<?php

declare(strict_types=1);

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, name, email, role, status, created_at, updated_at FROM users ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function countUsers(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function allPaginated(int $limit, int $offset): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $stmt = $this->db->query(
            'SELECT id, name, email, reference_code, role, status, created_at, updated_at FROM users ORDER BY name ASC LIMIT '
            . $limit . ' OFFSET ' . $offset
        );
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, name, email, reference_code, role, status, created_at, updated_at
            FROM users WHERE id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
        }
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO users (name, email, reference_code, password_hash, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $referenceCode = $this->normalizeReferenceCode($data['reference_code'] ?? null, (string) ($data['role'] ?? 'reviewer'));
        $stmt->execute([
            $data['name'],
            $data['email'],
            $referenceCode,
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'] ?? 'reviewer',
            $data['status'] ?? 'active',
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

        $fields = [
            'name'   => $data['name'] ?? $existing['name'],
            'email'  => $data['email'] ?? $existing['email'],
            'role'   => $data['role'] ?? $existing['role'],
            'status' => $data['status'] ?? $existing['status'],
            'reference_code' => array_key_exists('reference_code', $data)
                ? $this->normalizeReferenceCode($data['reference_code'], (string) ($data['role'] ?? $existing['role']))
                : ($existing['reference_code'] ?? null),
        ];

        if (!empty($data['password'])) {
            $stmt = $this->db->prepare('
                UPDATE users SET name = ?, email = ?, reference_code = ?, role = ?, status = ?, password_hash = ?, updated_at = ? WHERE id = ?
            ');
            return $stmt->execute([
                $fields['name'], $fields['email'], $fields['reference_code'], $fields['role'], $fields['status'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                now_iso(), $id,
            ]);
        }

        $stmt = $this->db->prepare('
            UPDATE users SET name = ?, email = ?, reference_code = ?, role = ?, status = ?, updated_at = ? WHERE id = ?
        ');
        return $stmt->execute([
            $fields['name'], $fields['email'], $fields['reference_code'], $fields['role'], $fields['status'],
            now_iso(), $id,
        ]);
    }

    private function normalizeReferenceCode(mixed $code, string $role): ?string
    {
        $code = trim((string) $code);
        if ($role !== 'partner' || $code === '') {
            return null;
        }
        return $code;
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE users SET status = ?, updated_at = ? WHERE id = ?');
        return $stmt->execute([$status, now_iso(), $id]);
    }

    public function activePartners(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, email, reference_code
            FROM users
            WHERE role = 'partner' AND status = 'active'
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    /** @param list<int>|null $limitToIds */
    public function findPartnerByReferenceCode(string $code, ?array $limitToIds = null): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $sql = "
            SELECT id, name, email, reference_code, role, status
            FROM users
            WHERE role = 'partner' AND status = 'active' AND reference_code = ?
        ";
        $params = [$code];
        if ($limitToIds !== null && $limitToIds !== []) {
            $placeholders = implode(',', array_fill(0, count($limitToIds), '?'));
            $sql .= ' AND id IN (' . $placeholders . ')';
            $params = array_merge($params, array_map('intval', $limitToIds));
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function referenceCodeExists(string $code, ?int $excludeId = null): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE reference_code = ? AND id != ?');
            $stmt->execute([$code, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE reference_code = ?');
            $stmt->execute([$code]);
        }
        return (bool) $stmt->fetch();
    }

    public function counts(): array
    {
        $stmt = $this->db->query('
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN role = \'admin\' THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN role = \'reviewer\' THEN 1 ELSE 0 END) AS reviewers,
                SUM(CASE WHEN role = \'partner\' THEN 1 ELSE 0 END) AS partners
            FROM users
        ');
        $row = $stmt->fetch() ?: [];
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'active'    => (int) ($row['active'] ?? 0),
            'admins'    => (int) ($row['admins'] ?? 0),
            'reviewers' => (int) ($row['reviewers'] ?? 0),
            'partners'  => (int) ($row['partners'] ?? 0),
        ];
    }
}
