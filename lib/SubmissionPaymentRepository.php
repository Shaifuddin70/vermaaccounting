<?php

declare(strict_types=1);

/**
 * Advance payments submitted through a form's payment field.
 *
 * Lifecycle: pending (client says they paid) → verified (staff confirmed receipt)
 * → applied (included in an invoice's advance, deducted from its total and counted as collected).
 * Rejected payments are never applied. Unticking it on the invoice (or deleting the
 * invoice) puts an applied payment back to verified.
 */
final class SubmissionPaymentRepository
{
    public const STATUSES = ['pending', 'verified', 'applied', 'rejected'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    /**
     * Record a pending payment for every payment field the client completed.
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $data
     * @return int Number of payments recorded.
     */
    public function recordFromSubmission(int $submissionId, int $formId, array $fields, array $data): int
    {
        $count = 0;
        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'payment') {
                continue;
            }
            $name = (string) ($field['name'] ?? '');
            $value = trim((string) ($data[$name] ?? ''));
            $amount = payment_field_amount($field);
            if ($value === '' || $amount <= 0) {
                continue;
            }
            $method = payment_method_for_value($field, $value);
            if ($method === null) {
                continue;
            }
            $keys = payment_storage_keys($name);
            $row = [
                'submission_id' => $submissionId,
                'form_id' => $formId,
                'field_id' => (string) $field['id'],
                'amount' => $amount,
                'method' => (string) ($method['recordAs'] ?? 'other'),
                'method_label' => (string) $method['label'],
                'reference' => mb_substr(trim((string) ($data[$keys['reference']] ?? '')), 0, 200),
            ];
            $this->insert($row);
            $update = $this->db->prepare('
                UPDATE submission_payments SET method = ?, method_label = ?, reference = ?
                WHERE submission_id = ? AND field_id = ? AND invoice_id IS NULL
            ');
            $update->execute([
                $row['method'] !== '' ? $row['method'] : null,
                $row['method_label'],
                $row['reference'] !== '' ? $row['reference'] : null,
                $submissionId,
                $row['field_id'],
            ]);
            $count++;
        }
        return $count;
    }

    /** @param array<string, mixed> $row */
    private function insert(array $row): void
    {
        $sql = Database::instance()->driver() === 'mysql'
            ? 'INSERT IGNORE INTO submission_payments'
            : 'INSERT OR IGNORE INTO submission_payments';
        $stmt = $this->db->prepare($sql . '
            (submission_id, form_id, field_id, amount, method, method_label, reference, payment_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $row['submission_id'],
            $row['form_id'],
            $row['field_id'],
            invoice_money((float) $row['amount']),
            $row['method'] !== '' ? $row['method'] : null,
            $row['method_label'],
            $row['reference'] !== '' ? $row['reference'] : null,
            date('Y-m-d'),
            'pending',
            now_iso(),
        ]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM submission_payments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, array<string, mixed>> keyed by field_id */
    public function forSubmission(int $submissionId): array
    {
        $stmt = $this->db->prepare('
            SELECT sp.*, i.invoice_number
            FROM submission_payments sp
            LEFT JOIN invoices i ON i.id = sp.invoice_id
            WHERE sp.submission_id = ?
            ORDER BY sp.id
        ');
        $stmt->execute([$submissionId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['field_id']] = $row;
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function forInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM submission_payments WHERE invoice_id = ? ORDER BY id');
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Unapplied payments for a submission and/or any submission linked to a client.
     *
     * @param list<string> $statuses
     * @return list<array<string, mixed>>
     */
    public function openFor(?int $submissionId, ?int $clientId, array $statuses = ['verified']): array
    {
        $statuses = array_values(array_intersect($statuses, self::STATUSES));
        if ($statuses === [] || (!$submissionId && !$clientId)) {
            return [];
        }
        $scope = [];
        $params = $statuses;
        if ($submissionId) {
            $scope[] = 'sp.submission_id = ?';
            $params[] = $submissionId;
        }
        if ($clientId) {
            $scope[] = 'sp.submission_id IN (SELECT submission_id FROM client_submissions WHERE client_id = ?)';
            $params[] = $clientId;
        }
        $stmt = $this->db->prepare('
            SELECT sp.*, f.title AS form_title
            FROM submission_payments sp
            LEFT JOIN forms f ON f.id = sp.form_id
            WHERE sp.status IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')
              AND sp.invoice_id IS NULL
              AND (' . implode(' OR ', $scope) . ')
            ORDER BY sp.id
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function setStatus(int $id, string $status, ?array $user): void
    {
        $stmt = $this->db->prepare('
            UPDATE submission_payments
            SET status = ?, reviewed_by_user_id = ?, reviewed_by_name = ?, reviewed_at = ?
            WHERE id = ? AND invoice_id IS NULL
        ');
        $stmt->execute([
            $status,
            $user['id'] ?? null,
            (string) ($user['name'] ?? ''),
            now_iso(),
            $id,
        ]);
    }

    /** @return bool False when the payment was already applied elsewhere or is no longer verified. */
    public function markApplied(int $id, int $invoiceId, float $appliedAmount): bool
    {
        $stmt = $this->db->prepare("
            UPDATE submission_payments
            SET status = 'applied', invoice_id = ?, invoice_payment_id = NULL, applied_amount = ?
            WHERE id = ? AND status = 'verified' AND invoice_id IS NULL
        ");
        $stmt->execute([$invoiceId, invoice_money($appliedAmount), $id]);
        return $stmt->rowCount() > 0;
    }

    public function release(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE submission_payments
            SET status = 'verified', invoice_id = NULL, invoice_payment_id = NULL, applied_amount = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    public function releaseInvoicePayment(int $invoicePaymentId): void
    {
        $stmt = $this->db->prepare("
            UPDATE submission_payments
            SET status = 'verified', invoice_id = NULL, invoice_payment_id = NULL, applied_amount = NULL
            WHERE invoice_payment_id = ?
        ");
        $stmt->execute([$invoicePaymentId]);
    }

    public function releaseInvoice(int $invoiceId): void
    {
        $stmt = $this->db->prepare("
            UPDATE submission_payments
            SET status = 'verified', invoice_id = NULL, invoice_payment_id = NULL, applied_amount = NULL
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
    }
}
