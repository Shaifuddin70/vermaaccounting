<?php

declare(strict_types=1);

final class CmsRedirectRepository
{
    public const CODES = [
        301 => '301 Moved permanently',
        308 => '308 Permanent (keeps method)',
        302 => '302 Found (temporary)',
        307 => '307 Temporary (keeps method)',
        410 => '410 Gone',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function findBySource(string $path): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_redirects WHERE source_path = ?');
        $stmt->execute([$path]);
        return $stmt->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_redirects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public function search(string $q = '', int $limit = 50, int $offset = 0, string $origin = ''): array
    {
        [$where, $params] = $this->where($q, $origin);
        $stmt = $this->db->prepare('SELECT * FROM cms_redirects' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT ' . max(1, min(1000, $limit)) . ' OFFSET ' . max(0, $offset));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(string $q = '', string $origin = ''): int
    {
        [$where, $params] = $this->where($q, $origin);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM cms_redirects' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM cms_redirects ORDER BY source_path')->fetchAll();
    }

    private function where(string $q, string $origin): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(source_path LIKE ? OR target LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($origin !== '') {
            $where[] = 'origin = ?';
            $params[] = $origin;
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function save(?int $id, string $source, string $target, int $code, string $origin = 'manual', string $note = ''): int
    {
        $now = now_iso();
        if ($id !== null) {
            $this->db->prepare('UPDATE cms_redirects SET source_path = ?, target = ?, status_code = ?, note = ?, updated_at = ? WHERE id = ?')
                ->execute([$source, $target, $code, mb_substr($note, 0, 255), $now, $id]);
            return $id;
        }
        $existing = $this->findBySource($source);
        if ($existing) {
            $this->db->prepare('UPDATE cms_redirects SET target = ?, status_code = ?, origin = ?, note = ?, updated_at = ? WHERE id = ?')
                ->execute([$target, $code, $origin, mb_substr($note, 0, 255), $now, (int) $existing['id']]);
            return (int) $existing['id'];
        }
        $this->db->prepare('INSERT INTO cms_redirects (source_path, target, status_code, origin, hits, note, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)')
            ->execute([$source, $target, $code, $origin, mb_substr($note, 0, 255), Auth::check() ? Auth::userId() : null, $now, $now]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM cms_redirects WHERE id = ?')->execute([$id]);
    }

    public function deleteBySource(string $source): void
    {
        $this->db->prepare('DELETE FROM cms_redirects WHERE source_path = ?')->execute([$source]);
    }

    public function recordHit(int $id): void
    {
        try {
            $this->db->prepare('UPDATE cms_redirects SET hits = hits + 1, last_hit_at = ? WHERE id = ?')->execute([now_iso(), $id]);
        } catch (Throwable) {
        }
    }

    /**
     * Follow internal redirects from $start. Returns the hop list and whether it loops.
     *
     * @param array<string, string>|null $overrides extra source => target pairs to test before saving
     * @return array{hops: list<string>, loop: bool, final: string}
     */
    public function trace(string $start, ?array $overrides = null, int $max = 10): array
    {
        $hops = [$start];
        $seen = [$start => true];
        $current = $start;
        for ($i = 0; $i < $max; $i++) {
            $path = strtok($current, '?') ?: $current;
            if ($overrides !== null && isset($overrides[$path])) {
                $next = $overrides[$path];
            } else {
                $row = $this->findBySource($path);
                if (!$row || (int) $row['status_code'] === 410) {
                    break;
                }
                $next = (string) $row['target'];
            }
            if ($next === '' || $next[0] !== '/') {
                $hops[] = $next;
                break;
            }
            $nextPath = strtok($next, '?') ?: $next;
            if (isset($seen[$nextPath])) {
                $hops[] = $next;
                return ['hops' => $hops, 'loop' => true, 'final' => $next];
            }
            $seen[$nextPath] = true;
            $hops[] = $next;
            $current = $next;
        }
        return ['hops' => $hops, 'loop' => false, 'final' => end($hops)];
    }

    /**
     * Validate a redirect before saving. Errors block saving; warnings need confirmation or are advisory.
     *
     * @return array{errors: list<string>, warnings: list<string>, overwrite: ?array, source: string, target: string, code: int}
     */
    public function check(?int $id, string $sourceRaw, string $targetRaw, int $code, bool $allowExternal): array
    {
        $errors = [];
        $warnings = [];
        $base = CmsSiteConfig::baseUrl();
        $source = '';
        $target = '';

        $src = CmsPath::parseTarget($sourceRaw, $base);
        if (!$src['ok']) {
            $errors[] = 'Source: ' . $src['error'];
        } elseif (!$src['internal']) {
            $errors[] = 'Source must be a path on this site, like /old-page.';
        } else {
            $source = strtok($src['value'], '?') ?: '/';
            if ($source === '/') {
                $errors[] = 'The homepage cannot be redirected.';
            } elseif (str_starts_with($source, '/admin') || str_starts_with($source, '/api/')) {
                $errors[] = 'Admin and API URLs cannot be redirected.';
            }
        }
        if (!isset(self::CODES[$code])) {
            $errors[] = 'Choose a valid status code.';
        }

        if ($code !== 410 && isset(self::CODES[$code])) {
            $dst = CmsPath::parseTarget($targetRaw, $base);
            if (!$dst['ok']) {
                $errors[] = 'Destination: ' . $dst['error'];
            } elseif (!$dst['internal'] && !$allowExternal) {
                $errors[] = 'Redirecting to another website needs site code permission.';
            } else {
                $target = $dst['value'];
                if ($dst['internal'] && $source !== '') {
                    $targetPath = strtok($target, '?') ?: '/';
                    if ($targetPath === $source) {
                        $errors[] = 'Source and destination are the same URL.';
                    } else {
                        $trace = $this->trace($source, [$source => $target]);
                        if ($trace['loop']) {
                            $errors[] = 'This would create a redirect loop: ' . implode(' → ', $trace['hops']) . '.';
                        } elseif (count($trace['hops']) > 2) {
                            $warnings[] = 'Redirect chain: ' . implode(' → ', $trace['hops']) . '. Point directly to ' . $trace['final'] . ' instead.';
                        }
                        if (!$trace['loop'] && str_starts_with((string) $trace['final'], '/') && !CmsRouter::exists(strtok((string) $trace['final'], '?') ?: '/') && !is_file(PROJECT_ROOT . (strtok((string) $trace['final'], '?') ?: '/'))) {
                            $warnings[] = 'The destination ' . $trace['final'] . ' does not currently resolve (404).';
                        }
                    }
                }
            }
        }

        $overwrite = null;
        if ($source !== '') {
            $existing = $this->findBySource($source);
            if ($existing && (int) $existing['id'] !== (int) $id) {
                $overwrite = $existing;
                $warnings[] = $source . ' already redirects to ' . ($existing['target'] ?: '410 Gone') . ' (' . $existing['status_code'] . '). Saving replaces it.';
            }
            $live = (new CmsContentRepository())->findByPath($source);
            if (($live && CmsContentRepository::isLive($live)) || CmsPath::legacyPageExists($source)) {
                $warnings[] = $source . ' currently shows a page. The redirect takes priority and visitors will no longer see it.';
            }
            $stmt = Database::instance()->pdo()->prepare('SELECT source_path FROM cms_redirects WHERE target = ? AND source_path <> ?');
            $stmt->execute([$source, $source]);
            $incoming = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($incoming && $code !== 410) {
                $warnings[] = 'Redirects from ' . implode(', ', array_slice($incoming, 0, 5)) . ' point here and will now chain. Use “Fix chains” afterwards.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'overwrite' => $overwrite, 'source' => $source, 'target' => $code === 410 ? '' : $target, 'code' => $code];
    }

    /**
     * Loops, chains and dead destinations across all redirects.
     *
     * @return list<array{id: int, source: string, kind: string, detail: string, final: string}>
     */
    public function problems(): array
    {
        $out = [];
        foreach ($this->all() as $r) {
            if ((int) $r['status_code'] === 410 || ($r['target'] ?? '') === '' || $r['target'][0] !== '/') {
                continue;
            }
            $trace = $this->trace((string) $r['source_path']);
            $final = (string) $trace['final'];
            if ($trace['loop']) {
                $out[] = ['id' => (int) $r['id'], 'source' => $r['source_path'], 'kind' => 'loop', 'detail' => implode(' → ', $trace['hops']), 'final' => $final];
            } elseif (count($trace['hops']) > 2) {
                $out[] = ['id' => (int) $r['id'], 'source' => $r['source_path'], 'kind' => 'chain', 'detail' => implode(' → ', $trace['hops']), 'final' => $final];
            } elseif (str_starts_with($final, '/')) {
                $path = strtok($final, '?') ?: '/';
                if (!CmsRouter::exists($path) && !is_file(PROJECT_ROOT . $path)) {
                    $out[] = ['id' => (int) $r['id'], 'source' => $r['source_path'], 'kind' => 'broken', 'detail' => $final . ' returns 404', 'final' => $final];
                }
            }
        }
        return $out;
    }

    /** Point every chained redirect straight at its final destination. @return int updated */
    public function flattenChains(): int
    {
        $n = 0;
        foreach ($this->problems() as $p) {
            if ($p['kind'] !== 'chain') {
                continue;
            }
            $this->db->prepare('UPDATE cms_redirects SET target = ?, updated_at = ? WHERE id = ?')->execute([$p['final'], now_iso(), $p['id']]);
            $n++;
        }
        return $n;
    }

    /**
     * Import source,target,code,note rows. Rows with errors are skipped; existing sources are only replaced when $overwrite.
     *
     * @return array{saved: int, skipped: list<string>}
     */
    public function importCsv(string $file, bool $overwrite, bool $allowExternal): array
    {
        $fh = fopen($file, 'rb');
        if (!$fh) {
            return ['saved' => 0, 'skipped' => ['Could not read the file.']];
        }
        $first = fgetcsv($fh) ?: [];
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $first);
        $hasHeader = in_array('source', $header, true) || in_array('from', $header, true);
        if (!$hasHeader) {
            rewind($fh);
            $header = ['source', 'target', 'code', 'note'];
        }
        $saved = 0;
        $skipped = [];
        $line = $hasHeader ? 1 : 0;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if ($row === [null] || trim(implode('', $row)) === '') {
                continue;
            }
            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }
            $source = $data['source'] ?? $data['from'] ?? '';
            $target = $data['target'] ?? $data['to'] ?? $data['destination'] ?? '';
            $code = (int) (($data['code'] ?? $data['status'] ?? '') ?: 301);
            $check = $this->check(null, $source, $target, $code, $allowExternal);
            if ($check['errors']) {
                $skipped[] = 'Line ' . $line . ' (' . $source . '): ' . implode(' ', $check['errors']);
                continue;
            }
            if ($check['overwrite'] && !$overwrite) {
                $skipped[] = 'Line ' . $line . ' (' . $source . '): already redirected; tick “Replace existing” to overwrite.';
                continue;
            }
            $this->save($check['overwrite'] ? (int) $check['overwrite']['id'] : null, $check['source'], $check['target'], $code, 'csv', (string) ($data['note'] ?? ''));
            $saved++;
        }
        fclose($fh);
        return ['saved' => $saved, 'skipped' => $skipped];
    }
}
