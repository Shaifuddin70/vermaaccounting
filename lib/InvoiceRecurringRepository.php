<?php

declare(strict_types=1);

final class InvoiceRecurringRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_recurring_schedules WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByTemplateInvoice(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM invoice_recurring_schedules WHERE template_invoice_id = ? LIMIT 1'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function allEnabled(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM invoice_recurring_schedules WHERE enabled = 1 ORDER BY id ASC'
        );
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertForInvoice(int $templateInvoiceId, array $data): int
    {
        $existing = $this->findByTemplateInvoice($templateInvoiceId);
        $now = now_iso();
        $monthsJson = invoice_recurring_months_to_json($data['months'] ?? []);
        $day = max(1, min(31, (int) ($data['day_of_month'] ?? 1)));
        $dueDays = max(0, min(120, (int) ($data['due_days'] ?? 14)));
        $autoEmail = !empty($data['auto_email']) ? 1 : 0;
        $enabled = !empty($data['enabled']) ? 1 : 0;

        if ($existing) {
            $stmt = $this->db->prepare('
                UPDATE invoice_recurring_schedules SET
                    day_of_month = ?,
                    months_json = ?,
                    due_days = ?,
                    auto_email = ?,
                    enabled = ?,
                    updated_at = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $day,
                $monthsJson,
                $dueDays,
                $autoEmail,
                $enabled,
                $now,
                (int) $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare('
            INSERT INTO invoice_recurring_schedules (
                template_invoice_id, day_of_month, months_json, due_days, auto_email, enabled,
                last_run_ym, last_invoice_id, created_by_user_id, created_by_name, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $templateInvoiceId,
            $day,
            $monthsJson,
            $dueDays,
            $autoEmail,
            $enabled,
            $data['created_by_user_id'] ?? null,
            (string) ($data['created_by_name'] ?? ''),
            $now,
            $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function markRun(int $id, string $runYm, int $invoiceId): void
    {
        $stmt = $this->db->prepare('
            UPDATE invoice_recurring_schedules
            SET last_run_ym = ?, last_invoice_id = ?, updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([$runYm, $invoiceId, now_iso(), $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM invoice_recurring_schedules WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function disable(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice_recurring_schedules SET enabled = 0, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([now_iso(), $id]);
    }
}
