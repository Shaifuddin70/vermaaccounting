<?php

declare(strict_types=1);

final class InvoiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function count(?string $search = null, ?string $status = null, ?int $partnerUserId = null): int
    {
        [$where, $params] = $this->filterClause($search, $status, $partnerUserId);
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            ' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(
        ?string $search = null,
        ?string $status = null,
        int $limit = 50,
        int $offset = 0,
        ?int $partnerUserId = null
    ): array {
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->filterClause($search, $status, $partnerUserId);
        $stmt = $this->db->prepare('
            SELECT i.*, c.name AS client_name, c.company AS client_company,
                   s.id AS source_submission_ref, s.form_id AS source_form_id, f.title AS source_form_title
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            LEFT JOIN submissions s ON s.id = i.source_submission_id
            LEFT JOIN forms f ON f.id = s.form_id
            ' . $where . '
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, ?int $partnerUserId = null): ?array
    {
        [$scopeSql, $scopeParams] = partner_invoice_scope_sql($partnerUserId, 'i');
        $sql = '
            SELECT i.*, c.name AS client_name, c.company AS client_company, c.email AS client_email, c.phone AS client_phone
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE i.id = ?
        ';
        $params = [$id];
        if ($scopeSql !== '') {
            $sql .= ' AND ' . $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function itemsForInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM invoice_items
            WHERE invoice_id = ?
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    public function nextInvoiceNumber(): string
    {
        $stmt = $this->db->query('SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1');
        $last = $stmt->fetchColumn();
        $next = 1;
        if (is_string($last) && preg_match('/(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     */
    public function create(array $data, array $items): int
    {
        $now = now_iso();
        $totals = invoice_calculate_totals(
            $items,
            (float) ($data['discount_percent'] ?? 0),
            (float) ($data['discount_flat'] ?? 0),
            (float) ($data['advance_amount'] ?? 0),
            (float) ($data['due_adjustment'] ?? 0)
        );

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                INSERT INTO invoices (
                    invoice_number, client_id,
                    bill_to_company, bill_to_name, bill_to_email, bill_to_phone,
                    bill_to_street, bill_to_city,
                    bill_to_province, bill_to_postal, bill_to_country,
                    invoice_date, due_date, currency, discount_percent, discount_flat,
                    discount_percent_label, discount_flat_label,
                    advance_amount, due_adjustment, advance_label, due_adjustment_label,
                    subtotal, discount_amount, total, amount_due, notes, status,
                    created_by_user_id, created_by_name, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ');
            $stmt->execute([
                (string) ($data['invoice_number'] ?? $this->nextInvoiceNumber()),
                $data['client_id'] ?? null,
                (string) ($data['bill_to_company'] ?? ''),
                (string) ($data['bill_to_name'] ?? ''),
                (string) ($data['bill_to_email'] ?? ''),
                (string) ($data['bill_to_phone'] ?? ''),
                (string) ($data['bill_to_street'] ?? ''),
                (string) ($data['bill_to_city'] ?? ''),
                (string) ($data['bill_to_province'] ?? ''),
                (string) ($data['bill_to_postal'] ?? ''),
                (string) ($data['bill_to_country'] ?? 'Canada'),
                (string) ($data['invoice_date'] ?? date('Y-m-d')),
                (string) ($data['due_date'] ?? date('Y-m-d')),
                (string) ($data['currency'] ?? 'CAD'),
                invoice_percent((float) ($data['discount_percent'] ?? 0)),
                invoice_money(max(0, (float) ($data['discount_flat'] ?? 0))),
                invoice_sanitize_discount_label((string) ($data['discount_percent_label'] ?? '')) ?: null,
                invoice_sanitize_discount_label((string) ($data['discount_flat_label'] ?? '')) ?: null,
                $totals['advance_amount'],
                $totals['due_adjustment'],
                invoice_sanitize_discount_label((string) ($data['advance_label'] ?? '')) ?: null,
                invoice_sanitize_discount_label((string) ($data['due_adjustment_label'] ?? '')) ?: null,
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['total'],
                $totals['amount_due'],
                (string) ($data['notes'] ?? ''),
                (string) ($data['status'] ?? 'draft'),
                $data['created_by_user_id'] ?? null,
                (string) ($data['created_by_name'] ?? ''),
                $now,
                $now,
            ]);
            $invoiceId = (int) $this->db->lastInsertId();
            $this->replaceItems($invoiceId, $items);
            $this->db->commit();
            return $invoiceId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $items
     */
    public function update(int $id, array $data, array $items): void
    {
        $totals = invoice_calculate_totals(
            $items,
            (float) ($data['discount_percent'] ?? 0),
            (float) ($data['discount_flat'] ?? 0),
            (float) ($data['advance_amount'] ?? 0),
            (float) ($data['due_adjustment'] ?? 0)
        );

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                UPDATE invoices SET
                    invoice_number = ?,
                    client_id = ?,
                    bill_to_company = ?,
                    bill_to_name = ?,
                    bill_to_email = ?,
                    bill_to_phone = ?,
                    bill_to_street = ?,
                    bill_to_city = ?,
                    bill_to_province = ?,
                    bill_to_postal = ?,
                    bill_to_country = ?,
                    invoice_date = ?,
                    due_date = ?,
                    currency = ?,
                    discount_percent = ?,
                    discount_flat = ?,
                    discount_percent_label = ?,
                    discount_flat_label = ?,
                    advance_amount = ?,
                    due_adjustment = ?,
                    advance_label = ?,
                    due_adjustment_label = ?,
                    subtotal = ?,
                    discount_amount = ?,
                    total = ?,
                    amount_due = ?,
                    notes = ?,
                    status = ?,
                    updated_at = ?
                WHERE id = ?
            ');
            $stmt->execute([
                (string) ($data['invoice_number'] ?? ''),
                $data['client_id'] ?? null,
                (string) ($data['bill_to_company'] ?? ''),
                (string) ($data['bill_to_name'] ?? ''),
                (string) ($data['bill_to_email'] ?? ''),
                (string) ($data['bill_to_phone'] ?? ''),
                (string) ($data['bill_to_street'] ?? ''),
                (string) ($data['bill_to_city'] ?? ''),
                (string) ($data['bill_to_province'] ?? ''),
                (string) ($data['bill_to_postal'] ?? ''),
                (string) ($data['bill_to_country'] ?? 'Canada'),
                (string) ($data['invoice_date'] ?? date('Y-m-d')),
                (string) ($data['due_date'] ?? date('Y-m-d')),
                (string) ($data['currency'] ?? 'CAD'),
                invoice_percent((float) ($data['discount_percent'] ?? 0)),
                invoice_money(max(0, (float) ($data['discount_flat'] ?? 0))),
                invoice_sanitize_discount_label((string) ($data['discount_percent_label'] ?? '')) ?: null,
                invoice_sanitize_discount_label((string) ($data['discount_flat_label'] ?? '')) ?: null,
                $totals['advance_amount'],
                $totals['due_adjustment'],
                invoice_sanitize_discount_label((string) ($data['advance_label'] ?? '')) ?: null,
                invoice_sanitize_discount_label((string) ($data['due_adjustment_label'] ?? '')) ?: null,
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['total'],
                $totals['amount_due'],
                (string) ($data['notes'] ?? ''),
                (string) ($data['status'] ?? 'draft'),
                now_iso(),
                $id,
            ]);
            $this->replaceItems($id, $items);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->completeSourceSubmissionIfPaid($id);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE invoices SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, now_iso(), $id]);
        $this->completeSourceSubmissionIfPaid($id);
    }

    /** A paid invoice closes out the submission it was created from. */
    private function completeSourceSubmissionIfPaid(int $id): void
    {
        $stmt = $this->db->prepare('
            SELECT i.invoice_number, s.id AS submission_id, s.form_id, s.status AS submission_status
            FROM invoices i
            JOIN submissions s ON s.id = i.source_submission_id
            WHERE i.id = ? AND i.status = ?
        ');
        $stmt->execute([$id, 'paid']);
        $row = $stmt->fetch();
        if (!$row || ($row['submission_status'] ?? '') === 'complete') {
            return;
        }
        (new FormRepository())->setSubmissionStatus((int) $row['submission_id'], 'complete');
        ActivityLog::record('submission.status_changed', 'submission', (int) $row['submission_id'], [
            'form_id' => (int) $row['form_id'],
            'from_status' => (string) ($row['submission_status'] ?? 'pending'),
            'to_status' => 'complete',
            'reason' => 'Invoice #' . $row['invoice_number'] . ' paid',
        ]);
    }

    public function delete(int $id): void
    {
        (new SubmissionPaymentRepository())->releaseInvoice($id);
        $stmt = $this->db->prepare('DELETE FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Add to the invoice's advance (deducted from the total) and recompute the amount due. */
    public function increaseAdvance(int $id, float $amount, string $defaultLabel = ''): void
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            return;
        }
        $this->setAdvance($id, (float) ($invoice['advance_amount'] ?? 0) + $amount, $defaultLabel);
    }

    /** Replace the invoice's advance (deducted from the total) and recompute the amount due. */
    public function setAdvance(int $id, float $advanceAmount, string $defaultLabel = ''): void
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            return;
        }
        $advance = invoice_money(max(0, $advanceAmount));
        $amountDue = invoice_money(max(
            0,
            (float) ($invoice['total'] ?? 0) - $advance + (float) ($invoice['due_adjustment'] ?? 0)
        ));
        $label = trim((string) ($invoice['advance_label'] ?? ''));
        $stmt = $this->db->prepare('
            UPDATE invoices SET advance_amount = ?, amount_due = ?, advance_label = ?, updated_at = ? WHERE id = ?
        ');
        $stmt->execute([
            $advance,
            $amountDue,
            $label !== '' ? $label : ($defaultLabel !== '' ? $defaultLabel : null),
            now_iso(),
            $id,
        ]);
    }

    public function setSourceSubmission(int $id, ?int $submissionId): void
    {
        $stmt = $this->db->prepare('UPDATE invoices SET source_submission_id = ? WHERE id = ?');
        $stmt->execute([$submissionId ?: null, $id]);
        $this->completeSourceSubmissionIfPaid($id);
    }

    /** @return list<array<string, mixed>> */
    public function forSourceSubmission(int $submissionId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE source_submission_id = ? ORDER BY id DESC');
        $stmt->execute([$submissionId]);
        return $stmt->fetchAll();
    }

    /** Most recent invoice created from a submission that still has a balance. */
    public function openInvoiceForSubmission(int $submissionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE source_submission_id = ? ORDER BY id DESC');
        $stmt->execute([$submissionId]);
        foreach ($stmt->fetchAll() as $invoice) {
            if (invoice_amount_due($invoice) > 0.004) {
                return $invoice;
            }
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    public function payments(int $invoiceId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM invoice_payments
            WHERE invoice_id = ?
            ORDER BY payment_date DESC, id DESC
        ');
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    public function findPayment(int $paymentId, int $invoiceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_payments WHERE id = ? AND invoice_id = ?');
        $stmt->execute([$paymentId, $invoiceId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return float New total paid on the invoice.
     */
    public function addPayment(int $invoiceId, array $data, ?int &$paymentId = null): float
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                INSERT INTO invoice_payments (
                    invoice_id, amount, payment_date, method, reference, note,
                    recorded_by_user_id, recorded_by_name, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $invoiceId,
                invoice_money((float) ($data['amount'] ?? 0)),
                (string) ($data['payment_date'] ?? date('Y-m-d')),
                ($data['method'] ?? '') !== '' ? (string) $data['method'] : null,
                ($data['reference'] ?? '') !== '' ? (string) $data['reference'] : null,
                ($data['note'] ?? '') !== '' ? (string) $data['note'] : null,
                $data['recorded_by_user_id'] ?? null,
                (string) ($data['recorded_by_name'] ?? ''),
                now_iso(),
            ]);
            $paymentId = (int) $this->db->lastInsertId();
            $paid = $this->syncAmountPaid($invoiceId);
            $this->db->commit();
            return $paid;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return float New total paid on the invoice. */
    public function deletePayment(int $paymentId, int $invoiceId): float
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM invoice_payments WHERE id = ? AND invoice_id = ?');
            $stmt->execute([$paymentId, $invoiceId]);
            (new SubmissionPaymentRepository())->releaseInvoicePayment($paymentId);
            $paid = $this->syncAmountPaid($invoiceId);
            $this->db->commit();
            return $paid;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function syncAmountPaid(int $invoiceId): float
    {
        $sum = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?');
        $sum->execute([$invoiceId]);
        $paid = invoice_money((float) $sum->fetchColumn());

        $stmt = $this->db->prepare('UPDATE invoices SET amount_paid = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$paid, now_iso(), $invoiceId]);
        return $paid;
    }

    public function invoiceNumberExists(string $number, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM invoices WHERE invoice_number = ? AND id != ?');
            $stmt->execute([$number, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM invoices WHERE invoice_number = ?');
            $stmt->execute([$number]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceItems(int $invoiceId, array $items): void
    {
        $del = $this->db->prepare('DELETE FROM invoice_items WHERE invoice_id = ?');
        $del->execute([$invoiceId]);

        $ins = $this->db->prepare('
            INSERT INTO invoice_items (
                invoice_id, service_id, name, description, unit_price, quantity, amount, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $order = 0;
        foreach ($items as $item) {
            $qty = max(0.01, (float) ($item['quantity'] ?? 1));
            $price = invoice_money((float) ($item['unit_price'] ?? 0));
            $amount = invoice_money($price * $qty);
            $ins->execute([
                $invoiceId,
                !empty($item['service_id']) ? (int) $item['service_id'] : null,
                (string) ($item['name'] ?? ''),
                (string) ($item['description'] ?? ''),
                $price,
                $qty,
                $amount,
                $order,
            ]);
            $order += 10;
        }
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterClause(?string $search, ?string $status, ?int $partnerUserId = null): array
    {
        $parts = [];
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $like = '%' . trim($search) . '%';
            $submissionRef = (int) ltrim(trim($search), '#');
            $parts[] = '(i.invoice_number LIKE ? OR i.bill_to_name LIKE ? OR i.bill_to_company LIKE ? OR c.name LIKE ? OR c.company LIKE ?'
                . ($submissionRef > 0 && ctype_digit(ltrim(trim($search), '#')) ? ' OR i.source_submission_id = ?' : '') . ')';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            if ($submissionRef > 0 && ctype_digit(ltrim(trim($search), '#'))) {
                $params[] = $submissionRef;
            }
        }
        if ($status !== null && $status !== '' && $status !== 'all') {
            $parts[] = 'i.status = ?';
            $params[] = $status;
        }
        [$scopeSql, $scopeParams] = partner_invoice_scope_sql($partnerUserId, 'i');
        if ($scopeSql !== '') {
            $parts[] = $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
        $where = $parts === [] ? '' : (' WHERE ' . implode(' AND ', $parts));
        return [$where, $params];
    }

    /**
     * Invoice rows for reporting, filtered by invoice_date (inclusive).
     *
     * @return list<array<string, mixed>>
     */
    public function reportRows(?string $from = null, ?string $to = null, ?int $partnerUserId = null): array
    {
        $parts = [];
        $params = [];
        if ($from !== null && $from !== '') {
            $parts[] = 'i.invoice_date >= ?';
            $params[] = $from;
        }
        if ($to !== null && $to !== '') {
            $parts[] = 'i.invoice_date <= ?';
            $params[] = $to;
        }
        [$scopeSql, $scopeParams] = partner_invoice_scope_sql($partnerUserId, 'i');
        if ($scopeSql !== '') {
            $parts[] = $scopeSql;
            $params = array_merge($params, $scopeParams);
        }
        $where = $parts === [] ? '' : (' WHERE ' . implode(' AND ', $parts));

        $stmt = $this->db->prepare('
            SELECT i.id, i.invoice_number, i.client_id, i.invoice_date, i.due_date, i.status,
                   i.subtotal, i.discount_amount, i.total, i.amount_due, i.amount_paid, i.advance_amount, i.due_adjustment,
                   i.bill_to_name, i.bill_to_company,
                   c.name AS client_name, c.company AS client_company
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            ' . $where . '
            ORDER BY i.invoice_date ASC, i.id ASC
        ');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
