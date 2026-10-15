<?php

declare(strict_types=1);

final class CmsContentRepository
{
    public const TYPES = ['page' => 'Page', 'article' => 'Article', 'city' => 'City page'];
    public const STATUSES = [
        'draft' => 'Draft',
        'review' => 'In review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'unpublished' => 'Unpublished',
        'archived' => 'Archived',
    ];

    private const CONTENT_COLUMNS = [
        'type', 'folder_id', 'internal_name', 'title', 'h1', 'slug', 'path', 'body_html', 'body_mode',
        'excerpt', 'featured_media_id', 'featured_image_url', 'featured_alt', 'author_user_id', 'author_name',
        'template', 'language', 'status', 'publish_at', 'expire_at', 'published_at', 'preview_token',
        'internal_notes', 'faqs_json', 'sort_order', 'created_by', 'updated_by',
    ];

    private const SEO_COLUMNS = [
        'seo_title', 'meta_description', 'robots_index', 'robots_follow', 'max_snippet', 'max_image_preview',
        'max_video_preview', 'robots_extra', 'sitemap_include', 'canonical_mode', 'canonical_url', 'og_title',
        'og_description', 'og_image_id', 'og_image_url', 'og_image_alt', 'og_type', 'twitter_card', 'schema_json',
    ];

