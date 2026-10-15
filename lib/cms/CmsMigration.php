<?php

declare(strict_types=1);

/**
 * Website migration: URL inventory, crawling, dry-run imports into drafts, redirect validation and reports.
 */
final class CmsMigration
{
    public const ACTIONS = ['pending' => 'Not decided', 'import' => 'Import as CMS draft', 'redirect' => 'Redirect only', 'keep' => 'Keep hand-written page', 'skip' => 'Skip', 'imported' => 'Imported'];
    public const CRAWL_BATCH = 10;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /** @return list<array> */
    public function items(string $filter = '', int $limit = 500): array
    {
        $sql = 'SELECT * FROM cms_migration_items';
        $params = [];
        if ($filter !== '' && isset(self::ACTIONS[$filter])) {
            $sql .= ' WHERE action = ?';
            $params[] = $filter;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY source_path LIMIT ' . max(1, $limit));
        $stmt->execute($params);
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_migration_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['links'] = json_decode((string) ($row['links_json'] ?? ''), true) ?: [];
        $row['images'] = json_decode((string) ($row['images_json'] ?? ''), true) ?: [];
        return $row;
    }

    public function counts(): array
    {
        $out = array_fill_keys(array_keys(self::ACTIONS), 0);
        foreach ($this->db->query('SELECT action, COUNT(*) n FROM cms_migration_items GROUP BY action')->fetchAll() as $r) {
            $out[$r['action']] = (int) $r['n'];
        }
        $out['total'] = array_sum($out);
        $out['crawled'] = (int) $this->db->query('SELECT COUNT(*) FROM cms_migration_items WHERE crawled_at IS NOT NULL')->fetchColumn();
        return $out;
    }

    /** Add or refresh an inventory row keyed by normalized source path. */
    public function upsertItem(string $sourceUrlOrPath, string $origin, array $extra = []): ?int
    {
        $base = CmsSiteConfig::baseUrl();
        $parsed = CmsPath::parseTarget($sourceUrlOrPath, $base);
        if (!$parsed['ok'] || !$parsed['internal']) {
            return null;
        }
        $path = strtok($parsed['value'], '?') ?: '/';
        $now = now_iso();
        $stmt = $this->db->prepare('SELECT id FROM cms_migration_items WHERE source_path = ?');
        $stmt->execute([$path]);
        $id = $stmt->fetchColumn();
        $fields = array_intersect_key($extra, array_flip(['target_path', 'content_type', 'folder_id', 'action', 'title', 'description', 'notes']));
        if ($id) {
            if ($fields) {
                $sets = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($fields)));
                $this->db->prepare('UPDATE cms_migration_items SET ' . $sets . ', updated_at = ? WHERE id = ?')->execute([...array_values($fields), $now, (int) $id]);
            }
            return (int) $id;
        }
        $row = array_merge([
            'source_url' => $base . ($path === '/' ? '/' : $path),
            'source_path' => $path,
            'origin' => $origin,
            'http_status' => 0,
            'title' => '', 'description' => '', 'canonical' => '', 'robots' => '', 'h1' => '', 'body_html' => '',
            'links_json' => '[]', 'images_json' => '[]', 'word_count' => 0,
            'content_type' => 'page', 'folder_id' => null, 'target_path' => $path, 'action' => 'pending', 'content_id' => null, 'notes' => '',
            'crawled_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ], $fields);
        $cols = array_keys($row);
        $this->db->prepare('INSERT INTO cms_migration_items (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
        return (int) $this->db->lastInsertId();
    }

    /** Seed from the hand-written pages and the old static sitemap.xml. */
    public function inventoryFromSite(): int
    {
        $n = 0;
        foreach (CmsPath::legacyPages() as $path) {
            if ($this->upsertItem($path, 'legacy') !== null) {
                $n++;
            }
        }
        $old = PROJECT_ROOT . '/sitemap.xml';
        if (is_file($old) && preg_match_all('#<loc>([^<]+)</loc>#', (string) file_get_contents($old), $m)) {
            foreach ($m[1] as $loc) {
                if ($this->upsertItem(html_entity_decode(trim($loc)), 'sitemap') !== null) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /**
     * CSV inventory import. Columns (header row required): url, target_path, type, action, notes.
     *
     * @return array{added: int, errors: list<string>}
     */
    public function importInventoryCsv(string $file): array
    {
        $fh = fopen($file, 'rb');
        if (!$fh) {
            return ['added' => 0, 'errors' => ['Could not read the file.']];
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), fgetcsv($fh) ?: []);
        $added = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            $data = array_combine(array_slice($header, 0, count($row)), array_slice($row, 0, count($header))) ?: [];
            $url = trim((string) ($data['url'] ?? $data['source'] ?? ''));
            if ($url === '') {
                continue;
            }
            $extra = [];
            if (!empty($data['target_path'])) {
                $extra['target_path'] = CmsPath::normalize((string) $data['target_path']);
            }
            if (!empty($data['type']) && isset(CmsContentRepository::TYPES[$data['type']])) {
                $extra['content_type'] = $data['type'];
            }
            if (!empty($data['action']) && isset(self::ACTIONS[$data['action']])) {
                $extra['action'] = $data['action'];
            }
            if (!empty($data['notes'])) {
                $extra['notes'] = (string) $data['notes'];
            }
            if ($this->upsertItem($url, 'csv', $extra) === null) {
                $errors[] = 'Line ' . $line . ': ' . $url . ' is not a URL on this site.';
            } else {
                $added++;
            }
        }
        fclose($fh);
        return ['added' => $added, 'errors' => $errors];
    }

    public function update(int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['target_path', 'content_type', 'folder_id', 'action', 'notes']));
        if (!$allowed) {
            return;
        }
        $sets = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($allowed)));
        $this->db->prepare('UPDATE cms_migration_items SET ' . $sets . ', updated_at = ? WHERE id = ?')->execute([...array_values($allowed), now_iso(), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM cms_migration_items WHERE id = ?')->execute([$id]);
    }

    /** Crawl the next batch of uncrawled items (or the given ids). @return int crawled */
    public function crawlBatch(array $ids = [], int $limit = self::CRAWL_BATCH): int
    {
        if ($ids) {
            $items = array_filter(array_map(fn ($id) => $this->find((int) $id), $ids));
        } else {
            $items = array_map([$this, 'hydrate'], $this->db->query('SELECT * FROM cms_migration_items WHERE crawled_at IS NULL ORDER BY id LIMIT ' . max(1, $limit))->fetchAll());
        }
        $n = 0;
        foreach ($items as $item) {
            $this->crawl($item);
            $n++;
        }
        return $n;
    }

    public function crawl(array $item): void
    {
        $result = self::fetch((string) $item['source_url']);
        $data = self::extract($result['body'], (string) $item['source_url']);
        $this->db->prepare('UPDATE cms_migration_items SET http_status = ?, title = ?, description = ?, canonical = ?, robots = ?, h1 = ?, body_html = ?, links_json = ?, images_json = ?, word_count = ?, crawled_at = ?, updated_at = ?, notes = ? WHERE id = ?')
            ->execute([
                $result['status'], mb_substr($data['title'], 0, 500), $data['description'], mb_substr($data['canonical'], 0, 1000), mb_substr($data['robots'], 0, 255),
                mb_substr($data['h1'], 0, 500), $data['body'], json_encode($data['links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($data['images'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $data['words'], now_iso(), now_iso(),
                trim((string) $item['notes'] . ($result['error'] !== '' ? "\nCrawl: " . $result['error'] : ($result['location'] !== '' ? "\nRedirects to " . $result['location'] : ''))),
                (int) $item['id'],
            ]);
    }

    /** @return array{status: int, body: string, location: string, error: string} */
    public static function fetch(string $url, bool $follow = false): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'VermaCMS-Migration/1.0',
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $location = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
        $error = $body === false ? curl_error($ch) : '';
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'location' => $location, 'error' => $error];
    }

    /**
     * Pull SEO metadata and main content from an HTML document.
     *
     * @return array{title: string, description: string, canonical: string, robots: string, h1: string, body: string, links: list<array>, images: list<array>, words: int}
     */
    public static function extract(string $html, string $pageUrl): array
    {
        $out = ['title' => '', 'description' => '', 'canonical' => '', 'robots' => '', 'h1' => '', 'body' => '', 'links' => [], 'images' => [], 'words' => 0];
        if (trim($html) === '') {
            return $out;
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $text = static fn (?DOMNode $n): string => $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent) ?? '') : '';
        $out['title'] = $text($xp->query('//title')->item(0));
        $out['description'] = trim((string) ($xp->query('//meta[@name="description"]/@content')->item(0)?->nodeValue ?? ''));
        $out['canonical'] = trim((string) ($xp->query('//link[@rel="canonical"]/@href')->item(0)?->nodeValue ?? ''));
        $out['robots'] = trim((string) ($xp->query('//meta[@name="robots"]/@content')->item(0)?->nodeValue ?? ''));
        $out['h1'] = $text($xp->query('//h1')->item(0));

        $main = $xp->query('//main')->item(0) ?? $xp->query('//body')->item(0);
        if ($main) {
            foreach (['.//script', './/style', './/noscript', './/header[contains(@class,"sh")]', './/nav', './/footer', './/form'] as $q) {
                foreach (iterator_to_array($xp->query($q, $main)) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
            $inner = '';
            foreach ($main->childNodes as $child) {
                $inner .= $doc->saveHTML($child);
            }
            $out['body'] = trim($inner);
            $out['words'] = CmsHtml::wordCount($out['body']);
        }
        $base = CmsSiteConfig::baseUrl();
        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || $href[0] === '#' || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }
            $abs = self::absolutize($href, $pageUrl);
            $parsed = CmsPath::parseTarget($abs, $base);
            $out['links'][] = ['href' => $href, 'internal' => $parsed['ok'] && $parsed['internal'], 'path' => $parsed['ok'] && $parsed['internal'] ? (strtok($parsed['value'], '?') ?: '/') : '', 'text' => mb_substr($text($a), 0, 120)];
        }
        foreach ($xp->query('//img[@src]') as $img) {
            $out['images'][] = ['src' => self::absolutize($img->getAttribute('src'), $pageUrl), 'alt' => $img->hasAttribute('alt') ? $img->getAttribute('alt') : null];
        }
        return $out;
    }

