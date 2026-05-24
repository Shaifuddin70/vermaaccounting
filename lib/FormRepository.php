<?php

declare(strict_types=1);

final class FormRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM forms ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM forms WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = 'SELECT * FROM forms WHERE slug = ?';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $now = now_iso();
        $schema = normalize_form_schema($data['schema'] ?? ['fields' => []]);
        $stmt = $this->db->prepare('
            INSERT INTO forms (slug, title, description, schema_json, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['slug'],
            $data['title'],
            $data['description'] ?? '',
            json_encode($schema, JSON_UNESCAPED_UNICODE),
            $data['status'] ?? 'draft',
            $now,
            $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        $schema = isset($data['schema'])
            ? normalize_form_schema($data['schema'])
            : json_decode($existing['schema_json'], true);

        $stmt = $this->db->prepare('
            UPDATE forms SET slug = ?, title = ?, description = ?, schema_json = ?, status = ?, updated_at = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['slug'] ?? $existing['slug'],
            $data['title'] ?? $existing['title'],
            $data['description'] ?? $existing['description'],
            json_encode($schema, JSON_UNESCAPED_UNICODE),
            $data['status'] ?? $existing['status'],
            now_iso(),
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM forms WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function decodeSchema(array $form): array
    {
        $schema = json_decode($form['schema_json'] ?? '{}', true);
        return is_array($schema) ? normalize_form_schema($schema) : normalize_form_schema([]);
    }

    public function saveSubmission(int $formId, array $data, array $filesMeta = []): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO submissions (form_id, data_json, ip, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $formId,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $now,
        ]);
        $submissionId = (int) $this->db->lastInsertId();

        foreach ($filesMeta as $file) {
            $fstmt = $this->db->prepare('
                INSERT INTO submission_files (submission_id, field_id, stored_name, original_name, mime, size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $fstmt->execute([
                $submissionId,
                $file['field_id'],
                $file['stored_name'],
                $file['original_name'],
                $file['mime'],
                $file['size'],
                $now,
            ]);
        }

        return $submissionId;
    }

    public function submissionsForForm(int $formId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM submissions WHERE form_id = ? ORDER BY created_at DESC');
        $stmt->execute([$formId]);
        return $stmt->fetchAll();
    }

    public function filesForSubmission(int $submissionId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM submission_files WHERE submission_id = ?');
        $stmt->execute([$submissionId]);
        return $stmt->fetchAll();
    }

    public function findSubmissionFile(int $fileId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT sf.*, s.form_id, s.id AS submission_id
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            WHERE sf.id = ?
        ');
        $stmt->execute([$fileId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function filesForForm(int $formId): array
    {
        $stmt = $this->db->prepare('
            SELECT sf.*, s.id AS submission_id, s.created_at AS submitted_at
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            WHERE s.form_id = ?
            ORDER BY s.created_at DESC, sf.id ASC
        ');
        $stmt->execute([$formId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, list<array>> */
    public function filesGroupedBySubmission(int $formId): array
    {
        $grouped = [];
        foreach ($this->filesForForm($formId) as $file) {
            $sid = (int) $file['submission_id'];
            $grouped[$sid][] = $file;
        }
        return $grouped;
    }

    public function publishedForms(): array
    {
        $stmt = $this->db->query("SELECT id, slug, title FROM forms WHERE status = 'published' ORDER BY title ASC");
        return $stmt->fetchAll();
    }

    public function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = slugify($base);
        $candidate = $slug;
        $i = 1;
        while ($this->slugExists($candidate, $excludeId)) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM forms WHERE slug = ? AND id != ?');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM forms WHERE slug = ?');
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetch();
    }
}
