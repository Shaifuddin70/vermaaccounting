<?php

declare(strict_types=1);

final class UploadRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /**
     * @return array{used_bytes: int, db_bytes: int, file_count: int, quota_bytes: int, available_bytes: int, disk_free_bytes: int|null, percent_used: float}
     */
    public function storageStats(): array
    {
        $quota = uploads_quota_bytes();
        $dbBytes = $this->totalBytesInDatabase();
        $physical = $this->physicalUploadsBytes();
        $used = max($dbBytes, $physical);
        $diskFree = $this->diskFreeBytes();

        $available = max(0, $quota - $used);
        if ($diskFree !== null) {
            $available = min($available, $diskFree);
        }

        $countStmt = $this->db->query('SELECT COUNT(*) FROM submission_files');
        $fileCount = (int) $countStmt->fetchColumn();

        return [
            'used_bytes' => $used,
            'db_bytes' => $dbBytes,
            'file_count' => $fileCount,
            'quota_bytes' => $quota,
            'available_bytes' => $available,
            'disk_free_bytes' => $diskFree,
            'percent_used' => $quota > 0 ? min(100, ($used / $quota) * 100) : 0,
        ];
    }

    public function totalBytesInDatabase(): int
    {
        $stmt = $this->db->query('SELECT COALESCE(SUM(size), 0) FROM submission_files');
        return (int) $stmt->fetchColumn();
    }

    public function physicalUploadsBytes(): int
    {
        if (!is_dir(UPLOADS_DIR)) {
            return 0;
        }

        $total = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(UPLOADS_DIR, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $total += (int) $item->getSize();
            }
        }

        return $total;
    }

    public function diskFreeBytes(): ?int
    {
        if (!is_dir(UPLOADS_DIR)) {
            return null;
        }
        $free = @disk_free_space(UPLOADS_DIR);
        return $free !== false ? (int) $free : null;
    }

    public function countFiles(?int $formId = null, string $search = ''): int
    {
        [$where, $params] = $this->buildFilters($formId, $search);
        $sql = '
            SELECT COUNT(*)
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            INNER JOIN forms f ON f.id = s.form_id
            ' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array>
     */
    public function listFiles(?int $formId = null, string $search = '', int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->buildFilters($formId, $search);

        $sql = '
            SELECT
                sf.*,
                s.form_id,
                s.id AS submission_id,
                s.created_at AS submitted_at,
                f.title AS form_title,
                f.slug AS form_slug
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            INNER JOIN forms f ON f.id = s.form_id
            ' . $where . '
            ORDER BY sf.created_at DESC, sf.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findWithContext(int $fileId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                sf.*,
                s.form_id,
                s.id AS submission_id,
                s.data_json,
                f.title AS form_title
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            INNER JOIN forms f ON f.id = s.form_id
            WHERE sf.id = ?
        ');
        $stmt->execute([$fileId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteFile(int $fileId): ?array
    {
        $file = $this->findWithContext($fileId);
        if (!$file) {
            return null;
        }

        $path = UPLOADS_DIR . '/' . $file['stored_name'];
        $realBase = realpath(UPLOADS_DIR);
        if (is_file($path)) {
            $realPath = realpath($path);
            if ($realBase !== false && $realPath !== false && str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
                @unlink($realPath);
            }
        }

        $this->clearSubmissionFieldReference($file);

        $del = $this->db->prepare('DELETE FROM submission_files WHERE id = ?');
        $del->execute([$fileId]);

        return $file;
    }

    private function clearSubmissionFieldReference(array $file): void
    {
        $formRepo = new FormRepository();
        $form = $formRepo->find((int) $file['form_id']);
        if (!$form) {
            return;
        }

        $schema = $formRepo->decodeSchema($form);
        $data = json_decode((string) ($file['data_json'] ?? '{}'), true) ?: [];
        $changed = false;

        foreach ($schema['fields'] ?? [] as $field) {
            if (($field['id'] ?? '') !== ($file['field_id'] ?? '')) {
                continue;
            }
            $name = $field['name'] ?? '';
            if ($name !== '' && array_key_exists($name, $data)) {
                unset($data[$name]);
                $changed = true;
            }
            break;
        }

        if ($changed) {
            $formRepo->updateSubmissionData((int) $file['submission_id'], $data);
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function buildFilters(?int $formId, string $search): array
    {
        $where = [];
        $params = [];

        if ($formId !== null && $formId > 0) {
            $where[] = 's.form_id = ?';
            $params[] = $formId;
        }

        $search = trim($search);
        if ($search !== '') {
            $where[] = '(sf.original_name LIKE ? OR sf.stored_name LIKE ? OR f.title LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$clause, $params];
    }
}
