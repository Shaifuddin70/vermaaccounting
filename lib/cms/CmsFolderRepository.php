<?php

declare(strict_types=1);

/**
 * Organisational folders. A folder's url_prefix is only the default prefix for new content in it;
 * moving or renaming folders never changes the URL of content that already exists.
 */
final class CmsFolderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /** @return list<array> */
    public function all(bool $includeArchived = true): array
    {
        $sql = 'SELECT f.*, (SELECT COUNT(*) FROM cms_contents c WHERE c.folder_id = f.id) AS content_count FROM cms_folders f';
        if (!$includeArchived) {
            $sql .= " WHERE f.status = 'active'";
        }
        $rows = $this->db->query($sql . ' ORDER BY f.sort_order, f.name')->fetchAll();
        return array_map([$this, 'hydrate'], $rows);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPrefix(string $prefix): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_folders WHERE url_prefix = ? ORDER BY id LIMIT 1');
        $stmt->execute([$prefix]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** The folder whose landing page is this content (the page's own sub-pages folder). */
    public function forLanding(int $contentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_folders WHERE landing_content_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$contentId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** Content items in this folder and its subfolders. */
    public function contentCount(int $id): int
    {
        $ids = [$id];
        $byParent = [];
        foreach ($this->all() as $f) {
            $byParent[(int) ($f['parent_id'] ?? 0)][] = (int) $f['id'];
        }
        for ($i = 0; $i < count($ids) && $i < 500; $i++) {
            array_push($ids, ...($byParent[$ids[$i]] ?? []));
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM cms_contents WHERE folder_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Give a page its own folder for sub-pages (or take it away). The folder follows the page: its name is the
     * page title, its URL prefix the page URL, and it sits in the page's folder.
     *
     * @return ?string error
     */
    public function syncLanding(array $content, bool $enabled): ?string
    {
        $id = (int) $content['id'];
        $folder = $this->forLanding($id);
        if (!$enabled) {
            if ($folder === null) {
                return null;
            }
            $n = $this->contentCount((int) $folder['id']);
            if ($n > 0) {
                return 'This page still has ' . $n . ' sub-page' . ($n === 1 ? '' : 's') . '. Move or delete them first.';
            }
            $this->delete((int) $folder['id']);
            ActivityLog::record('cms.folder_deleted', 'cms_folder', (int) $folder['id'], ['name' => $folder['name'], 'landing' => $id]);
            return null;
        }
        $path = (string) $content['path'];
        if ($path === '/' || $path === '') {
            return 'The homepage cannot have sub-pages.';
        }
        $parent = (int) ($content['folder_id'] ?? 0) ?: null;
        $data = [
            'name' => mb_substr((string) $content['title'], 0, 191) ?: $path,
            'slug' => CmsPath::slugify((string) $content['title']) ?: 'page-' . $id,
            'url_prefix' => $path,
            'landing_content_id' => $id,
        ];
        if ($folder === null) {
            $folder = $this->findByPrefix($path);
            if ($folder !== null && !empty($folder['landing_content_id']) && (int) $folder['landing_content_id'] !== $id) {
                $folder = null;
            }
        }
        if ($folder === null) {
            $folderId = $this->create($data + ['parent_id' => $parent, 'default_type' => 'page']);
            ActivityLog::record('cms.folder_created', 'cms_folder', $folderId, ['name' => $data['name'], 'landing' => $id]);
        } else {
            if (!$this->wouldCreateCycle((int) $folder['id'], $parent)) {
                $data['parent_id'] = $parent;
            }
            $this->update((int) $folder['id'], $data);
        }
        return null;
    }

    /**
     * Folders as a nested tree.
     *
     * @return list<array> each with 'children'
     */
    public function tree(bool $includeArchived = true): array
    {
        $byParent = [];
        foreach ($this->all($includeArchived) as $folder) {
            $byParent[(int) ($folder['parent_id'] ?? 0)][] = $folder;
        }
        $build = function (int $parent, int $depth) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $folder) {
                $folder['depth'] = $depth;
                $folder['children'] = $depth < 12 ? $build((int) $folder['id'], $depth + 1) : [];
                $out[] = $folder;
            }
            return $out;
        };
        return $build(0, 0);
    }

    /** Flat list in tree order with depth, for select menus. */
    public function flatTree(bool $includeArchived = false): array
    {
        $out = [];
        $walk = function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                $children = $node['children'];
                unset($node['children']);
                $out[] = $node;
                $walk($children);
            }
        };
        $walk($this->tree($includeArchived));
        return $out;
    }

    /** @return list<array> root-first chain ending with this folder */
    public function ancestors(int $id): array
    {
        $chain = [];
        $seen = [];
        $current = $this->find($id);
        while ($current !== null && !isset($seen[$current['id']])) {
            $seen[$current['id']] = true;
            array_unshift($chain, $current);
            $current = $current['parent_id'] ? $this->find((int) $current['parent_id']) : null;
        }
        return $chain;
    }

    /** True when $candidateParent is $id itself or one of its descendants. */
    public function wouldCreateCycle(int $id, ?int $candidateParent): bool
    {
        if ($candidateParent === null || $candidateParent === 0) {
            return false;
        }
        if ($candidateParent === $id) {
            return true;
        }
        foreach ($this->ancestors($candidateParent) as $ancestor) {
            if ((int) $ancestor['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    public function create(array $data): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('INSERT INTO cms_folders (parent_id, name, slug, url_prefix, sort_order, status, landing_content_id, default_type, seo_defaults_json, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['parent_id'] ?: null,
            $data['name'],
            $data['slug'],
            $data['url_prefix'] ?? '',
            (int) ($data['sort_order'] ?? $this->nextSort($data['parent_id'] ?: null)),
            $data['status'] ?? 'active',
            $data['landing_content_id'] ?? null,
            $data['default_type'] ?? 'page',
            json_encode($data['seo_defaults'] ?? [], JSON_UNESCAPED_SLASHES),
            $now,
            $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['parent_id', 'name', 'slug', 'url_prefix', 'sort_order', 'status', 'landing_content_id', 'default_type'];
        $row = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $row[$col] = $data[$col];
            }
        }
        if (array_key_exists('seo_defaults', $data)) {
            $row['seo_defaults_json'] = json_encode($data['seo_defaults'], JSON_UNESCAPED_SLASHES);
        }
        $row['updated_at'] = now_iso();
        $sets = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($row)));
        $this->db->prepare('UPDATE cms_folders SET ' . $sets . ' WHERE id = ?')->execute([...array_values($row), $id]);
    }

    public function delete(int $id): void
    {
        $folder = $this->find($id);
        if ($folder === null) {
            return;
        }
        $parent = $folder['parent_id'] ?: null;
        $this->db->prepare('UPDATE cms_folders SET parent_id = ? WHERE parent_id = ?')->execute([$parent, $id]);
        $this->db->prepare('UPDATE cms_contents SET folder_id = ? WHERE folder_id = ?')->execute([$parent, $id]);
        $this->db->prepare('DELETE FROM cms_folders WHERE id = ?')->execute([$id]);
    }

    /** @param list<array{id: int, parent_id: ?int}> $order sibling order as submitted by the tree UI */
    public function reorder(array $order): void
    {
        $stmt = $this->db->prepare('UPDATE cms_folders SET parent_id = ?, sort_order = ?, updated_at = ? WHERE id = ?');
        $positions = [];
        foreach ($order as $item) {
            $parent = $item['parent_id'] ?: null;
            $key = (string) ($parent ?? 0);
            $positions[$key] = ($positions[$key] ?? 0) + 1;
            $stmt->execute([$parent, $positions[$key], now_iso(), (int) $item['id']]);
        }
    }

    private function nextSort(?int $parent): int
    {
        $stmt = $parent === null
            ? $this->db->query('SELECT MAX(sort_order) FROM cms_folders WHERE parent_id IS NULL')
            : $this->db->prepare('SELECT MAX(sort_order) FROM cms_folders WHERE parent_id = ?');
        if ($parent !== null) {
            $stmt->execute([$parent]);
        }
        return (int) $stmt->fetchColumn() + 1;
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['landing_content_id'] = $row['landing_content_id'] !== null ? (int) $row['landing_content_id'] : null;
        $row['seo_defaults'] = json_decode((string) ($row['seo_defaults_json'] ?? ''), true) ?: [];
        return $row;
    }
}
