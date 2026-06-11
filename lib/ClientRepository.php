<?php

declare(strict_types=1);

final class ClientRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function count(?string $search = null): int
    {
        [$where, $params] = $this->searchClause($search);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM clients c ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array>
     */
    public function allWithStats(?string $search = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->searchClause($search);

        $sql = '
            SELECT
                c.*,
                COUNT(DISTINCT cs.submission_id) AS submission_count,
                MAX(s.created_at) AS last_submission_at
            FROM clients c
            LEFT JOIN client_submissions cs ON cs.client_id = c.id
            LEFT JOIN submissions s ON s.id = cs.submission_id
            ' . $where . '
            GROUP BY c.id
            ORDER BY c.name ASC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @return list<array>
     */
    public function submissionsForClient(int $clientId, ?int $year = null, ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = $this->clientSubmissionSql($clientId, $year);
        $sql .= ' ORDER BY s.created_at DESC';
        if ($limit !== null) {
            $limit = max(1, min(200, $limit));
            $offset = max(0, $offset ?? 0);
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countSubmissionsForClient(int $clientId, ?int $year = null): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM client_submissions cs
            INNER JOIN submissions s ON s.id = cs.submission_id
            WHERE cs.client_id = ?
        ';
        $params = [$clientId];
        if ($year !== null && $year > 0) {
            $sql .= ' AND (s.tax_year = ? OR (s.tax_year IS NULL AND YEAR(s.created_at) = ?))';
            $params[] = $year;
            $params[] = $year;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function clientSubmissionSql(int $clientId, ?int $year): array
    {
        $sql = '
            SELECT s.*, f.title AS form_title, f.slug AS form_slug
            FROM client_submissions cs
            INNER JOIN submissions s ON s.id = cs.submission_id
            INNER JOIN forms f ON f.id = s.form_id
            WHERE cs.client_id = ?
        ';
        $params = [$clientId];
        if ($year !== null && $year > 0) {
            $sql .= ' AND (s.tax_year = ? OR (s.tax_year IS NULL AND YEAR(s.created_at) = ?))';
            $params[] = $year;
            $params[] = $year;
        }
        return [$sql, $params];
    }

    /** @return array<int, int> year => count */
    public function submissionYearCountsForClient(int $clientId): array
    {
        $stmt = $this->db->prepare('
            SELECT COALESCE(s.tax_year, YEAR(s.created_at)) AS yr, COUNT(*) AS cnt
            FROM client_submissions cs
            INNER JOIN submissions s ON s.id = cs.submission_id
            WHERE cs.client_id = ?
            GROUP BY yr
            ORDER BY yr DESC
        ');
        $stmt->execute([$clientId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['yr']] = (int) $row['cnt'];
        }
        return $map;
    }

    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findByCin(string $cin): ?array
    {
        $cin = $this->normalizeCin($cin);
        if ($cin === null) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE cin = ? LIMIT 1');
        $stmt->execute([$cin]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data, string $source = 'import'): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO clients (name, cin, email, phone, company, notes, source, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            trim((string) ($data['name'] ?? '')),
            $this->normalizeCin($data['cin'] ?? ''),
            $this->normalizeEmail($data['email'] ?? ''),
            trim((string) ($data['phone'] ?? '')),
            trim((string) ($data['company'] ?? '')),
            trim((string) ($data['notes'] ?? '')),
            $source,
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
        $stmt = $this->db->prepare('
            UPDATE clients
            SET name = ?, cin = ?, email = ?, phone = ?, company = ?, notes = ?, updated_at = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            trim((string) ($data['name'] ?? $existing['name'])),
            $this->normalizeCin($data['cin'] ?? $existing['cin'] ?? ''),
            $this->normalizeEmail($data['email'] ?? $existing['email']),
            trim((string) ($data['phone'] ?? $existing['phone'] ?? '')),
            trim((string) ($data['company'] ?? $existing['company'] ?? '')),
            trim((string) ($data['notes'] ?? $existing['notes'] ?? '')),
            now_iso(),
            $id,
        ]);
    }

    public function linkSubmission(int $clientId, int $submissionId): void
    {
        $stmt = $this->db->prepare('
            INSERT IGNORE INTO client_submissions (client_id, submission_id)
            VALUES (?, ?)
        ');
        $stmt->execute([$clientId, $submissionId]);
    }

    public function linkFromSubmission(array $submission, array $schema): void
    {
        $submissionId = (int) ($submission['id'] ?? 0);
        if ($submissionId < 1) {
            return;
        }

        $extracted = extract_client_from_submission($submission, $schema);
        if ($extracted['name'] === '' && $extracted['email'] === '' && $extracted['cin'] === '') {
            return;
        }

        $result = $this->upsertFromExtracted($extracted, 'submission');
        if ($result === null) {
            return;
        }

        $this->linkSubmission($result['id'], $submissionId);
    }

    public function syncFromSubmissions(): array
    {
        $formRepo = new FormRepository();
        $forms = $formRepo->all();
        $clientsCreated = 0;
        $submissionsLinked = 0;
        $skipped = 0;

        foreach ($forms as $form) {
            $formId = (int) $form['id'];
            $schema = $formRepo->decodeSchema($form);
            $submissions = $formRepo->submissionsForForm($formId);

            foreach ($submissions as $submission) {
                $submissionId = (int) $submission['id'];
                $extracted = extract_client_from_submission($submission, $schema);

                if ($extracted['name'] === '' && $extracted['email'] === '' && $extracted['cin'] === '') {
                    $skipped++;
                    continue;
                }

                $result = $this->upsertFromExtracted($extracted, 'submission');
                if ($result === null) {
                    $skipped++;
                    continue;
                }

                if ($result['is_new']) {
                    $clientsCreated++;
                }

                if (!$this->submissionLinkExists($result['id'], $submissionId)) {
                    $this->linkSubmission($result['id'], $submissionId);
                    $submissionsLinked++;
                }
            }
        }

        return [
            'clients_created' => $clientsCreated,
            'submissions_linked' => $submissionsLinked,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param list<array> $rows
     * @return array{imported: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importRows(array $rows): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $cin = trim((string) ($row['cin'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $line = (int) ($row['_line'] ?? 0);

            if ($name === '' && $email === '' && $cin === '') {
                $skipped++;
                continue;
            }
            if ($name === '') {
                $errors[] = 'Line ' . $line . ': missing name.';
                $skipped++;
                continue;
            }

            $payload = [
                'name' => $name,
                'cin' => $cin,
                'email' => $email,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'company' => trim((string) ($row['company'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];

            $existing = null;
            if ($cin !== '') {
                $existing = $this->findByCin($cin);
            }
            if (!$existing && $email !== '') {
                $existing = $this->findByEmail($email);
            }

            if ($existing) {
                if ($cin !== '' && ($existing['cin'] ?? '') !== '' && $existing['cin'] !== $cin) {
                    $errors[] = 'Line ' . $line . ': CIN conflicts with existing client.';
                    $skipped++;
                    continue;
                }
                $this->update((int) $existing['id'], array_merge($existing, $payload));
                $updated++;
                continue;
            }

            if ($cin !== '' && $this->findByCin($cin)) {
                $errors[] = 'Line ' . $line . ': CIN already in use.';
                $skipped++;
                continue;
            }

            try {
                $this->create($payload, 'import');
                $imported++;
            } catch (PDOException $e) {
                if ($this->isDuplicateCinError($e)) {
                    $errors[] = 'Line ' . $line . ': CIN already in use.';
                    $skipped++;
                    continue;
                }
                throw $e;
            }
        }

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    /**
     * @return list<array>
     */
    public function exportRows(): array
    {
        $stmt = $this->db->query('
            SELECT
                c.name,
                c.cin,
                c.email,
                c.phone,
                c.company,
                c.notes,
                c.source,
                COUNT(DISTINCT cs.submission_id) AS submission_count,
                c.created_at
            FROM clients c
            LEFT JOIN client_submissions cs ON cs.client_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ');
        return $stmt->fetchAll();
    }

    /** @return array{id: int, is_new: bool}|null */
    private function upsertFromExtracted(array $extracted, string $source): ?array
    {
        $name = trim((string) ($extracted['name'] ?? ''));
        $cin = trim((string) ($extracted['cin'] ?? ''));
        $email = strtolower(trim((string) ($extracted['email'] ?? '')));

        if ($name === '' && $email === '' && $cin === '') {
            return null;
        }
        if ($name === '' && $email !== '') {
            $name = $email;
        }
        if ($name === '' && $cin !== '') {
            $name = $cin;
        }

        if ($cin !== '') {
            $existing = $this->findByCin($cin);
            if ($existing) {
                $this->fillEmptyFields((int) $existing['id'], $extracted);
                return ['id' => (int) $existing['id'], 'is_new' => false];
            }
        }

        if ($email !== '') {
            $existing = $this->findByEmail($email);
            if ($existing) {
                $this->fillEmptyFields((int) $existing['id'], $extracted);
                return ['id' => (int) $existing['id'], 'is_new' => false];
            }
        }

        $id = $this->create([
            'name' => $name,
            'cin' => $cin,
            'email' => $email,
            'phone' => $extracted['phone'] ?? '',
            'company' => $extracted['company'] ?? '',
            'notes' => '',
        ], $source);

        return ['id' => $id, 'is_new' => true];
    }

    private function fillEmptyFields(int $clientId, array $extracted): void
    {
        $client = $this->find($clientId);
        if (!$client) {
            return;
        }
        $updates = [];
        if (($client['cin'] ?? '') === '' && ($extracted['cin'] ?? '') !== '') {
            $updates['cin'] = $extracted['cin'];
        }
        if (($client['phone'] ?? '') === '' && ($extracted['phone'] ?? '') !== '') {
            $updates['phone'] = $extracted['phone'];
        }
        if (($client['company'] ?? '') === '' && ($extracted['company'] ?? '') !== '') {
            $updates['company'] = $extracted['company'];
        }
        if (count($updates) > 0) {
            $this->update($clientId, array_merge($client, $updates));
        }
    }

    private function submissionLinkExists(int $clientId, int $submissionId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM client_submissions WHERE client_id = ? AND submission_id = ?');
        $stmt->execute([$clientId, $submissionId]);
        return (bool) $stmt->fetch();
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function searchClause(?string $search): array
    {
        $search = trim((string) $search);
        if ($search === '') {
            return ['', []];
        }
        $like = '%' . $search . '%';
        return [
            'WHERE (c.name LIKE ? OR c.cin LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?)',
            [$like, $like, $like, $like, $like],
        ];
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));
        return $email !== '' ? $email : null;
    }

    private function normalizeCin(mixed $cin): ?string
    {
        $cin = trim((string) $cin);
        return $cin !== '' ? $cin : null;
    }

    private function isDuplicateCinError(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'uk_clients_cin')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate');
    }
}
