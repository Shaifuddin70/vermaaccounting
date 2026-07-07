<?php

declare(strict_types=1);

final class FileFolderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM file_folders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listChildren(?int $parentId): array
    {
        if ($parentId === null || $parentId < 1) {
            $stmt = $this->db->query('
                SELECT * FROM file_folders
                WHERE parent_id IS NULL
                ORDER BY name ASC, id ASC
            ');
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('
            SELECT * FROM file_folders
            WHERE parent_id = ?
            ORDER BY name ASC, id ASC
        ');
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    /** @return list<array{id: int|null, name: string}> */
    public function breadcrumb(?int $folderId): array
    {
        $trail = [['id' => null, 'name' => 'All files']];
        if ($folderId === null || $folderId < 1) {
            return $trail;
        }

        $chain = [];
        $currentId = $folderId;
        while ($currentId > 0) {
            $folder = $this->find($currentId);
            if (!$folder) {
                break;
            }
            $chain[] = ['id' => (int) $folder['id'], 'name' => (string) $folder['name']];
            $parentId = $folder['parent_id'] ?? null;
            $currentId = $parentId !== null ? (int) $parentId : 0;
        }

        return array_merge($trail, array_reverse($chain));
    }

    public function create(?int $parentId, string $name): int
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new RuntimeException('Folder name is required.');
        }
        if ($parentId !== null && $parentId > 0 && !$this->find($parentId)) {
            throw new RuntimeException('Parent folder not found.');
        }
        if ($this->siblingExists($parentId, $name)) {
            throw new RuntimeException('A folder with this name already exists here.');
        }

        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO file_folders (parent_id, name, created_at, updated_at)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $parentId !== null && $parentId > 0 ? $parentId : null,
            $name,
            $now,
            $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function rename(int $folderId, string $name): ?array
    {
        $folder = $this->find($folderId);
        if (!$folder) {
            return null;
        }

        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new RuntimeException('Folder name is required.');
        }

        $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
        if ($this->siblingExists($parentId, $name, $folderId)) {
            throw new RuntimeException('A folder with this name already exists here.');
        }

        $stmt = $this->db->prepare('UPDATE file_folders SET name = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$name, now_iso(), $folderId]);
        $folder['name'] = $name;
        return $folder;
    }

    public function delete(int $folderId): bool
    {
        $folder = $this->find($folderId);
        if (!$folder) {
            return false;
        }
        if ($this->childFolderCount($folderId) > 0 || $this->fileCount($folderId) > 0) {
            throw new RuntimeException('Folder is not empty. Move or delete its contents first.');
        }

        $stmt = $this->db->prepare('DELETE FROM file_folders WHERE id = ?');
        $stmt->execute([$folderId]);
        return true;
    }

    public function move(int $folderId, ?int $newParentId): ?array
    {
        $folder = $this->find($folderId);
        if (!$folder) {
            return null;
        }

        if ($newParentId !== null && $newParentId > 0) {
            if ($newParentId === $folderId) {
                throw new RuntimeException('A folder cannot be moved into itself.');
            }
            if (!$this->find($newParentId)) {
                throw new RuntimeException('Destination folder not found.');
            }
            if ($this->isDescendant($newParentId, $folderId)) {
                throw new RuntimeException('A folder cannot be moved into one of its subfolders.');
            }
        } else {
            $newParentId = null;
        }

        $currentParent = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
        if ($currentParent === $newParentId) {
            return $folder;
        }

        if ($this->siblingExists($newParentId, (string) $folder['name'], $folderId)) {
            throw new RuntimeException('A folder with this name already exists in the destination.');
        }

        $stmt = $this->db->prepare('UPDATE file_folders SET parent_id = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$newParentId, now_iso(), $folderId]);
        $folder['parent_id'] = $newParentId;
        return $folder;
    }

    public function childFolderCount(int $folderId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM file_folders WHERE parent_id = ?');
        $stmt->execute([$folderId]);
        return (int) $stmt->fetchColumn();
    }

    public function fileCount(int $folderId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM submission_files WHERE folder_id = ?');
        $stmt->execute([$folderId]);
        return (int) $stmt->fetchColumn();
    }

    public function itemCount(int $folderId): int
    {
        return $this->childFolderCount($folderId) + $this->fileCount($folderId);
    }

    public function isDescendant(int $folderId, int $possibleAncestorId): bool
    {
        $currentId = $folderId;
        while ($currentId > 0) {
            $folder = $this->find($currentId);
            if (!$folder) {
                return false;
            }
            $parentId = $folder['parent_id'] ?? null;
            if ($parentId === null) {
                return false;
            }
            if ((int) $parentId === $possibleAncestorId) {
                return true;
            }
            $currentId = (int) $parentId;
        }
        return false;
    }

    /** @return list<array{id: int, name: string, parent_id: int|null, item_count: int}> */
    public function listForApi(?int $parentId): array
    {
        return array_map(function (array $folder): array {
            $id = (int) $folder['id'];
            return [
                'id' => $id,
                'name' => (string) $folder['name'],
                'parent_id' => $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null,
                'item_count' => $this->itemCount($id),
            ];
        }, $this->listChildren($parentId));
    }

    /** @return list<array{id: int, name: string, path: string}> */
    public function listAllForPicker(): array
    {
        $out = [];
        $this->collectPickerRows(null, '', $out);
        usort($out, fn (array $a, array $b): int => strcasecmp($a['path'], $b['path']));
        return $out;
    }

    /** @param list<array{id: int, name: string, path: string}> $out */
    private function collectPickerRows(?int $parentId, string $prefix, array &$out): void
    {
        foreach ($this->listChildren($parentId) as $folder) {
            $id = (int) $folder['id'];
            $name = (string) $folder['name'];
            $path = $prefix === '' ? $name : $prefix . ' / ' . $name;
            $out[] = ['id' => $id, 'name' => $name, 'path' => $path];
            $this->collectPickerRows($id, $path, $out);
        }
    }

    private function siblingExists(?int $parentId, string $name, ?int $excludeId = null): bool
    {
        if ($parentId === null || $parentId < 1) {
            $sql = 'SELECT COUNT(*) FROM file_folders WHERE parent_id IS NULL AND name = ?';
            $params = [$name];
        } else {
            $sql = 'SELECT COUNT(*) FROM file_folders WHERE parent_id = ? AND name = ?';
            $params = [$parentId, $name];
        }
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function sanitizeName(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';
        $name = trim($name);
        if (strlen($name) > 255) {
            $name = substr($name, 0, 255);
        }
        return $name;
    }
}
