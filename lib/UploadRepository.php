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

        $this->removePhysicalFile($file['stored_name'] ?? '');
        $this->clearSubmissionFieldReference($file);

        $del = $this->db->prepare('DELETE FROM submission_files WHERE id = ?');
        $del->execute([$fileId]);

        return $file;
    }

    /** @param list<int> $fileIds @return list<array> */
    public function deleteFiles(array $fileIds): array
    {
        $deleted = [];
        foreach ($fileIds as $fileId) {
            $fileId = (int) $fileId;
            if ($fileId < 1) {
                continue;
            }
            $file = $this->deleteFile($fileId);
            if ($file) {
                $deleted[] = $file;
            }
        }
        return $deleted;
    }

    public function renameFile(int $fileId, string $newName): ?array
    {
        $newName = $this->sanitizeOriginalName($newName);
        if ($newName === '') {
            return null;
        }

        $file = $this->findWithContext($fileId);
        if (!$file) {
            return null;
        }

        $stmt = $this->db->prepare('UPDATE submission_files SET original_name = ? WHERE id = ?');
        $stmt->execute([$newName, $fileId]);

        $file['original_name'] = $newName;
        return $file;
    }

    /**
     * @return list<array>
     */
    public function listFilesForManager(?int $formId = null, string $search = '', string $sort = 'date'): array
    {
        [$where, $params] = $this->buildFilters($formId, $search);
        $order = match ($sort) {
            'name' => 'sf.original_name ASC, sf.id ASC',
            'size' => 'sf.size DESC, sf.id DESC',
            default => 'sf.created_at DESC, sf.id DESC',
        };

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
            ORDER BY ' . $order . '
            LIMIT 1000';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param list<int> $fileIds @return list<array> */
    public function filesByIds(array $fileIds): array
    {
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds), fn (int $id) => $id > 0)));
        if ($fileIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $stmt = $this->db->prepare('
            SELECT
                sf.*,
                s.form_id,
                s.id AS submission_id,
                f.title AS form_title
            FROM submission_files sf
            INNER JOIN submissions s ON s.id = sf.submission_id
            INNER JOIN forms f ON f.id = s.form_id
            WHERE sf.id IN (' . $placeholders . ')
            ORDER BY sf.original_name ASC
        ');
        $stmt->execute($fileIds);
        return $stmt->fetchAll();
    }

    /** @return array{form_id: int, submission_id: int} */
    public function ensureAdminUploadBucket(): array
    {
        $formRepo = new FormRepository();
        $slug = file_manager_form_slug();
        $form = $formRepo->findBySlug($slug, false);

        if (!$form) {
            $formId = $formRepo->create([
                'slug' => $slug,
                'title' => 'File Manager Storage',
                'description' => 'Internal bucket for admin file manager uploads.',
                'status' => 'draft',
                'schema' => ['fields' => []],
            ]);
        } else {
            $formId = (int) $form['id'];
        }

        $stmt = $this->db->prepare('
            SELECT id FROM submissions
            WHERE form_id = ? AND data_json LIKE ?
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute([$formId, '%"__file_manager":true%']);
        $submissionId = (int) ($stmt->fetchColumn() ?: 0);

        if ($submissionId < 1) {
            $submissionId = $formRepo->saveSubmission($formId, ['__file_manager' => true], [], null);
        }

        return [
            'form_id' => $formId,
            'submission_id' => $submissionId,
        ];
    }

    /**
     * @param array{name: string, tmp_name: string, size: int, error: int} $upload
     * @return array{id: int, original_name: string, stored_name: string, mime: string, size: int}|null
     */
    public function uploadManagerFile(array $upload): ?array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $config = app_config();
        $maxBytes = (int) ($config['max_upload_bytes'] ?? 10485760);
        $allowedMimes = $config['allowed_upload_mimes'] ?? [];
        $size = (int) ($upload['size'] ?? 0);

        if ($size < 1 || $size > $maxBytes) {
            throw new RuntimeException('File exceeds the maximum upload size.');
        }

        $stats = $this->storageStats();
        if ($size > $stats['available_bytes']) {
            throw new RuntimeException('Not enough storage quota available.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']) ?: 'application/octet-stream';
        if ($allowedMimes && !in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('This file type is not allowed.');
        }

        $bucket = $this->ensureAdminUploadBucket();
        $formId = $bucket['form_id'];
        $submissionId = $bucket['submission_id'];
        $originalName = $this->sanitizeOriginalName((string) ($upload['name'] ?? 'file'));

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $destDir = UPLOADS_DIR . '/' . $formId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . $stored;
        if (!move_uploaded_file($upload['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }

        $storedName = $formId . '/' . $stored;
        $formRepo = new FormRepository();
        $formRepo->addSubmissionFile($submissionId, [
            'field_id' => 'manager_upload',
            'stored_name' => $storedName,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
        ]);

        $fileId = (int) $this->db->lastInsertId();
        return [
            'id' => $fileId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function resolvePhysicalPath(string $storedName): ?string
    {
        $path = UPLOADS_DIR . '/' . $storedName;
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $realBase = realpath(UPLOADS_DIR);
        $realPath = realpath($path);
        if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    private function removePhysicalFile(string $storedName): void
    {
        $path = UPLOADS_DIR . '/' . $storedName;
        $realBase = realpath(UPLOADS_DIR);
        if (is_file($path)) {
            $realPath = realpath($path);
            if ($realBase !== false && $realPath !== false && str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
                @unlink($realPath);
            }
        }
    }

    private function sanitizeOriginalName(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';
        return trim($name);
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
