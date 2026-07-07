<?php

declare(strict_types=1);

final class HolidayScheduleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM holiday_email_schedules
            ORDER BY holiday_month ASC, holiday_day ASC, name ASC
        ');
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function forMonth(int $month): array
    {
        $month = max(1, min(12, $month));
        $stmt = $this->db->prepare('
            SELECT * FROM holiday_email_schedules
            WHERE holiday_month = ?
            ORDER BY holiday_day ASC, name ASC
        ');
        $stmt->execute([$month]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM holiday_email_schedules WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = now_iso();
        $stmt = $this->db->prepare('
            INSERT INTO holiday_email_schedules (
                name, holiday_month, holiday_day, send_time,
                subject, body_html, enabled,
                last_sent_year, last_campaign_id,
                created_by_user_id, created_by_name,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            (string) ($data['name'] ?? ''),
            (int) ($data['holiday_month'] ?? 1),
            (int) ($data['holiday_day'] ?? 1),
            (string) ($data['send_time'] ?? '09:00:00'),
            (string) ($data['subject'] ?? ''),
            (string) ($data['body_html'] ?? ''),
            !empty($data['enabled']) ? 1 : 0,
            $data['last_sent_year'] ?? null,
            $data['last_campaign_id'] ?? null,
            $data['created_by_user_id'] ?? null,
            (string) ($data['created_by_name'] ?? ''),
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
        $allowed = [
            'name', 'holiday_month', 'holiday_day', 'send_time',
            'subject', 'body_html', 'enabled',
            'last_sent_year', 'last_campaign_id',
        ];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($key === 'enabled') {
                $value = !empty($value) ? 1 : 0;
            }
            $fields[] = $key . ' = ?';
            $params[] = $value;
        }
        if ($fields === []) {
            return;
        }
        $fields[] = 'updated_at = ?';
        $params[] = now_iso();
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE holiday_email_schedules SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM holiday_email_schedules WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function markSent(int $id, int $year, int $campaignId): void
    {
        $this->update($id, [
            'last_sent_year' => $year,
            'last_campaign_id' => $campaignId,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function enabledForDate(int $month, int $day): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM holiday_email_schedules
            WHERE enabled = 1 AND holiday_month = ? AND holiday_day = ?
            ORDER BY send_time ASC, id ASC
        ');
        $stmt->execute([$month, $day]);
        return $stmt->fetchAll();
    }
}
