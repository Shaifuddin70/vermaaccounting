<?php

declare(strict_types=1);

final class BlogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /** @return list<array<string, mixed>> */
    public function all(?string $localStatus = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT * FROM blogs';
        $params = [];
        if ($localStatus !== null && $localStatus !== '') {
            $sql .= ' WHERE local_status = ?';
            $params[] = $localStatus;
        }
        $sql .= ' ORDER BY COALESCE(publish_date, created_at) DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public function published(int $limit = 50, int $offset = 0): array
    {
        return $this->all('published', $limit, $offset);
    }

    public function count(?string $localStatus = null): int
    {
        if ($localStatus !== null && $localStatus !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM blogs WHERE local_status = ?');
            $stmt->execute([$localStatus]);
            return (int) $stmt->fetchColumn();
        }
        return (int) $this->db->query('SELECT COUNT(*) FROM blogs')->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blogs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = false): ?array
    {
        $sql = 'SELECT * FROM blogs WHERE slug = ?';
        $params = [trim($slug)];
        if ($publishedOnly) {
            $sql .= ' AND local_status = ?';
            $params[] = 'published';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUpliftId(string $upliftId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blogs WHERE uplift_id = ? LIMIT 1');
        $stmt->execute([$upliftId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Upsert a remote Uplift blog payload.
     *
     * @param array<string, mixed> $remote
     * @return array{id: int, created: bool, updated: bool}
     */
    public function upsertFromUplift(array $remote): array
    {
        $upliftId = trim((string) ($remote['id'] ?? ''));
        if ($upliftId === '') {
            throw new InvalidArgumentException('Uplift blog is missing id.');
        }

        $slug = blog_normalize_slug((string) ($remote['slug'] ?? ''), (string) ($remote['title'] ?? 'post'));
        $slug = $this->uniqueSlug($slug, $upliftId);

        $sourceStatus = strtoupper((string) ($remote['status'] ?? 'DRAFT'));
        if (!in_array($sourceStatus, ['PUBLISH', 'DRAFT'], true)) {
            $sourceStatus = 'DRAFT';
        }

        $meta = is_array($remote['meta'] ?? null) ? $remote['meta'] : [];
        $categories = is_array($remote['categories'] ?? null) ? $remote['categories'] : [];
        $tags = is_array($remote['tags'] ?? null) ? $remote['tags'] : [];
        $freshness = is_array($remote['freshness'] ?? null) ? $remote['freshness'] : [];
        $customFields = is_array($remote['customFields'] ?? null) ? $remote['customFields'] : [];

        $payload = [
            'uplift_id' => $upliftId,
            'title' => trim((string) ($remote['title'] ?? 'Untitled')),
            'slug' => $slug,
            'excerpt' => (string) ($remote['excerpt'] ?? ''),
            'content_html' => (string) ($remote['content'] ?? ''),
            'featured_image' => (string) ($remote['featuredImage'] ?? ''),
            'source_status' => $sourceStatus,
            'publish_date' => blog_normalize_date($remote['publishDate'] ?? null),
            'publish_time' => blog_normalize_time($remote['publishTime'] ?? null),
            'author_name' => (string) ($remote['authorName'] ?? ''),
            'author_url' => (string) ($remote['authorUrl'] ?? ''),
            'seo_title' => (string) ($meta['seoTitle'] ?? $remote['title'] ?? ''),
            'seo_description' => (string) ($meta['seoDescription'] ?? $remote['excerpt'] ?? ''),
            'focus_keyword' => (string) ($meta['focusKeyword'] ?? ''),
            'seo_score' => (int) ($remote['seoScore'] ?? 0),
            'categories_json' => json_encode(array_values($categories), JSON_UNESCAPED_UNICODE),
            'tags_json' => json_encode(array_values($tags), JSON_UNESCAPED_UNICODE),
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'freshness_json' => json_encode($freshness, JSON_UNESCAPED_UNICODE),
            'custom_fields_json' => json_encode($customFields, JSON_UNESCAPED_UNICODE),
            'source_updated_at' => (string) ($remote['updatedAt'] ?? ($freshness['lastUpdatedAt'] ?? '')),
        ];

        $existing = $this->findByUpliftId($upliftId);
        $now = now_iso();

        if ($existing === null) {
            $localStatus = $sourceStatus === 'PUBLISH' ? 'published' : 'draft';
            $stmt = $this->db->prepare('
                INSERT INTO blogs (
                    uplift_id, title, slug, excerpt, content_html, featured_image,
                    source_status, local_status, publish_date, publish_time,
                    author_name, author_url, seo_title, seo_description, focus_keyword, seo_score,
                    categories_json, tags_json, meta_json, freshness_json, custom_fields_json,
                    source_updated_at, last_synced_at, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                )
            ');
            $stmt->execute([
                $payload['uplift_id'],
                $payload['title'],
                $payload['slug'],
                $payload['excerpt'],
                $payload['content_html'],
                $payload['featured_image'],
                $payload['source_status'],
                $localStatus,
                $payload['publish_date'],
                $payload['publish_time'],
                $payload['author_name'],
                $payload['author_url'],
                $payload['seo_title'],
                $payload['seo_description'],
                $payload['focus_keyword'],
                $payload['seo_score'],
                $payload['categories_json'],
                $payload['tags_json'],
                $payload['meta_json'],
                $payload['freshness_json'],
                $payload['custom_fields_json'],
                $payload['source_updated_at'] !== '' ? $payload['source_updated_at'] : null,
                $now,
                $now,
                $now,
            ]);
            return ['id' => (int) $this->db->lastInsertId(), 'created' => true, 'updated' => false];
        }

        $stmt = $this->db->prepare('
            UPDATE blogs SET
                title = ?, slug = ?, excerpt = ?, content_html = ?, featured_image = ?,
                source_status = ?, publish_date = ?, publish_time = ?,
                author_name = ?, author_url = ?, seo_title = ?, seo_description = ?,
                focus_keyword = ?, seo_score = ?,
                categories_json = ?, tags_json = ?, meta_json = ?, freshness_json = ?, custom_fields_json = ?,
                source_updated_at = ?, last_synced_at = ?, updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $payload['title'],
            $payload['slug'],
            $payload['excerpt'],
            $payload['content_html'],
            $payload['featured_image'],
            $payload['source_status'],
            $payload['publish_date'],
            $payload['publish_time'],
            $payload['author_name'],
            $payload['author_url'],
            $payload['seo_title'],
            $payload['seo_description'],
            $payload['focus_keyword'],
            $payload['seo_score'],
            $payload['categories_json'],
            $payload['tags_json'],
            $payload['meta_json'],
            $payload['freshness_json'],
            $payload['custom_fields_json'],
            $payload['source_updated_at'] !== '' ? $payload['source_updated_at'] : null,
            $now,
            $now,
            (int) $existing['id'],
        ]);

        return ['id' => (int) $existing['id'], 'created' => false, 'updated' => true];
    }

    public function setLocalStatus(int $id, string $status): void
    {
        $status = $status === 'published' ? 'published' : 'draft';
        $stmt = $this->db->prepare('UPDATE blogs SET local_status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, now_iso(), $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM blogs WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function uniqueSlug(string $slug, string $upliftId): string
    {
        $base = $slug !== '' ? $slug : 'post';
        $candidate = $base;
        $i = 2;
        while (true) {
            $stmt = $this->db->prepare('SELECT uplift_id FROM blogs WHERE slug = ? LIMIT 1');
            $stmt->execute([$candidate]);
            $row = $stmt->fetch();
            if (!$row || (string) $row['uplift_id'] === $upliftId) {
                return $candidate;
            }
            $candidate = $base . '-' . $i;
            $i++;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        $row['categories'] = blog_decode_json_list($row['categories_json'] ?? '[]');
        $row['tags'] = blog_decode_json_list($row['tags_json'] ?? '[]');
        $row['meta'] = blog_decode_json_object($row['meta_json'] ?? '{}');
        $row['freshness'] = blog_decode_json_object($row['freshness_json'] ?? '{}');
        $row['custom_fields'] = blog_decode_json_object($row['custom_fields_json'] ?? '{}');
        return $row;
    }
}
