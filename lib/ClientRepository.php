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

    public function countWithEmail(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(DISTINCT LOWER(email))
            FROM clients
            WHERE email IS NOT NULL AND TRIM(email) != ''
        ");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Unique client emails for bulk campaigns (first match per email, ordered by name).
     *
     * @return list<array{client_id: int, client_name: string, email: string, sin: string, company: string}>
     */
    public function recipientsForCampaign(): array
    {
        $rows = $this->db->query("
            SELECT c.id, c.name, c.email, c.sin, c.company
            FROM clients c
            WHERE c.email IS NOT NULL AND TRIM(c.email) != ''
            ORDER BY c.name ASC, c.id ASC
        ")->fetchAll();

        return $this->dedupeRecipients($rows);
    }

    /**
     * Clients with email whose birthday matches today (Feb 29 → Feb 28 in non-leap years).
     *
     * @return list<array{client_id: int, client_name: string, email: string, sin: string, company: string}>
     */
    public function recipientsWithBirthdayOn(DateTimeImmutable $day): array
    {
        $year = (int) $day->format('Y');
        $month = (int) $day->format('n');
        $dom = (int) $day->format('j');
        $includeFeb29 = ($month === 2 && $dom === 28 && !checkdate(2, 29, $year));

        $sql = "
            SELECT c.id, c.name, c.email, c.sin, c.company, c.date_of_birth
            FROM clients c
            WHERE c.email IS NOT NULL AND TRIM(c.email) != ''
              AND c.date_of_birth IS NOT NULL AND TRIM(c.date_of_birth) != ''
              AND (c.birthday_last_sent_year IS NULL OR c.birthday_last_sent_year != ?)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$year]);
        $rows = $stmt->fetchAll();

        $matched = [];
        foreach ($rows as $row) {
            $dob = birthday_normalize_dob($row['date_of_birth'] ?? '');
            if ($dob === null) {
                continue;
            }
            $dobMonth = (int) substr($dob, 5, 2);
            $dobDay = (int) substr($dob, 8, 2);
            $isToday = ($dobMonth === $month && $dobDay === $dom);
            $isFeb29Fallback = $includeFeb29 && $dobMonth === 2 && $dobDay === 29;
            if (!$isToday && !$isFeb29Fallback) {
                continue;
            }
            $matched[] = $row;
        }

        return $this->dedupeRecipients($matched);
    }

    public function markBirthdaySent(int $clientId, int $year): void
    {
        $stmt = $this->db->prepare('
            UPDATE clients
            SET birthday_last_sent_year = ?, updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([$year, now_iso(), $clientId]);
    }

    public function countWithBirthday(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM clients
            WHERE date_of_birth IS NOT NULL AND TRIM(date_of_birth) != ''
              AND email IS NOT NULL AND TRIM(email) != ''
        ");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Upcoming birthdays in the next N days (including today).
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingBirthdays(int $withinDays = 30, int $limit = 20): array
    {
        $withinDays = max(1, min(366, $withinDays));
        $limit = max(1, min(100, $limit));
        $tz = app_timezone();
        $today = new DateTimeImmutable('today', $tz);

        $stmt = $this->db->query("
            SELECT c.id, c.name, c.email, c.date_of_birth
            FROM clients c
            WHERE c.date_of_birth IS NOT NULL AND TRIM(c.date_of_birth) != ''
              AND c.email IS NOT NULL AND TRIM(c.email) != ''
        ");
        $rows = $stmt->fetchAll();
        $upcoming = [];

        foreach ($rows as $row) {
            $dob = birthday_normalize_dob($row['date_of_birth'] ?? '');
            if ($dob === null) {
                continue;
            }
            $month = (int) substr($dob, 5, 2);
            $day = (int) substr($dob, 8, 2);
            $nextYear = (int) $today->format('Y');
            if ($month === 2 && $day === 29 && !checkdate(2, 29, $nextYear)) {
                $candidate = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-2-28', $nextYear), $tz);
            } else {
                $candidate = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $nextYear, $month, $day), $tz);
            }
            if ($candidate === false) {
                continue;
            }
            if ($candidate < $today) {
                $nextYear++;
                if ($month === 2 && $day === 29 && !checkdate(2, 29, $nextYear)) {
                    $candidate = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-2-28', $nextYear), $tz);
                } else {
                    $candidate = DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $nextYear, $month, $day), $tz);
                }
            }
            if ($candidate === false) {
                continue;
            }
            $diff = (int) $today->diff($candidate)->days;
            if ($diff > $withinDays) {
                continue;
            }
            $row['next_birthday'] = $candidate->format('Y-m-d');
            $row['days_until'] = $diff;
            $upcoming[] = $row;
        }

        usort($upcoming, static fn (array $a, array $b): int => ($a['days_until'] <=> $b['days_until']) ?: strcmp((string) $a['name'], (string) $b['name']));
        return array_slice($upcoming, 0, $limit);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{client_id: int, client_name: string, email: string, sin: string, company: string}>
     */
    private function dedupeRecipients(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = [
                'client_id' => (int) $row['id'],
                'client_name' => (string) ($row['name'] ?? ''),
                'email' => $email,
                'sin' => (string) ($row['sin'] ?? ''),
                'company' => (string) ($row['company'] ?? ''),
            ];
        }
        return $out;
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
     * Lightweight client list for dropdowns (name + company).
     *
     * @return list<array{id: int, name: string, company: string, email: string}>
     */
    public function listForSelect(int $limit = 2000): array
    {
        $limit = max(1, min(5000, $limit));
        $stmt = $this->db->query('
            SELECT id, name, company, email
            FROM clients
            ORDER BY name ASC
            LIMIT ' . $limit
        );
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'company' => (string) ($row['company'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }
        return $out;
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

    public function findBySin(string $sin): ?array
    {
        $sin = $this->normalizeSin($sin);
        if ($sin === null) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE sin = ? LIMIT 1');
        $stmt->execute([$sin]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data, string $source = 'import'): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO clients (name, sin, email, phone, company, date_of_birth, notes, source, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            trim((string) ($data['name'] ?? '')),
            $this->normalizeSin($data['sin'] ?? ''),
            $this->normalizeEmail($data['email'] ?? ''),
            trim((string) ($data['phone'] ?? '')),
            trim((string) ($data['company'] ?? '')),
            $this->normalizeDob($data['date_of_birth'] ?? null),
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
            SET name = ?, sin = ?, email = ?, phone = ?, company = ?, date_of_birth = ?, notes = ?, updated_at = ?
            WHERE id = ?
        ');
        $dob = array_key_exists('date_of_birth', $data)
            ? $this->normalizeDob($data['date_of_birth'])
            : $this->normalizeDob($existing['date_of_birth'] ?? null);
        return $stmt->execute([
            trim((string) ($data['name'] ?? $existing['name'])),
            $this->normalizeSin($data['sin'] ?? $existing['sin'] ?? ''),
            $this->normalizeEmail($data['email'] ?? $existing['email']),
            trim((string) ($data['phone'] ?? $existing['phone'] ?? '')),
            trim((string) ($data['company'] ?? $existing['company'] ?? '')),
            $dob,
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

    public function findBySubmissionId(int $submissionId): ?array
    {
        if ($submissionId < 1) {
            return null;
        }
        $stmt = $this->db->prepare('
            SELECT c.*
            FROM client_submissions cs
            INNER JOIN clients c ON c.id = cs.client_id
            WHERE cs.submission_id = ?
            ORDER BY cs.client_id ASC
            LIMIT 1
        ');
        $stmt->execute([$submissionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function linkFromSubmission(array $submission, array $schema): void
    {
        $submissionId = (int) ($submission['id'] ?? 0);
        if ($submissionId < 1) {
            return;
        }

        $extracted = extract_client_from_submission($submission, $schema);
        if ($extracted['name'] === '' && $extracted['email'] === '' && $extracted['sin'] === '') {
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

                if ($extracted['name'] === '' && $extracted['email'] === '' && $extracted['sin'] === '') {
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
            $sin = trim((string) ($row['sin'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $line = (int) ($row['_line'] ?? 0);

            if ($name === '' && $email === '' && $sin === '') {
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
                'sin' => $sin,
                'email' => $email,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'company' => trim((string) ($row['company'] ?? '')),
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'notes' => trim((string) ($row['notes'] ?? '')),
            ];

            $existing = null;
            if ($sin !== '') {
                $existing = $this->findBySin($sin);
            }
            if (!$existing && $email !== '') {
                $existing = $this->findByEmail($email);
            }

            if ($existing) {
                if ($sin !== '' && ($existing['sin'] ?? '') !== '' && $existing['sin'] !== $sin) {
                    $errors[] = 'Line ' . $line . ': SIN conflicts with existing client.';
                    $skipped++;
                    continue;
                }
                $this->update((int) $existing['id'], array_merge($existing, $payload));
                $updated++;
                continue;
            }

            if ($sin !== '' && $this->findBySin($sin)) {
                $errors[] = 'Line ' . $line . ': SIN already in use.';
                $skipped++;
                continue;
            }

            try {
                $this->create($payload, 'import');
                $imported++;
            } catch (PDOException $e) {
                if ($this->isDuplicateSinError($e)) {
                    $errors[] = 'Line ' . $line . ': SIN already in use.';
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
                c.sin,
                c.email,
                c.phone,
                c.company,
                c.date_of_birth,
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
        $sin = trim((string) ($extracted['sin'] ?? ''));
        $email = strtolower(trim((string) ($extracted['email'] ?? '')));

        if ($name === '' && $email === '' && $sin === '') {
            return null;
        }
        if ($name === '' && $email !== '') {
            $name = $email;
        }
        if ($name === '' && $sin !== '') {
            $name = $sin;
        }

        if ($sin !== '') {
            $existing = $this->findBySin($sin);
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
            'sin' => $sin,
            'email' => $email,
            'phone' => $extracted['phone'] ?? '',
            'company' => $extracted['company'] ?? '',
            'date_of_birth' => $extracted['date_of_birth'] ?? null,
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
        if (($client['sin'] ?? '') === '' && ($extracted['sin'] ?? '') !== '') {
            $updates['sin'] = $extracted['sin'];
        }
        if (($client['phone'] ?? '') === '' && ($extracted['phone'] ?? '') !== '') {
            $updates['phone'] = $extracted['phone'];
        }
        if (($client['company'] ?? '') === '' && ($extracted['company'] ?? '') !== '') {
            $updates['company'] = $extracted['company'];
        }
        if (($client['date_of_birth'] ?? '') === '' && ($extracted['date_of_birth'] ?? '') !== '') {
            $updates['date_of_birth'] = $extracted['date_of_birth'];
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
        if (preg_match('/^\d+$/', $search)) {
            return [
                'WHERE (c.id = ? OR c.name LIKE ? OR c.sin LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?)',
                [(int) $search, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'],
            ];
        }
        $like = '%' . $search . '%';
        return [
            'WHERE (c.name LIKE ? OR c.sin LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ?)',
            [$like, $like, $like, $like, $like],
        ];
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));
        return $email !== '' ? $email : null;
    }

    private function normalizeSin(mixed $sin): ?string
    {
        $sin = trim((string) $sin);
        return $sin !== '' ? $sin : null;
    }

    private function normalizeDob(mixed $dob): ?string
    {
        return birthday_normalize_dob($dob);
    }

    private function isDuplicateSinError(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'uk_clients_sin')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate');
    }
}