    private const CITY_COLUMNS = [
        'city', 'region', 'country', 'primary_service', 'related_services_json', 'phone', 'email', 'address',
        'hours_json', 'map_url', 'map_embed_url', 'nearby_json', 'cta_json', 'local_business',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function pdo(): PDO
    {
        return $this->db;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_contents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPath(string $path): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_contents WHERE path = ?');
        $stmt->execute([$path]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Which of these paths belong to CMS content that is not live (draft, scheduled, expired, archived…).
     *
     * @param list<string> $paths
     * @return array<string, true>
     */
    public function notLivePaths(array $paths): array
    {
        $paths = array_values(array_unique($paths));
        if ($paths === []) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT path, status, expire_at FROM cms_contents WHERE path IN (' . implode(',', array_fill(0, count($paths), '?')) . ')');
        $stmt->execute($paths);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!self::isLive($row)) {
                $out[(string) $row['path']] = true;
            }
        }
        return $out;
    }

    public function findByPreviewToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM cms_contents WHERE preview_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** Published and not expired at the given UTC time. */
    public static function isLive(array $content, ?string $nowUtc = null): bool
    {
        $now = $nowUtc ?? gmdate('Y-m-d H:i:s');
        if (($content['status'] ?? '') !== 'published') {
            return false;
        }
        $expire = (string) ($content['expire_at'] ?? '');
        return $expire === '' || $expire > $now;
    }

    public function pathTaken(string $path, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM cms_contents WHERE path = ?';
        $params = [$path];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{type?: string, status?: string, folder_id?: int|null, q?: string, sort?: string, dir?: string, term_id?: int} $filters
     * @return list<array>
     */
    public function search(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);
        $sorts = [
            'updated' => 'c.updated_at', 'title' => 'c.title', 'path' => 'c.path', 'status' => 'c.status',
            'published' => 'c.published_at', 'type' => 'c.type', 'order' => 'c.sort_order', 'publish_at' => 'c.publish_at',
        ];
        $sort = $sorts[$filters['sort'] ?? 'updated'] ?? 'c.updated_at';
        $dir = strtolower((string) ($filters['dir'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT c.*, s.seo_title, s.meta_description, s.robots_index, s.canonical_mode
                FROM cms_contents c LEFT JOIN cms_seo s ON s.content_id = c.id'
            . $where . ' ORDER BY ' . $sort . ' ' . $dir . ', c.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->filterSql($filters);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM cms_contents c' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, int> status => count */
    public function statusCounts(?string $type = null): array
    {
        $sql = 'SELECT status, COUNT(*) n FROM cms_contents';
        $params = [];
        if ($type !== null) {
            $sql .= ' WHERE type = ?';
            $params[] = $type;
        }
        $stmt = $this->db->prepare($sql . ' GROUP BY status');
        $stmt->execute($params);
        $out = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterSql(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['type']) && isset(self::TYPES[$filters['type']])) {
            $where[] = 'c.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['status']) && isset(self::STATUSES[$filters['status']])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_archived'])) {
            $where[] = "c.status <> 'archived'";
        }
        if (array_key_exists('folder_id', $filters) && $filters['folder_id'] !== null && $filters['folder_id'] !== '') {
            if ((int) $filters['folder_id'] === 0) {
                $where[] = 'c.folder_id IS NULL';
            } else {
                $where[] = 'c.folder_id = ?';
                $params[] = (int) $filters['folder_id'];
            }
        }
        if (!empty($filters['term_id'])) {
            $where[] = 'c.id IN (SELECT content_id FROM cms_content_terms WHERE term_id = ?)';
            $params[] = (int) $filters['term_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $where[] = '(c.title LIKE ? OR c.internal_name LIKE ? OR c.path LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }

    /** @return list<array> live published content, newest first */
    public function published(?string $type = null, int $limit = 100, int $offset = 0, ?int $folderId = null): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM cms_contents WHERE status = 'published' AND (expire_at IS NULL OR expire_at > ?)";
        $params = [$now];
        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $params[] = $folderId;
        }
        $sql .= ' ORDER BY published_at DESC, id DESC LIMIT ' . max(1, min(5000, $limit)) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /** @return list<array{path: string, name: string, region: string}> live, indexable city pages, A–Z by city name */
    public function serviceAreas(): array
    {
        $stmt = $this->db->prepare("SELECT c.path, c.title, d.city, d.region FROM cms_contents c LEFT JOIN cms_city_data d ON d.content_id = c.id
            LEFT JOIN cms_seo s ON s.content_id = c.id
            WHERE c.type = 'city' AND c.status = 'published' AND (c.expire_at IS NULL OR c.expire_at > ?) AND COALESCE(s.robots_index, 'inherit') <> 'noindex'");
        $stmt->execute([gmdate('Y-m-d H:i:s')]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['city'] ?? '')) ?: trim((string) $row['title']);
            if ($name !== '') {
                $out[] = ['path' => (string) $row['path'], 'name' => $name, 'region' => trim((string) ($row['region'] ?? ''))];
            }
        }
        usort($out, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public function countPublished(?string $type = null, ?int $folderId = null): int
    {
        $sql = "SELECT COUNT(*) FROM cms_contents WHERE status = 'published' AND (expire_at IS NULL OR expire_at > ?)";
        $params = [gmdate('Y-m-d H:i:s')];
        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        if ($folderId !== null) {
            $sql .= ' AND folder_id = ?';
            $params[] = $folderId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array> every content row (no body), for audits and link checks */
    public function allLight(): array
    {
        return $this->db->query('SELECT c.id, c.type, c.title, c.h1, c.path, c.status, c.expire_at, c.updated_at, c.published_at, c.folder_id, c.excerpt,
                s.seo_title, s.meta_description, s.robots_index, s.robots_follow, s.canonical_mode, s.canonical_url, s.sitemap_include
            FROM cms_contents c LEFT JOIN cms_seo s ON s.content_id = c.id ORDER BY c.path')->fetchAll();
    }

    public function create(array $data): int
    {
        $now = now_iso();
        $row = $this->onlyColumns($data, self::CONTENT_COLUMNS);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $cols = array_keys($row);
        $stmt = $this->db->prepare('INSERT INTO cms_contents (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        $stmt->execute(array_values($row));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $row = $this->onlyColumns($data, self::CONTENT_COLUMNS);
        if ($row === []) {
            return;
        }
        $row['updated_at'] = now_iso();
        $sets = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($row)));
        $stmt = $this->db->prepare('UPDATE cms_contents SET ' . $sets . ' WHERE id = ?');
        $stmt->execute([...array_values($row), $id]);
    }

    public function delete(int $id): void
    {
        foreach (['cms_seo', 'cms_city_data', 'cms_content_terms', 'cms_revisions', 'cms_content_links'] as $table) {
            $this->db->prepare('DELETE FROM ' . $table . ' WHERE content_id = ?')->execute([$id]);
        }
        $this->db->prepare('UPDATE cms_folders SET landing_content_id = NULL WHERE landing_content_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM cms_contents WHERE id = ?')->execute([$id]);
    }

    public function seo(int $contentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_seo WHERE content_id = ?');
        $stmt->execute([$contentId]);
        $row = $stmt->fetch();
        if (!$row) {
            return self::defaultSeo();
        }
        $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        return $row;
    }

    public static function defaultSeo(): array
    {
        return [
            'seo_title' => '', 'meta_description' => '', 'robots_index' => 'inherit', 'robots_follow' => 'inherit',
            'max_snippet' => null, 'max_image_preview' => '', 'max_video_preview' => null, 'robots_extra' => '',
            'sitemap_include' => 1, 'canonical_mode' => 'auto', 'canonical_url' => '', 'og_title' => '',
            'og_description' => '', 'og_image_id' => null, 'og_image_url' => '', 'og_image_alt' => '', 'og_type' => '',
            'twitter_card' => '', 'schema_json' => '', 'schema' => [],
        ];
    }

    public function saveSeo(int $contentId, array $data): void
    {
        $row = $this->onlyColumns($data, self::SEO_COLUMNS);
        $row['updated_at'] = now_iso();
        $this->upsert('cms_seo', 'content_id', $contentId, $row);
    }

    public function city(int $contentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_city_data WHERE content_id = ?');
        $stmt->execute([$contentId]);
        $row = $stmt->fetch() ?: [];
        $out = array_fill_keys(self::CITY_COLUMNS, '');
        $out = array_merge($out, $row);
        foreach (['related_services', 'hours', 'nearby', 'cta'] as $key) {
            $out[$key] = json_decode((string) ($out[$key . '_json'] ?? ''), true) ?: [];
        }
        $out['local_business'] = (int) ($out['local_business'] ?: 0);
        return $out;
    }

    public function saveCity(int $contentId, array $data): void
    {
        $this->upsert('cms_city_data', 'content_id', $contentId, $this->onlyColumns($data, self::CITY_COLUMNS));
    }

    private function upsert(string $table, string $key, int $id, array $row): void
    {
        $exists = $this->db->prepare('SELECT 1 FROM ' . $table . ' WHERE ' . $key . ' = ?');
        $exists->execute([$id]);
        if ($exists->fetchColumn()) {
            if ($row === []) {
                return;
            }
            $sets = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($row)));
            $this->db->prepare('UPDATE ' . $table . ' SET ' . $sets . ' WHERE ' . $key . ' = ?')->execute([...array_values($row), $id]);
            return;
        }
        $row = [$key => $id] + $row;
        $cols = array_keys($row);
        $this->db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($row));
    }

    private function onlyColumns(array $data, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $value = $data[$col];
                $out[$col] = is_bool($value) ? (int) $value : $value;
            }
        }
        return $out;
    }

    // ---- Revisions -------------------------------------------------------

    public function addRevision(int $contentId, array $snapshot, string $comment = '', bool $autosave = false): int
    {
        $user = Auth::currentUser();
        if ($autosave) {
            // Keep one rolling autosave per content so history stays readable.
            $this->db->prepare('DELETE FROM cms_revisions WHERE content_id = ? AND is_autosave = 1')->execute([$contentId]);
        }
        $stmt = $this->db->prepare('INSERT INTO cms_revisions (content_id, snapshot_json, comment, is_autosave, user_id, user_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $contentId,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            mb_substr($comment, 0, 255),
            $autosave ? 1 : 0,
            Auth::userId(),
            (string) ($user['name'] ?? ''),
            now_iso(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return list<array> */
    public function revisions(int $contentId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT id, content_id, comment, is_autosave, user_name, created_at FROM cms_revisions WHERE content_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$contentId]);
        return $stmt->fetchAll();
    }

    public function revision(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_revisions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['snapshot'] = json_decode((string) $row['snapshot_json'], true) ?: [];
        return $row;
    }

    public function pruneRevisions(int $contentId, int $keep = 60): void
    {
        $stmt = $this->db->prepare('SELECT id FROM cms_revisions WHERE content_id = ? AND is_autosave = 0 ORDER BY id DESC LIMIT 1 OFFSET ' . max(1, $keep));
        $stmt->execute([$contentId]);
        $cutoff = $stmt->fetchColumn();
        if ($cutoff) {
            $this->db->prepare('DELETE FROM cms_revisions WHERE content_id = ? AND is_autosave = 0 AND id <= ?')->execute([$contentId, (int) $cutoff]);
        }
    }

    // ---- URL history -----------------------------------------------------

    public function recordUrlChange(int $contentId, string $old, string $new): void
    {
        $this->db->prepare('INSERT INTO cms_url_history (content_id, old_path, new_path, user_id, changed_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$contentId, $old, $new, Auth::userId(), now_iso()]);
    }

    /** @return list<array> */
    public function urlHistory(int $contentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_url_history WHERE content_id = ? ORDER BY id DESC');
        $stmt->execute([$contentId]);
        return $stmt->fetchAll();
    }

    /** Newest content whose URL used to be this path, for redirect suggestions. */
    public function findByOldPath(string $path): ?array
    {
        $stmt = $this->db->prepare('SELECT content_id FROM cms_url_history WHERE old_path = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$path]);
        $id = $stmt->fetchColumn();
        return $id ? $this->find((int) $id) : null;
    }

    // ---- Terms -----------------------------------------------------------

    /** @return list<array> */
    public function terms(?string $kind = null): array
    {
        if ($kind !== null) {
            $stmt = $this->db->prepare('SELECT t.*, (SELECT COUNT(*) FROM cms_content_terms ct WHERE ct.term_id = t.id) AS uses FROM cms_terms t WHERE kind = ? ORDER BY name');
            $stmt->execute([$kind]);
            return $stmt->fetchAll();
        }
        return $this->db->query('SELECT t.*, (SELECT COUNT(*) FROM cms_content_terms ct WHERE ct.term_id = t.id) AS uses FROM cms_terms t ORDER BY kind, name')->fetchAll();
    }

    public function findTerm(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_terms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findTermBySlug(string $kind, string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_terms WHERE kind = ? AND slug = ?');
        $stmt->execute([$kind, $slug]);
        return $stmt->fetch() ?: null;
    }

    public function ensureTerm(string $kind, string $name): int
    {
        $name = trim($name);
        $slug = CmsPath::slugify($name);
        if ($slug === '') {
            throw new InvalidArgumentException('Term name is empty.');
        }
        $existing = $this->findTermBySlug($kind, $slug);
        if ($existing) {
            return (int) $existing['id'];
        }
        $this->db->prepare('INSERT INTO cms_terms (kind, name, slug, description, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$kind, mb_substr($name, 0, 191), $slug, '', now_iso()]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTerm(int $id, string $name, string $description): void
    {
        $this->db->prepare('UPDATE cms_terms SET name = ?, description = ? WHERE id = ?')->execute([mb_substr(trim($name), 0, 191), $description, $id]);
    }

    public function deleteTerm(int $id): void
    {
        $this->db->prepare('DELETE FROM cms_content_terms WHERE term_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM cms_terms WHERE id = ?')->execute([$id]);
    }

    /** @return list<array{id: int, kind: string, name: string, slug: string}> */
    public function contentTerms(int $contentId): array
    {
        $stmt = $this->db->prepare('SELECT t.id, t.kind, t.name, t.slug FROM cms_terms t JOIN cms_content_terms ct ON ct.term_id = t.id WHERE ct.content_id = ? ORDER BY t.kind, t.name');
        $stmt->execute([$contentId]);
        return $stmt->fetchAll();
    }

    /** @param list<int> $termIds */
    public function setContentTerms(int $contentId, array $termIds): void
    {
        $this->db->prepare('DELETE FROM cms_content_terms WHERE content_id = ?')->execute([$contentId]);
        $stmt = $this->db->prepare('INSERT INTO cms_content_terms (content_id, term_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $termIds)) as $termId) {
            if ($termId > 0) {
                $stmt->execute([$contentId, $termId]);
            }
        }
    }

    /** @return list<array> live articles sharing categories/tags with this one */
    public function related(int $contentId, int $limit = 3): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT c.*, COUNT(*) AS shared FROM cms_contents c
            JOIN cms_content_terms ct ON ct.content_id = c.id
            WHERE ct.term_id IN (SELECT term_id FROM cms_content_terms WHERE content_id = ?)
              AND c.id <> ? AND c.type = 'article' AND c.status = 'published' AND (c.expire_at IS NULL OR c.expire_at > ?)
            GROUP BY c.id ORDER BY shared DESC, c.published_at DESC LIMIT " . max(1, $limit));
        $stmt->execute([$contentId, $contentId, $now]);
        $rows = array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
        if (count($rows) < $limit) {
            $ids = array_merge([$contentId], array_map(static fn ($r) => (int) $r['id'], $rows));
            foreach ($this->published('article', $limit + count($ids)) as $extra) {
                if (count($rows) >= $limit) {
                    break;
                }
                if (!in_array((int) $extra['id'], $ids, true)) {
                    $rows[] = $extra;
                }
            }
        }
        return $rows;
    }

    // ---- Scheduling ------------------------------------------------------

    /** @return list<array> scheduled items whose publish time has passed */
    public function duePublications(string $nowUtc): array
    {
        $stmt = $this->db->prepare("SELECT * FROM cms_contents WHERE status = 'scheduled' AND publish_at IS NOT NULL AND publish_at <= ?");
        $stmt->execute([$nowUtc]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /** @return list<array> published items whose expiry has passed */
    public function dueExpiries(string $nowUtc): array
    {
        $stmt = $this->db->prepare("SELECT * FROM cms_contents WHERE status = 'published' AND expire_at IS NOT NULL AND expire_at <= ?");
        $stmt->execute([$nowUtc]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['folder_id'] = $row['folder_id'] !== null ? (int) $row['folder_id'] : null;
        $row['faqs'] = json_decode((string) ($row['faqs_json'] ?? ''), true) ?: [];
        return $row;
    }
}
