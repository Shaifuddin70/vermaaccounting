<?php

declare(strict_types=1);

/**
 * Reusable code blocks. Writing {{block:token}} in a page body, the site header or footer, or another block
 * renders the block's code in its place. Blocks are trusted code (cms.code) and are output verbatim.
 */
final class CmsBlocks
{
    public const MAX_BYTES = 512 * 1024;
    private const MAX_DEPTH = 5;
    private const TOKEN = '[a-z0-9][a-z0-9_-]{0,63}';

    /** @var array<string, array>|null active blocks by token, loaded once per request */
    private static ?array $active = null;

    public static function tokenTag(string $token): string
    {
        return '{{block:' . $token . '}}';
    }

    public static function validToken(string $token): bool
    {
        return (bool) preg_match('/^' . self::TOKEN . '$/', $token);
    }

    /** "Footer CTA banner" → "footer-cta-banner" */
    public static function suggestToken(string $name): string
    {
        $token = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        return substr($token, 0, 64);
    }

    // ---------------------------------------------------------------- storage

    /** @return list<array> */
    public static function all(): array
    {
        return Database::instance()->pdo()->query('SELECT * FROM cms_blocks ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_blocks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_blocks WHERE token = ?');
        $stmt->execute([strtolower($token)]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create or update a block. The token cannot change after creation, so existing references keep working.
     *
     * @param array{name?: string, token?: string, description?: string, code?: string, status?: string} $input
     * @return array{id: int, errors: list<string>}
     */
    public static function save(?int $id, array $input, string $comment = ''): array
    {
        $existing = $id ? self::find($id) : null;
        if ($id && $existing === null) {
            return ['id' => 0, 'errors' => ['That block no longer exists.']];
        }
        $name = trim((string) ($input['name'] ?? ''));
        $token = $existing ? (string) $existing['token'] : strtolower(trim((string) ($input['token'] ?? '')));
        if ($token === '' && $name !== '') {
            $token = self::suggestToken($name);
        }
        $code = str_replace("\r\n", "\n", (string) ($input['code'] ?? ''));
        $status = ($input['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
        $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 500);

        $errors = [];
        if ($name === '') {
            $errors[] = 'Give the block a name.';
        }
        if (!self::validToken($token)) {
            $errors[] = 'The token may only use lowercase letters, numbers, - and _ (for example footer-cta).';
        } elseif (!$existing && self::findByToken($token) !== null) {
            $errors[] = 'Another block already uses the token ' . self::tokenTag($token) . '.';
        }
        if (strlen($code) > self::MAX_BYTES) {
            $errors[] = 'The code is larger than 512 KB.';
        }
        if (preg_match('/<\?(php|=)?/i', $code)) {
            $errors[] = 'PHP code is not allowed in blocks.';
        }
        if (self::validToken($token) && in_array($token, self::referencedTokens($code), true)) {
            $errors[] = 'A block cannot include itself.';
        }
        if ($errors) {
            return ['id' => (int) $id, 'errors' => $errors];
        }

        if ($existing && $existing['name'] === $name && $existing['code'] === $code && $existing['status'] === $status && $existing['description'] === $description) {
            return ['id' => (int) $id, 'errors' => []];
        }
        $pdo = Database::instance()->pdo();
        $now = now_iso();
        $user = (string) (Auth::currentUser()['name'] ?? '');
        if ($existing) {
            $pdo->prepare('UPDATE cms_blocks SET name = ?, description = ?, code = ?, status = ?, updated_at = ?, updated_by_name = ? WHERE id = ?')
                ->execute([$name, $description, $code, $status, $now, $user, $id]);
        } else {
            $pdo->prepare('INSERT INTO cms_blocks (token, name, description, code, status, created_at, updated_at, updated_by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$token, $name, $description, $code, $status, $now, $now, $user]);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO cms_block_revisions (block_id, name, code, status, comment, user_id, user_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $name, $code, $status, mb_substr($comment !== '' ? $comment : ($existing ? 'Edited' : 'Created'), 0, 255), Auth::userId(), $user, $now]);
        self::$active = null;
        CmsCache::flush();
        ActivityLog::record($existing ? 'cms.block_updated' : 'cms.block_created', 'cms_block', $id, ['token' => $token]);
        return ['id' => (int) $id, 'errors' => []];
    }

    public static function delete(int $id): bool
    {
        $block = self::find($id);
        if ($block === null) {
            return false;
        }
        $pdo = Database::instance()->pdo();
        $pdo->prepare('DELETE FROM cms_block_revisions WHERE block_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM cms_blocks WHERE id = ?')->execute([$id]);
        self::$active = null;
        CmsCache::flush();
        ActivityLog::record('cms.block_deleted', 'cms_block', $id, ['token' => $block['token']]);
        return true;
    }

    /** @return list<array> newest first */
    public static function revisions(int $blockId, int $limit = 50): array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_block_revisions WHERE block_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }

    public static function revision(int $id): ?array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_block_revisions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ---------------------------------------------------------------- references

    /** @return list<string> tokens referenced in $html, lowercase, unique */
    public static function referencedTokens(string $html): array
    {
        if (!str_contains($html, '{{') || !preg_match_all('/\{\{\s*block:(' . self::TOKEN . ')\s*\}\}/i', $html, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** Tokens in $html that have no active block. @return list<string> */
    public static function missingTokens(string $html): array
    {
        $active = self::activeBlocks();
        return array_values(array_filter(self::referencedTokens($html), static fn ($t) => !isset($active[$t])));
    }

    /**
     * Where a token is used: CMS content bodies, the site header and footer, and other blocks.
     *
     * @return list<array{kind: string, label: string, url: string}>
     */
    public static function usage(string $token): array
    {
        $out = [];
        $stmt = Database::instance()->pdo()->prepare('SELECT id, title, path, type, body_html FROM cms_contents WHERE body_html LIKE ? ORDER BY title');
        $stmt->execute(['%block:' . $token . '%']);
        foreach ($stmt->fetchAll() as $row) {
            if (in_array($token, self::referencedTokens((string) $row['body_html']), true)) {
                $out[] = ['kind' => ucfirst((string) $row['type']), 'label' => CmsCityDefaults::label($row) . ' (' . $row['path'] . ')', 'url' => '/admin/cms-edit?id=' . (int) $row['id']];
            }
        }
        foreach (['header_html' => 'Site header', 'footer_html' => 'Site footer'] as $field => $label) {
            if (in_array($token, self::referencedTokens((string) CmsSiteConfig::value($field)), true)) {
                $out[] = ['kind' => 'Site configuration', 'label' => $label, 'url' => '/admin/cms-site-config#chrome'];
            }
        }
        foreach (self::all() as $block) {
            if ($block['token'] !== $token && in_array($token, self::referencedTokens((string) $block['code']), true)) {
                $out[] = ['kind' => 'Block', 'label' => $block['name'] . ' ' . self::tokenTag((string) $block['token']), 'url' => '/admin/cms-blocks?id=' . (int) $block['id']];
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------- rendering

    /** @return array<string, array> */
    private static function activeBlocks(): array
    {
        if (self::$active === null) {
            self::$active = [];
            try {
                foreach (Database::instance()->pdo()->query("SELECT token, name, code FROM cms_blocks WHERE status = 'active'")->fetchAll() as $row) {
                    self::$active[(string) $row['token']] = $row;
                }
            } catch (Throwable $e) {
                error_log('CMS blocks unavailable: ' . $e->getMessage());
            }
        }
        return self::$active;
    }

    /** Drop the per-request block list (after saving in the same request, e.g. tests). */
    public static function reset(): void
    {
        self::$active = null;
    }

    /**
     * Replace {{block:token}} with each block's code. A token alone in a paragraph replaces the whole
     * paragraph, so block-level code is not wrapped in <p>. Unknown or disabled tokens render nothing
     * (a visible note in previews). Blocks may include other blocks; loops are cut off.
     *
     * @param list<string> $stack tokens being expanded (loop protection)
     */
    public static function expand(string $html, string $path = '/', bool $preview = false, array $stack = []): string
    {
        if (!str_contains($html, '{{') || stripos($html, 'block:') === false) {
            return $html;
        }
        $token = '\{\{\s*block:(' . self::TOKEN . ')\s*\}\}';
        $replace = function (array $m) use ($path, $preview, $stack): string {
            return self::renderToken(strtolower($m[1]), $path, $preview, $stack);
        };
        $html = (string) preg_replace_callback('#<p\b[^>]*>(?:\s|&nbsp;|<br\s*/?>)*' . $token . '(?:\s|&nbsp;|<br\s*/?>)*</p>#i', $replace, $html);
        return (string) preg_replace_callback('#' . $token . '#i', $replace, $html);
    }

    private static function renderToken(string $token, string $path, bool $preview, array $stack): string
    {
        $block = self::activeBlocks()[$token] ?? null;
        $label = '&#123;&#123;block:' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '&#125;&#125;';
        if ($block === null) {
            return $preview ? '<div class="cms-block-missing">Block ' . $label . ' does not exist or is disabled.</div>' : '';
        }
        if (in_array($token, $stack, true) || count($stack) >= self::MAX_DEPTH) {
            return $preview ? '<div class="cms-block-missing">Block ' . $label . ' includes itself and was skipped.</div>' : '';
        }
        $code = self::expand((string) $block['code'], $path, $preview, array_merge($stack, [$token]));
        return CmsRenderer::expandPlaceholders($code, $path, false);
    }

    /** One block's rendered code, for hand-written PHP pages. */
    public static function render(string $token, string $path = '/'): string
    {
        return self::renderToken(strtolower($token), $path, false, []);
    }
}

/** Output a CMS block in a hand-written page: <?= cms_block('footer-cta') ?> */
function cms_block(string $token): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    return CmsBlocks::render($token, $path);
}