    private static function absolutize(string $href, string $pageUrl): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($pageUrl);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($p['scheme'] ?? 'https') . ':' . $href;
        }
        if ($href[0] === '/') {
            return $origin . $href;
        }
        $dir = rtrim(dirname($p['path'] ?? '/'), '/');
        return $origin . $dir . '/' . $href;
    }

    /**
     * What importing an item would do.
     *
     * @return array{ok: bool, path: string, problems: list<string>, notes: list<string>}
     */
    public function plan(array $item): array
    {
        $repo = new CmsContentRepository();
        $path = CmsPath::normalize((string) ($item['target_path'] ?: $item['source_path']));
        $problems = [];
        $notes = [];
        if ($item['crawled_at'] === null) {
            $problems[] = 'Not crawled yet.';
        } elseif ((int) $item['http_status'] !== 200) {
            $problems[] = 'Source returned HTTP ' . (int) $item['http_status'] . '.';
        }
        if ($path === '/') {
            $notes[] = 'Will be imported at /home and set as the homepage when you choose.';
            $path = '/home';
        }
        foreach (CmsPath::validate($path) as $e) {
            $problems[] = $e;
        }
        $existing = $repo->findByPath($path);
        if ($existing && (int) $existing['id'] !== (int) ($item['content_id'] ?? 0)) {
            $problems[] = 'CMS content already exists at ' . $path . ' ("' . $existing['title'] . '").';
        }
        if (trim((string) $item['body_html']) === '') {
            $problems[] = 'No main content was extracted.';
        }
        $stmt = $this->db->prepare('SELECT source_path FROM cms_migration_items WHERE id <> ? AND title <> \'\' AND title = ?');
        $stmt->execute([(int) $item['id'], (string) $item['title']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $dup) {
            $notes[] = 'Same title as ' . $dup . ' (possible duplicate).';
        }
        if ($path !== $item['source_path'] && $item['source_path'] !== '/') {
            $notes[] = 'URL changes: a 301 from ' . $item['source_path'] . ' to ' . $path . ' will be created when the new page is published.';
        }
        if (CmsPath::legacyPageExists($path)) {
            $notes[] = 'The hand-written page stays live until the imported draft is published.';
        }
        return ['ok' => $problems === [], 'path' => $path, 'problems' => $problems, 'notes' => $notes];
    }

    /**
     * Import an item as a draft. Nothing is published automatically.
     *
     * @return array{ok: bool, content_id: ?int, problems: list<string>}
     */
    public function import(array $item, bool $importImages = false): array
    {
        $plan = $this->plan($item);
        if (!$plan['ok']) {
            return ['ok' => false, 'content_id' => null, 'problems' => $plan['problems']];
        }
        $repo = new CmsContentRepository();
        $suffix = trim((string) CmsSiteConfig::value('title_suffix', ''), " |-–");
        $title = trim((string) ($item['h1'] ?: $item['title']));
        if ($suffix !== '') {
            $title = trim(preg_replace('/\s*[|\-–]\s*' . preg_quote($suffix, '/') . '.*$/i', '', $title) ?? $title);
        }
        $body = (string) $item['body_html'];
        $body = preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', '', $body, 1) ?? $body;
        $body = CmsHtml::sanitize($body);
        if ($importImages) {
            $body = $this->mapImages($body, (string) $item['source_url']);
        }
        $path = $plan['path'];
        $pdo = $repo->pdo();
        $pdo->beginTransaction();
        try {
            $id = $repo->create([
                'type' => $item['content_type'] ?: 'page',
                'folder_id' => $item['folder_id'] ? (int) $item['folder_id'] : null,
                'internal_name' => 'Migrated: ' . $item['source_path'],
                'title' => $title !== '' ? $title : $item['source_path'],
                'h1' => (string) $item['h1'],
                'slug' => basename($path),
                'path' => $path,
                'body_html' => $body,
                'body_mode' => 'sanitized',
                'template' => $item['content_type'] === 'article' ? 'article' : 'full-width',
                'language' => (string) CmsSiteConfig::value('language', 'en-CA'),
                'status' => 'draft',
                'internal_notes' => 'Imported from ' . $item['source_url'] . ' on ' . gmdate('Y-m-d') . '.',
                'faqs_json' => '[]',
                'created_by' => Auth::userId(),
                'updated_by' => Auth::userId(),
            ]);
            $robots = strtolower((string) $item['robots']);
            $repo->saveSeo($id, [
                'seo_title' => (string) $item['title'],
                'meta_description' => (string) $item['description'],
                'robots_index' => str_contains($robots, 'noindex') ? 'noindex' : 'inherit',
                'robots_follow' => str_contains($robots, 'nofollow') ? 'nofollow' : 'inherit',
                'sitemap_include' => 1,
                'canonical_mode' => 'auto',
                'schema_json' => '{"disabled":[]}',
            ]);
            if ($item['content_type'] === 'city') {
                $repo->saveCity($id, ['city' => '', 'related_services_json' => '[]', 'hours_json' => '[]', 'nearby_json' => '[]', 'cta_json' => '{}', 'local_business' => 0]);
            }
            $service = new CmsContentService($repo);
            $service->indexLinks($id, $body);
            $repo->addRevision($id, $service->snapshot($id), 'Imported from ' . $item['source_path']);
            $this->db->prepare("UPDATE cms_migration_items SET action = 'imported', content_id = ?, target_path = ?, updated_at = ? WHERE id = ?")
                ->execute([$id, $path, now_iso(), (int) $item['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'content_id' => null, 'problems' => [app_safe_error_message($e, 'Import failed.')]];
        }
        ActivityLog::record('cms.migration_imported', 'cms_content', $id, ['source' => $item['source_path']]);
        return ['ok' => true, 'content_id' => $id, 'problems' => []];
    }

    /** Copy images on this site into the media library and point the body at them. */
    private function mapImages(string $body, string $pageUrl): string
    {
        $media = new CmsMediaRepository();
        return preg_replace_callback('/(<img\b[^>]*\ssrc=")([^"]+)(")/i', function (array $m) use ($media, $pageUrl): string {
            $src = html_entity_decode($m[2]);
            if (str_starts_with($src, '/media/')) {
                return $m[0];
            }
            $abs = self::absolutize($src, $pageUrl);
            $parsed = CmsPath::parseTarget($abs, CmsSiteConfig::baseUrl());
            if (!$parsed['ok'] || !$parsed['internal']) {
                return $m[0];
            }
            $file = PROJECT_ROOT . rawurldecode(strtok($parsed['value'], '?') ?: '');
            if (!is_file($file)) {
                return $m[0];
            }
            try {
                $item = $media->importLocal($file);
                return $m[1] . htmlspecialchars($item['url'], ENT_QUOTES) . $m[3];
            } catch (Throwable) {
                return $m[0];
            }
        }, $body) ?? $body;
    }

    /**
     * Redirects needed for imported items whose URL changed; created only when the new page is live.
     *
     * @return array{created: int, waiting: int, problems: list<string>}
     */
    public function createRedirects(): array
    {
        $repo = new CmsContentRepository();
        $redirects = new CmsRedirectRepository();
        $created = 0;
        $waiting = 0;
        $problems = [];
        foreach ($this->items() as $item) {
            $isRedirectOnly = $item['action'] === 'redirect';
            if (!$isRedirectOnly && $item['action'] !== 'imported') {
                continue;
            }
            $source = (string) $item['source_path'];
            $target = CmsPath::normalize((string) $item['target_path']);
            if ($source === $target || $source === '/') {
                continue;
            }
            if (!$isRedirectOnly) {
                $content = $item['content_id'] ? $repo->find((int) $item['content_id']) : null;
                if (!$content || !CmsContentRepository::isLive($content)) {
                    $waiting++;
                    continue;
                }
                $target = (string) $content['path'];
            } elseif (!CmsRouter::exists($target)) {
                $problems[] = $source . ': target ' . $target . ' does not resolve.';
                continue;
            }
            $existing = $redirects->findBySource($source);
            if ($existing && (string) $existing['target'] !== $target) {
                $problems[] = $source . ' already redirects to ' . $existing['target'] . '; not overwritten.';
                continue;
            }
            $trace = $redirects->trace($source, [$source => $target]);
            if ($trace['loop']) {
                $problems[] = $source . ': would create a loop.';
                continue;
            }
            $redirects->save($existing ? (int) $existing['id'] : null, $source, $target, 301, 'import', 'Migration');
            $created++;
        }
        if ($created) {
            CmsCache::flush();
            ActivityLog::record('cms.migration_redirects', 'cms_redirect', null, ['created' => $created]);
        }
        return ['created' => $created, 'waiting' => $waiting, 'problems' => $problems];
    }

    /**
     * Old-versus-new comparison for imported items.
     *
     * @return list<array{item: array, content: ?array, effective: ?array, diffs: list<string>}>
     */
    public function comparisons(): array
    {
        $repo = new CmsContentRepository();
        $out = [];
        foreach ($this->items('imported') as $item) {
            if ($item['origin'] === 'blog') {
                continue;
            }
            $content = $item['content_id'] ? $repo->find((int) $item['content_id']) : null;
            $eff = null;
            $diffs = [];
            if ($content) {
                $eff = CmsSeo::effective($content, $repo->seo((int) $content['id']), null, []);
                if (trim((string) $item['title']) !== $eff['title']) {
                    $diffs[] = 'Title changed';
                }
                if (trim((string) $item['description']) !== $eff['description']) {
                    $diffs[] = 'Description changed';
                }
                $oldH1 = trim((string) $item['h1']);
                $newH1 = trim((string) (($content['h1'] ?? '') ?: $content['title']));
                if ($oldH1 !== $newH1) {
                    $diffs[] = 'H1 changed';
                }
                if ($item['source_path'] !== $content['path']) {
                    $diffs[] = 'URL changed';
                }
                $oldCanonicalPath = $item['canonical'] !== '' ? (string) parse_url((string) $item['canonical'], PHP_URL_PATH) : '';
                if ($oldCanonicalPath !== '' && CmsPath::normalize($oldCanonicalPath) !== CmsPath::normalize((string) parse_url($eff['canonical'], PHP_URL_PATH))) {
                    $diffs[] = 'Canonical changed';
                }
                $words = CmsHtml::wordCount((string) $content['body_html']);
                if ($item['word_count'] > 0 && $words < $item['word_count'] * 0.7) {
                    $diffs[] = 'Content is ' . round(100 - $words / $item['word_count'] * 100) . '% shorter';
                }
            }
            $out[] = ['item' => $item, 'content' => $content, 'effective' => $eff, 'diffs' => $diffs];
        }
        return $out;
    }

    /**
     * Pre- or post-launch report. Post-launch checks each old URL over HTTP.
     *
     * @return array{rows: list<array{path: string, expected: string, result: string, ok: bool}>, broken_links: list<array{from: string, to: string}>, sitemap: array{old_only: list<string>, new_only: list<string>}}
     */
    public function report(bool $live = false): array
    {
        $rows = [];
        $redirects = new CmsRedirectRepository();
        $repo = new CmsContentRepository();
        $base = CmsSiteConfig::baseUrl();
        foreach ($this->items() as $item) {
            if ($item['action'] === 'skip') {
                continue;
            }
            $source = (string) $item['source_path'];
            if ($source === '/') {
                $homeId = (int) CmsSiteConfig::value('home_content_id', 0);
                $home = $homeId ? $repo->find($homeId) : null;
                $cmsHome = $home && CmsContentRepository::isLive($home);
                $wanted = $item['action'] === 'imported' && $item['content_id'];
                if ($live) {
                    $res = self::fetch($base . '/');
                    $ok = $res['status'] === 200;
                    $result = (string) $res['status'] . ($res['error'] !== '' ? ' (' . $res['error'] . ')' : '');
                } else {
                    $ok = !$wanted || ($cmsHome && $homeId === (int) $item['content_id']);
                    $result = $cmsHome ? 'Homepage shows CMS page ' . $home['path'] : 'Hand-written homepage';
                    if ($wanted && !$ok) {
                        $result .= ' (publish the imported page, then choose “Use as homepage”)';
                    }
                }
                $rows[] = ['path' => '/', 'expected' => $wanted ? 'Homepage = ' . $item['target_path'] : '/', 'result' => $result, 'ok' => $ok];
                continue;
            }
            $expected = match ($item['action']) {
                'imported', 'redirect' => CmsPath::normalize((string) $item['target_path']),
                default => $source,
            };
            if ($live) {
                $res = self::fetch($base . ($source === '/' ? '/' : $source));
                $loc = $res['location'] !== '' ? (string) parse_url($res['location'], PHP_URL_PATH) : '';
                $ok = $expected === $source ? $res['status'] === 200 : (in_array($res['status'], [301, 308], true) && CmsPath::normalize($loc) === $expected);
                $result = $res['status'] . ($loc !== '' ? ' → ' . $loc : '') . ($res['error'] !== '' ? ' (' . $res['error'] . ')' : '');
            } else {
                if ($expected === $source) {
                    $ok = CmsRouter::exists($source) || $source === '/';
                    $result = $ok ? 'Resolves' : 'Would return 404';
                } else {
                    $trace = $redirects->trace($source);
                    $final = (string) $trace['final'];
                    $content = $repo->findByPath($expected);
                    $ok = !$trace['loop'] && $final === $expected && $content && CmsContentRepository::isLive($content);
                    $result = count($trace['hops']) > 1 ? 'Redirects ' . implode(' → ', $trace['hops']) : 'No redirect yet';
                    if ($content && !CmsContentRepository::isLive($content)) {
                        $result .= ' (new page not published)';
                    }
                }
            }
            $rows[] = ['path' => $source, 'expected' => $expected, 'result' => $result, 'ok' => $ok];
        }

        $broken = [];
        foreach ($this->items() as $item) {
            foreach ($item['links'] as $link) {
                if (!empty($link['internal']) && $link['path'] !== '' && $link['path'] !== '/' && !CmsRouter::exists($link['path'])) {
                    $file = PROJECT_ROOT . $link['path'];
                    if (!is_file($file) && !is_file(rtrim($file, '/') . '.php')) {
                        $broken[$item['source_path'] . '|' . $link['path']] = ['from' => $item['source_path'], 'to' => $link['path']];
                    }
                }
            }
        }

        $old = [];
        $oldFile = PROJECT_ROOT . '/sitemap.xml';
        if (is_file($oldFile) && preg_match_all('#<loc>([^<]+)</loc>#', (string) file_get_contents($oldFile), $m)) {
            foreach ($m[1] as $loc) {
                $old[CmsPath::normalize((string) parse_url(html_entity_decode($loc), PHP_URL_PATH))] = true;
            }
        }
        $new = [];
        foreach (CmsSitemap::entries() as $urls) {
            foreach ($urls as $u) {
                $new[$u['path']] = true;
            }
        }
        return [
            'rows' => $rows,
            'broken_links' => array_values($broken),
            'sitemap' => ['old_only' => array_keys(array_diff_key($old, $new)), 'new_only' => array_keys(array_diff_key($new, $old))],
        ];
    }

    /** Number of rows in the old Uplift blogs table that are not yet in the CMS. */
    public static function pendingBlogCount(): int
    {
        $db = Database::instance()->pdo();
        try {
            $total = (int) $db->query('SELECT COUNT(*) FROM blogs')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
        $done = (int) $db->query("SELECT COUNT(*) FROM cms_migration_items WHERE origin = 'blog'")->fetchColumn();
        return max(0, $total - $done);
    }

    /**
     * One-time import of the old Uplift blog posts into /blog articles. Re-running skips posts already imported.
     *
     * @return array{imported: int, skipped: int, images: int, errors: list<string>}
     */
    public function importBlogs(bool $downloadImages = true): array
    {
        $repo = new CmsContentRepository();
        $folders = new CmsFolderRepository();
        $folder = $folders->findByPrefix('/blog');
        if (!$folder) {
            $folderId = $folders->create(['parent_id' => null, 'name' => 'Blog', 'slug' => 'blog', 'url_prefix' => '/blog', 'default_type' => 'article']);
        } else {
            $folderId = (int) $folder['id'];
        }
        if (!$repo->findByPath('/blog')) {
            $indexId = $repo->create([
                'type' => 'page', 'folder_id' => null, 'internal_name' => 'Blog home', 'title' => 'Accounting & Tax Blog', 'h1' => 'Accounting & Tax Blog',
                'slug' => 'blog', 'path' => '/blog', 'body_html' => '<p>Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses.</p>',
                'body_mode' => 'sanitized', 'template' => 'article-index', 'status' => 'published', 'published_at' => gmdate('Y-m-d H:i:s'), 'faqs_json' => '[]',
                'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ]);
            $repo->saveSeo($indexId, [
                'seo_title' => 'Accounting & Tax Blog | Verma Accounting Ontario',
                'meta_description' => 'Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses from Verma Accounting.',
                'robots_index' => 'inherit', 'robots_follow' => 'inherit', 'sitemap_include' => 1, 'canonical_mode' => 'auto', 'schema_json' => '{"disabled":[]}',
            ]);
            $folders->update($folderId, ['landing_content_id' => $indexId]);
        }

        $media = new CmsMediaRepository();
        $service = new CmsContentService($repo);
        $imported = 0;
        $skipped = 0;
        $images = 0;
        $errors = [];
        $done = $this->db->query("SELECT source_path FROM cms_migration_items WHERE origin = 'blog'")->fetchAll(PDO::FETCH_COLUMN);
        $done = array_flip($done);
        foreach ($this->db->query('SELECT * FROM blogs ORDER BY publish_date, id')->fetchAll() as $post) {
            $slug = CmsPath::slugify((string) $post['slug']) ?: CmsPath::slugify((string) $post['title']);
            $path = '/blog/' . $slug;
            if (isset($done[$path]) || $repo->findByPath($path)) {
                $skipped++;
                continue;
            }
            $published = ($post['local_status'] ?? '') === 'published';
            $publishedAt = null;
            if (!empty($post['publish_date'])) {
                $local = trim($post['publish_date'] . ' ' . ($post['publish_time'] ?: '09:00:00'));
                $publishedAt = app_parse_local_datetime(substr($local, 0, 16)) ?: null;
            }
            $publishedAt = $publishedAt ? str_replace('T', ' ', substr($publishedAt, 0, 19)) : gmdate('Y-m-d H:i:s');

            $featuredId = null;
            $featuredUrl = trim((string) ($post['featured_image'] ?? ''));
            if ($featuredUrl !== '' && $downloadImages) {
                try {
                    $m = $media->importUrl($featuredUrl, (string) $post['title']);
                    $featuredId = (int) $m['id'];
                    $featuredUrl = '';
                    $images++;
                } catch (Throwable $e) {
                    $errors[] = $path . ': featured image kept as external URL (' . $e->getMessage() . ').';
                }
            }

            $pdo = $repo->pdo();
            $pdo->beginTransaction();
            try {
                $body = CmsHtml::sanitize((string) $post['content_html']);
                $id = $repo->create([
                    'type' => 'article', 'folder_id' => $folderId, 'internal_name' => '', 'title' => trim((string) $post['title']), 'h1' => '',
                    'slug' => $slug, 'path' => $path, 'body_html' => $body, 'body_mode' => 'sanitized',
                    'excerpt' => trim((string) ($post['excerpt'] ?? '')), 'featured_media_id' => $featuredId, 'featured_image_url' => $featuredUrl,
                    'featured_alt' => trim((string) $post['title']), 'author_name' => trim((string) ($post['author_name'] ?? '')),
                    'template' => 'article', 'status' => $published ? 'published' : 'draft', 'published_at' => $published ? $publishedAt : null,
                    'internal_notes' => 'Imported from the Uplift blog (id ' . $post['uplift_id'] . ').', 'faqs_json' => '[]',
                    'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
                ]);
                $repo->saveSeo($id, [
                    'seo_title' => trim((string) ($post['seo_title'] ?? '')) !== trim((string) $post['title']) ? trim((string) ($post['seo_title'] ?? '')) : '',
                    'meta_description' => trim((string) ($post['seo_description'] ?? '')),
                    'robots_index' => 'inherit', 'robots_follow' => 'inherit', 'sitemap_include' => 1, 'canonical_mode' => 'auto',
                    'og_type' => 'article', 'schema_json' => '{"disabled":[]}',
                ]);
                $termIds = [];
                foreach (blog_decode_json_list($post['categories_json'] ?? '[]') as $name) {
                    $termIds[] = $repo->ensureTerm('category', $name);
                }
                foreach (blog_decode_json_list($post['tags_json'] ?? '[]') as $name) {
                    $termIds[] = $repo->ensureTerm('tag', $name);
                }
                $repo->setContentTerms($id, array_unique(array_filter($termIds)));
                $service->indexLinks($id, $body);
                $repo->addRevision($id, $service->snapshot($id), 'Imported from Uplift blog');
                $now = now_iso();
                $this->db->prepare("INSERT INTO cms_migration_items (source_url, source_path, origin, http_status, title, description, canonical, robots, h1, body_html, links_json, images_json, word_count, content_type, folder_id, target_path, action, content_id, notes, crawled_at, created_at, updated_at)
                    VALUES (?, ?, 'blog', 200, ?, ?, '', '', '', '', '[]', '[]', ?, 'article', ?, ?, 'imported', ?, '', NULL, ?, ?)")
                    ->execute([CmsSiteConfig::baseUrl() . $path, $path, mb_substr((string) $post['title'], 0, 500), (string) ($post['seo_description'] ?? ''), CmsHtml::wordCount($body), $folderId, $path, $id, $now, $now]);
                $pdo->commit();
                $imported++;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = $path . ': ' . app_safe_error_message($e, 'import failed.');
            }
        }
        CmsCache::flush();
        ActivityLog::record('cms.blog_imported', 'cms_content', null, ['imported' => $imported, 'skipped' => $skipped]);
        return ['imported' => $imported, 'skipped' => $skipped, 'images' => $images, 'errors' => $errors];
    }

    /**
     * Roll back a migration: unpublish imported pages and remove migration redirects.
     *
     * @return array{unpublished: int, redirects_removed: int}
     */
    public function rollback(): array
    {
        $repo = new CmsContentRepository();
        $unpublished = 0;
        $pdo = $this->db;
        $pdo->beginTransaction();
        try {
            foreach ($this->items('imported', 100000) as $item) {
                if ($item['origin'] === 'blog') {
                    continue;
                }
                $content = $item['content_id'] ? $repo->find((int) $item['content_id']) : null;
                if ($content && in_array($content['status'], ['published', 'scheduled'], true)) {
                    $repo->update((int) $content['id'], ['status' => 'unpublished']);
                    $unpublished++;
                }
            }
            $removed = $pdo->exec("DELETE FROM cms_redirects WHERE origin = 'import'") ?: 0;
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        CmsCache::flush();
        ActivityLog::record('cms.migration_rolled_back', 'cms_content', null, ['unpublished' => $unpublished, 'redirects' => $removed]);
        return ['unpublished' => $unpublished, 'redirects_removed' => (int) $removed];
    }
}
