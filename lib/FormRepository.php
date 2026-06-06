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

    /** Published form linked from homepage hero and site header. */
    public function findSiteCtaForm(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM forms WHERE is_site_cta = 1 AND status = 'published' LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Mark one form as the site CTA; clears others. Pass null to clear all. */
    public function setSiteCta(?int $formId): void
    {
        $this->db->exec('UPDATE forms SET is_site_cta = 0');
        if ($formId !== null && $formId > 0) {
            $stmt = $this->db->prepare('UPDATE forms SET is_site_cta = 1 WHERE id = ?');
            $stmt->execute([$formId]);
        }
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

        $ctaLabel = array_key_exists('cta_label', $data)
            ? trim((string) $data['cta_label'])
            : trim((string) ($existing['cta_label'] ?? ''));

        $stmt = $this->db->prepare('
            UPDATE forms SET slug = ?, title = ?, description = ?, schema_json = ?, status = ?, cta_label = ?, updated_at = ?
            WHERE id = ?
        ');
        $ok = $stmt->execute([
            $data['slug'] ?? $existing['slug'],
            $data['title'] ?? $existing['title'],
            $data['description'] ?? $existing['description'],
            json_encode($schema, JSON_UNESCAPED_UNICODE),
            $data['status'] ?? $existing['status'],
            $ctaLabel !== '' ? $ctaLabel : null,
            now_iso(),
            $id,
        ]);

        if ($ok && array_key_exists('is_site_cta', $data)) {
            $wantCta = !empty($data['is_site_cta']) && ($data['status'] ?? $existing['status']) === 'published';
            $this->setSiteCta($wantCta ? $id : null);
        }

        return $ok;
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

    public function saveSubmission(int $formId, array $data, array $filesMeta = [], ?int $taxYear = null): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO submissions (form_id, data_json, status, tax_year, ip, user_agent, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $formId,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            'pending',
            $taxYear,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $now,
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

    public function submissionsForForm(int $formId, ?string $status = null, ?int $taxYear = null): array
    {
        $sql = 'SELECT * FROM submissions WHERE form_id = ?';
        $params = [$formId];
        if ($status === 'pending' || $status === 'complete') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        if ($taxYear !== null) {
            $sql .= ' AND tax_year = ?';
            $params[] = $taxYear;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find the most recent submission where all match criteria equal stored values.
     *
     * @param array<string, string> $matchCriteria field name => value
     */
    public function findSubmissionByMatch(int $formId, array $matchCriteria, ?int $taxYear = null): ?array
    {
        if (count($matchCriteria) < 2) {
            return null;
        }

        $submissions = $this->submissionsForForm($formId, null, $taxYear);
        foreach ($submissions as $sub) {
            $data = json_decode($sub['data_json'], true) ?: [];
            $matches = true;
            foreach ($matchCriteria as $name => $expected) {
                $actual = $data[$name] ?? '';
                if (is_array($actual)) {
                    $actual = implode(',', $actual);
                }
                if (strcasecmp(trim((string) $actual), trim((string) $expected)) !== 0) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $sub;
            }
        }

        return null;
    }

    public function submissionStatusCounts(int $formId, ?int $taxYear = null): array
    {
        $sql = '
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = \'complete\' THEN 1 ELSE 0 END) AS complete
            FROM submissions WHERE form_id = ?
        ';
        $params = [$formId];
        if ($taxYear !== null) {
            $sql .= ' AND tax_year = ?';
            $params[] = $taxYear;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->mapSubmissionCountRow($stmt->fetch() ?: []);
    }

    /** @return list<int> */
    public function submissionTaxYearsForForm(int $formId): array
    {
        $stmt = $this->db->prepare('
            SELECT DISTINCT tax_year FROM submissions
            WHERE form_id = ? AND tax_year IS NOT NULL
            ORDER BY tax_year DESC
        ');
        $stmt->execute([$formId]);
        $years = [];
        foreach ($stmt->fetchAll() as $row) {
            $years[] = (int) $row['tax_year'];
        }
        return $years;
    }

    /** @return array{all: int, pending: int, complete: int} */
    public function globalSubmissionCounts(): array
    {
        $stmt = $this->db->query('
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = \'complete\' THEN 1 ELSE 0 END) AS complete
            FROM submissions
        ');
        return $this->mapSubmissionCountRow($stmt->fetch() ?: []);
    }

    /** @return array<int, array{all: int, pending: int, complete: int}> */
    public function submissionCountsByFormId(): array
    {
        $stmt = $this->db->query('
            SELECT form_id,
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = \'complete\' THEN 1 ELSE 0 END) AS complete
            FROM submissions
            GROUP BY form_id
        ');
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['form_id']] = $this->mapSubmissionCountRow($row);
        }
        return $map;
    }

    public function recentSubmissions(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->query('
            SELECT s.id, s.form_id, s.status, s.created_at, s.updated_at,
                   f.title AS form_title, f.slug AS form_slug
            FROM submissions s
            INNER JOIN forms f ON f.id = s.form_id
            ORDER BY s.created_at DESC
            LIMIT ' . $limit
        );
        return $stmt->fetchAll();
    }

    /** @return array{all: int, pending: int, complete: int} */
    private function mapSubmissionCountRow(array $row): array
    {
        return [
            'all' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'complete' => (int) ($row['complete'] ?? 0),
        ];
    }

    public function findSubmission(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM submissions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findSubmissionForForm(int $submissionId, int $formId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM submissions WHERE id = ? AND form_id = ?');
        $stmt->execute([$submissionId, $formId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setSubmissionStatus(int $submissionId, string $status): bool
    {
        if (!in_array($status, ['pending', 'complete'], true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE submissions SET status = ?, updated_at = ? WHERE id = ?');
        return $stmt->execute([$status, now_iso(), $submissionId]);
    }

    public function updateSubmissionData(int $submissionId, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE submissions SET data_json = ?, updated_at = ? WHERE id = ?');
        return $stmt->execute([
            json_encode($data, JSON_UNESCAPED_UNICODE),
            now_iso(),
            $submissionId,
        ]);
    }

    public function addSubmissionFile(int $submissionId, array $fileMeta): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO submission_files (submission_id, field_id, stored_name, original_name, mime, size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $submissionId,
            $fileMeta['field_id'],
            $fileMeta['stored_name'],
            $fileMeta['original_name'],
            $fileMeta['mime'],
            $fileMeta['size'],
            now_iso(),
        ]);
    }

    public function deleteFilesForField(int $submissionId, string $fieldId): void
    {
        $stmt = $this->db->prepare('SELECT * FROM submission_files WHERE submission_id = ? AND field_id = ?');
        $stmt->execute([$submissionId, $fieldId]);
        foreach ($stmt->fetchAll() as $file) {
            $path = UPLOADS_DIR . '/' . $file['stored_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $del = $this->db->prepare('DELETE FROM submission_files WHERE submission_id = ? AND field_id = ?');
        $del->execute([$submissionId, $fieldId]);
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
