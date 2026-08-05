<?php

declare(strict_types=1);

final class InvoiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public function count(?string $search = null, ?string $status = null): int
    {
        [$where, $params] = $this->filterClause($search, $status);
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
    public function all(?string $search = null, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        [$where, $params] = $this->filterClause($search, $status);
        $stmt = $this->db->prepare('
            SELECT i.*, c.name AS client_name, c.company AS client_company
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            ' . $where . '
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT i.*, c.name AS client_name, c.company AS client_company, c.email AS client_email
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE i.id = ?
        ');
        $stmt->execute([$id]);
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
        $totals = invoice_calculate_totals($items, (float) ($data['discount_percent'] ?? 0));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                INSERT INTO invoices (
                    invoice_number, client_id,
                    bill_to_company, bill_to_name, bill_to_street, bill_to_city,
                    bill_to_province, bill_to_postal, bill_to_country,
                    invoice_date, due_date, currency, discount_percent,
                    subtotal, discount_amount, total, notes, status,
                    created_by_user_id, created_by_name, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ');
            $stmt->execute([
                (string) ($data['invoice_number'] ?? $this->nextInvoiceNumber()),
                $data['client_id'] ?? null,
                (string) ($data['bill_to_company'] ?? ''),
                (string) ($data['bill_to_name'] ?? ''),
                (string) ($data['bill_to_street'] ?? ''),
                (string) ($data['bill_to_city'] ?? ''),
                (string) ($data['bill_to_province'] ?? ''),
                (string) ($data['bill_to_postal'] ?? ''),
                (string) ($data['bill_to_country'] ?? 'Canada'),
                (string) ($data['invoice_date'] ?? date('Y-m-d')),
                (string) ($data['due_date'] ?? date('Y-m-d')),
                (string) ($data['currency'] ?? 'CAD'),
                invoice_percent((float) ($data['discount_percent'] ?? 0)),
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['total'],
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
        $totals = invoice_calculate_totals($items, (float) ($data['discount_percent'] ?? 0));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                UPDATE invoices SET
                    invoice_number = ?,
                    client_id = ?,
                    bill_to_company = ?,
                    bill_to_name = ?,
                    bill_to_street = ?,
                    bill_to_city = ?,
                    bill_to_province = ?,
                    bill_to_postal = ?,
                    bill_to_country = ?,
                    invoice_date = ?,
                    due_date = ?,
                    currency = ?,
                    discount_percent = ?,
                    subtotal = ?,
                    discount_amount = ?,
                    total = ?,
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
                (string) ($data['bill_to_street'] ?? ''),
                (string) ($data['bill_to_city'] ?? ''),
                (string) ($data['bill_to_province'] ?? ''),
                (string) ($data['bill_to_postal'] ?? ''),
                (string) ($data['bill_to_country'] ?? 'Canada'),
                (string) ($data['invoice_date'] ?? date('Y-m-d')),
                (string) ($data['due_date'] ?? date('Y-m-d')),
                (string) ($data['currency'] ?? 'CAD'),
                invoice_percent((float) ($data['discount_percent'] ?? 0)),
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['total'],
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
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE invoices SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, now_iso(), $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM invoices WHERE id = ?');
        $stmt->execute([$id]);
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
    private function filterClause(?string $search, ?string $status): array
    {
        $parts = [];
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $like = '%' . trim($search) . '%';
            $parts[] = '(i.invoice_number LIKE ? OR i.bill_to_name LIKE ? OR i.bill_to_company LIKE ? OR c.name LIKE ? OR c.company LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '' && $status !== 'all') {
            $parts[] = 'i.status = ?';
            $params[] = $status;
        }
        $where = $parts === [] ? '' : (' WHERE ' . implode(' AND ', $parts));
        return [$where, $params];
    }
}
