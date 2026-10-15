<?php

declare(strict_types=1);

/**
 * The site's own stylesheets in /css, editable from Website → Stylesheets (cms.code).
 * Every save keeps a revision; the file on disk is replaced atomically.
 */
final class CmsStylesheets
{
    public const DIR = '/css';
    public const MAX_BYTES = 1024 * 1024;
    /** Files created from the admin, in load order: list<array{name: string, load: bool}> */
    public const ADDED_KEY = 'cms_stylesheets_added';

    private static ?array $added = null;

    /** @return list<array{name: string, url: string, bytes: int, modified: int, writable: bool, added: bool, load: bool}> */
    public static function all(): array
    {
        $added = self::added();
        $out = [];
        foreach (glob(PROJECT_ROOT . self::DIR . '/*.css') ?: [] as $file) {
            $name = basename($file);
            if (!self::validName($name)) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'url' => self::DIR . '/' . $name,
                'bytes' => (int) filesize($file),
                'modified' => (int) filemtime($file),
                'writable' => is_writable($file),
                'added' => isset($added[$name]),
                'load' => (bool) ($added[$name]['load'] ?? false),
            ];
        }
        usort($out, static fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $out;
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]*\.css$/i', $name);
    }

    public static function path(string $name): ?string
    {
        if (!self::validName($name)) {
            return null;
        }
        $file = PROJECT_ROOT . self::DIR . '/' . $name;
        return is_file($file) ? $file : null;
    }

    public static function read(string $name): ?string
    {
        $file = self::path($name);
        return $file === null ? null : (string) file_get_contents($file);
    }

    public static function hash(string $css): string
    {
        return hash('sha256', $css);
    }

    /**
     * Problems that would break the stylesheet or make it unsafe. Each message names the line.
     *
     * @return list<string>
     */
    public static function validate(string $css): array
    {
        $errors = [];
        if (strlen($css) > self::MAX_BYTES) {
            $errors[] = 'The stylesheet is larger than 1 MB.';
        }
        if (str_contains($css, "\0")) {
            $errors[] = 'The stylesheet contains invalid characters.';
        }
        if (preg_match('/<\?(php|=)?|<\/?script\b|<\/style/i', $css)) {
            $errors[] = 'HTML or PHP tags are not allowed in CSS.';
        }
        if (preg_match('/expression\s*\(|javascript\s*:|-moz-binding|(?<![\w-])behavior\s*:/i', $css, $m, PREG_OFFSET_CAPTURE)) {
            $errors[] = 'Line ' . self::lineAt($css, $m[0][1]) . ': "' . trim($m[0][0]) . '" is not allowed.';
        }

        $open = [];
        $structural = count($errors);
        $len = strlen($css);
        for ($i = 0; $i < $len; $i++) {
            $c = $css[$i];
            if ($c === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    $errors[] = 'Line ' . self::lineAt($css, $i) . ': comment is never closed with */.';
                    break;
                }
                $i = $end + 1;
            } elseif ($c === '"' || $c === "'") {
                $j = $i + 1;
                while ($j < $len && $css[$j] !== $c && $css[$j] !== "\n") {
                    $j += $css[$j] === '\\' ? 2 : 1;
                }
                if ($j >= $len || $css[$j] !== $c) {
                    $errors[] = 'Line ' . self::lineAt($css, $i) . ': text in quotes is never closed.';
                    break;
                }
                $i = $j;
            } elseif ($c === '{') {
                $open[] = $i;
            } elseif ($c === '}') {
                if (array_pop($open) === null) {
                    $errors[] = 'Line ' . self::lineAt($css, $i) . ': "}" has no matching "{".';
                    break;
                }
            }
        }
        if ($open !== [] && count($errors) === $structural) {
            $errors[] = 'Line ' . self::lineAt($css, (int) end($open)) . ': "{" is never closed with "}".'
                . (count($open) > 1 ? ' ' . count($open) . ' blocks are left open in total.' : '');
        }
        return $errors;
    }

    private static function lineAt(string $css, int $offset): int
    {
        return substr_count($css, "\n", 0, min($offset, strlen($css))) + 1;
    }

    /**
     * Validate and write a stylesheet. $expectedHash protects against overwriting a newer version on disk.
     *
     * @return list<string> errors (nothing written when non-empty)
     */
    public static function save(string $name, string $css, string $expectedHash = '', string $comment = ''): array
    {
        $file = self::path($name);
        if ($file === null) {
            return ['That stylesheet does not exist.'];
        }
        $css = str_replace("\r\n", "\n", $css);
        $current = (string) file_get_contents($file);
        if ($expectedHash !== '' && !hash_equals(self::hash($current), $expectedHash)) {
            return ['The file was changed on the server after you opened it (for example by a deployment). Your edits were not saved; copy them, reload the page and apply them again.'];
        }
        $errors = self::validate($css);
        if ($errors) {
            return $errors;
        }
        if ($css === $current) {
            return [];
        }
        if (!is_writable($file) || !is_writable(dirname($file))) {
            return ['The server cannot write to ' . self::DIR . '/' . $name . '. Make the file and the css folder writable by PHP.'];
        }

        self::ensureBaseline($name, $current);
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $css, LOCK_EX) !== strlen($css)) {
            @unlink($tmp);
            return ['The stylesheet could not be written.'];
        }
        @chmod($tmp, fileperms($file) & 0777);
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return ['The stylesheet could not be replaced.'];
        }
        clearstatcache(true, $file);
        self::recordRevision($name, $css, $comment !== '' ? $comment : 'Edited');
        CmsCache::flush();
        ActivityLog::record('cms.stylesheet_saved', 'cms_stylesheet', null, ['file' => $name, 'bytes' => strlen($css)]);
        return [];
    }

    /** Keep the version that was on disk before the first edit from the admin, so it can always be restored. */
    private static function ensureBaseline(string $name, string $current): void
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT hash FROM cms_stylesheet_revisions WHERE file = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$name]);
        $last = $stmt->fetchColumn();
        if ($last === false) {
            self::recordRevision($name, $current, 'Original file');
        } elseif ($last !== self::hash($current)) {
            self::recordRevision($name, $current, 'Changed outside the admin (e.g. deployment)');
        }
    }

    private static function recordRevision(string $name, string $css, string $comment): void
    {
        $user = Auth::currentUser();
        Database::instance()->pdo()->prepare('INSERT INTO cms_stylesheet_revisions (file, css, hash, comment, user_id, user_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $css, self::hash($css), mb_substr($comment, 0, 255), Auth::userId(), (string) ($user['name'] ?? ''), now_iso()]);
    }

    /** @return list<array{id: int, comment: string, user_name: string, created_at: string, bytes: int, hash: string}> */
    public static function revisions(string $name, int $limit = 50): array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT id, comment, user_name, created_at, LENGTH(css) AS bytes, hash FROM cms_stylesheet_revisions WHERE file = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$name]);
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'bytes' => (int) $r['bytes']] + $r, $stmt->fetchAll());
    }

    public static function revision(int $id): ?array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_stylesheet_revisions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ---------------------------------------------------------------- files added from the admin

    /** @return array<string, array{name: string, load: bool}> in load order */
    public static function added(): array
    {
        if (self::$added === null) {
            self::$added = [];
            try {
                foreach ((array) (new SettingsRepository())->get(self::ADDED_KEY, []) as $row) {
                    if (is_array($row) && self::validName((string) ($row['name'] ?? ''))) {
                        self::$added[(string) $row['name']] = ['name' => (string) $row['name'], 'load' => !empty($row['load'])];
                    }
                }
            } catch (Throwable) {
                self::$added = [];
            }
        }
        return self::$added;
    }

    private static function storeAdded(array $added): void
    {
        (new SettingsRepository())->set(self::ADDED_KEY, array_values($added));
        self::$added = null;
        CmsCache::flush();
    }

    /** "Blog Extras" → "blog-extras.css" */
    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = (string) preg_replace('/\.css$/', '', $name);
        $name = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $name), '-');
        return $name === '' ? '' : $name . '.css';
    }

    /**
     * Create a new stylesheet in /css. With $load it is linked on every page (hand-written and CMS),
     * after the site's main stylesheets so its rules can override them.
     *
     * @return array{name: string, errors: list<string>}
     */
    public static function create(string $rawName, string $css = '', bool $load = true): array
    {
        $name = self::normalizeName($rawName);
        $css = str_replace("\r\n", "\n", $css);
        if ($name === '' || !self::validName($name)) {
            return ['name' => $name, 'errors' => ['Use a file name made of letters, numbers, - and _ (for example blog-extras).']];
        }
        $file = PROJECT_ROOT . self::DIR . '/' . $name;
        if (is_file($file)) {
            return ['name' => $name, 'errors' => [self::DIR . '/' . $name . ' already exists. Open it from the list to edit it.']];
        }
        $errors = self::validate($css);
        if ($errors) {
            return ['name' => $name, 'errors' => $errors];
        }
        if (!is_writable(PROJECT_ROOT . self::DIR)) {
            return ['name' => $name, 'errors' => ['The server cannot create files in ' . self::DIR . '. Make the css folder writable by PHP.']];
        }
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $written = file_put_contents($tmp, $css, LOCK_EX) === strlen($css);
        @chmod($tmp, 0644);
        if (!$written || !rename($tmp, $file)) {
            @unlink($tmp);
            return ['name' => $name, 'errors' => ['The stylesheet could not be created.']];
        }
        clearstatcache(true, $file);
        Database::instance()->pdo()->prepare('DELETE FROM cms_stylesheet_revisions WHERE file = ?')->execute([$name]);
        self::recordRevision($name, $css, 'Created');
        $added = self::added();
        $added[$name] = ['name' => $name, 'load' => $load];
        self::storeAdded($added);
        ActivityLog::record('cms.stylesheet_created', 'cms_stylesheet', null, ['file' => $name, 'load' => $load]);
        return ['name' => $name, 'errors' => []];
    }

    /** Turn automatic loading on or off for a file created from the admin. */
    public static function setLoad(string $name, bool $load): bool
    {
        $added = self::added();
        if (!isset($added[$name])) {
            return false;
        }
        $added[$name]['load'] = $load;
        self::storeAdded($added);
        ActivityLog::record('cms.stylesheet_loading', 'cms_stylesheet', null, ['file' => $name, 'load' => $load]);
        return true;
    }

    /** Delete a file created from the admin, with its history. The site's own stylesheets cannot be deleted here. */
    public static function delete(string $name): array
    {
        $added = self::added();
        $file = self::path($name);
        if (!isset($added[$name])) {
            return ['Only stylesheets created here can be deleted.'];
        }
        if ($file !== null && !@unlink($file)) {
            return ['The server could not delete ' . self::DIR . '/' . $name . '.'];
        }
        unset($added[$name]);
        self::storeAdded($added);
        Database::instance()->pdo()->prepare('DELETE FROM cms_stylesheet_revisions WHERE file = ?')->execute([$name]);
        ActivityLog::record('cms.stylesheet_deleted', 'cms_stylesheet', null, ['file' => $name]);
        return [];
    }

    /** <link> tags for added files that load on every page. */
    public static function headLinks(): string
    {
        $out = '';
        foreach (self::added() as $name => $row) {
            if ($row['load'] && self::path($name) !== null) {
                $out .= '<link rel="stylesheet" href="' . htmlspecialchars(self::versionedUrl(self::DIR . '/' . $name), ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }
        return $out;
    }

    /** Cache-busting version for a file under the web root, from its modification time. */
    public static function version(string $publicPath): string
    {
        $path = strtok($publicPath, '?') ?: '';
        if (!str_starts_with($path, '/') || str_contains($path, '..')) {
            return '';
        }
        $file = PROJECT_ROOT . $path;
        return is_file($file) ? (string) filemtime($file) : '';
    }

    /** "/css/styles.css?v=86" → "/css/styles.css?v=<mtime>" for local files; other URLs are returned unchanged. */
    public static function versionedUrl(string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }
        $version = self::version($url);
        return $version === '' ? $url : (strtok($url, '?') ?: $url) . '?v=' . $version;
    }
}
