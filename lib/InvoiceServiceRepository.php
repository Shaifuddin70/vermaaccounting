<?php

declare(strict_types=1);

final class InvoiceServiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function count(?string $search = null, ?bool $activeOnly = null): int
    {
        [$where, $params] = $this->filterClause($search, $activeOnly);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM invoice_services' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(?string $search = null, ?bool $activeOnly = null, int $limit = 200, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->filterClause($search, $activeOnly);
        $stmt = $this->db->prepare('
            SELECT * FROM invoice_services
            ' . $where . '
            ORDER BY sort_order ASC, name ASC
            LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function activeForSelect(): array
    {
        return $this->all(null, true, 500, 0);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_services WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO invoice_services (name, description, unit_price, is_active, sort_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
            invoice_money((float) ($data['unit_price'] ?? 0)),
            !empty($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $now,
            $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        $allowed = ['name', 'description', 'unit_price', 'is_active', 'sort_order'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[] = $key . ' = ?';
            if ($key === 'unit_price') {
                $params[] = invoice_money((float) $data[$key]);
            } elseif ($key === 'is_active') {
                $params[] = !empty($data[$key]) ? 1 : 0;
            } elseif ($key === 'sort_order') {
                $params[] = (int) $data[$key];
            } else {
                $params[] = (string) $data[$key];
            }
        }
        if ($fields === []) {
            return;
        }
        $fields[] = 'updated_at = ?';
        $params[] = now_iso();
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE invoice_services SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM invoice_services WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function nextSortOrder(): int
    {
        $max = (int) $this->db->query('SELECT COALESCE(MAX(sort_order), 0) FROM invoice_services')->fetchColumn();
        return $max + 10;
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterClause(?string $search, ?bool $activeOnly): array
    {
        $parts = [];
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $like = '%' . trim($search) . '%';
            $parts[] = '(name LIKE ? OR description LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($activeOnly === true) {
            $parts[] = 'is_active = 1';
        } elseif ($activeOnly === false) {
            $parts[] = 'is_active = 0';
        }
        $where = $parts === [] ? '' : (' WHERE ' . implode(' AND ', $parts));
        return [$where, $params];
    }
}
