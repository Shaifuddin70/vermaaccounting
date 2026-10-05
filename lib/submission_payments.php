<?php
declare(strict_types=1);

function submission_payment_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending verification',
        'verified' => 'Verified — not yet invoiced',
        'applied' => 'Applied to invoice',
        'rejected' => 'Rejected',
        default => ucfirst($status),
    };
}

/** Accept a posted source submission id only if it exists (and, for partners, belongs to the invoice's client). */
function invoice_valid_source_submission(int $submissionId, ?int $clientId, ?int $partnerId): int
{
    if ($submissionId < 1) {
        return 0;
    }
    $db = Database::instance()->pdo();
    if ($partnerId !== null) {
        if (!$clientId) {
            return 0;
        }
        $stmt = $db->prepare('SELECT 1 FROM client_submissions WHERE submission_id = ? AND client_id = ?');
        $stmt->execute([$submissionId, $clientId]);
    } else {
        $stmt = $db->prepare('SELECT 1 FROM submissions WHERE id = ?');
        $stmt->execute([$submissionId]);
    }
    return $stmt->fetchColumn() ? $submissionId : 0;
}

/** Mark an invoice paid once its balance hits zero (same rule as recording a payment manually). */
function invoice_sync_paid_status(int $invoiceId): bool
{
    $repo = new InvoiceRepository();
    $invoice = $repo->find($invoiceId);
    if (!$invoice) {
        return false;
    }
    if (invoice_amount_due($invoice) <= 0 && ($invoice['status'] ?? '') !== 'paid') {
        $repo->updateStatus($invoiceId, 'paid');
        return true;
    }
    return false;
}

function submission_payment_default_advance_label(): string
{
    return 'Advance payment received';
}

/** @param list<array<string, mixed>> $payments */
function submission_payments_total(array $payments): float
{
    $sum = 0.0;
    foreach ($payments as $payment) {
        $sum += (float) ($payment['applied_amount'] ?? $payment['amount'] ?? 0);
    }
    return invoice_money($sum);
}

/**
 * After an invoice save, set its advance to the manually entered part plus every form payment
 * actually linked to it, so the stored advance never includes payments that were not applied.
 */
function invoice_sync_advance_with_linked_payments(int $invoiceId, float $manualAdvance): void
{
    $linked = (new SubmissionPaymentRepository())->forInvoice($invoiceId);
    $target = invoice_money(max(0, $manualAdvance) + submission_payments_total($linked));
    $repo = new InvoiceRepository();
    $invoice = $repo->find($invoiceId);
    if (!$invoice) {
        return;
    }
    if (abs(invoice_money((float) ($invoice['advance_amount'] ?? 0)) - $target) > 0.004) {
        $repo->setAdvance($invoiceId, $target, $linked !== [] ? submission_payment_default_advance_label() : '');
    }
}

/**
 * Keep Paid in step with the figures after an invoice edit: mark it Paid when advance and payments
 * clear the balance, and reopen it as Sent when the edit brings back a balance on an invoice that
 * had been Paid because its figures were settled (not because staff marked it Paid by hand).
 *
 * @return string|null New status when it changed.
 */
function invoice_reconcile_paid_status(int $invoiceId, bool $wasSettledByFigures): ?string
{
    $repo = new InvoiceRepository();
    $invoice = $repo->find($invoiceId);
    if (!$invoice) {
        return null;
    }
    $state = invoice_due_state($invoice);
    $status = (string) ($invoice['status'] ?? '');
    $hasCredit = $state['advance_amount'] > 0 || $state['recorded_paid'] > 0;

    if ($status !== 'paid' && $hasCredit && $state['raw_amount_due'] <= 0.004) {
        $repo->updateStatus($invoiceId, 'paid');
        return 'paid';
    }
    if ($status === 'paid' && $wasSettledByFigures && $state['raw_amount_due'] > 0.004) {
        $repo->updateStatus($invoiceId, 'sent');
        return 'sent';
    }
    return null;
}

/**
 * Link verified form payments to an invoice and release ones that were unticked.
 * Callers must re-sync the advance afterwards (invoice_sync_advance_with_linked_payments).
 *
 * @param list<array<string, mixed>> $apply verified payments to include in the advance
 * @param list<array<string, mixed>> $release payments previously applied to this invoice, now excluded
 * @return array{count: int, total: float}
 */
function submission_payments_link_to_invoice(int $invoiceId, array $apply, array $release = []): array
{
    $repo = new SubmissionPaymentRepository();
    foreach ($release as $payment) {
        $repo->release((int) $payment['id']);
    }
    $result = ['count' => 0, 'total' => 0.0];
    foreach ($apply as $payment) {
        if (($payment['status'] ?? '') !== 'verified' || !empty($payment['invoice_id'])) {
            continue;
        }
        if (!$repo->markApplied((int) $payment['id'], $invoiceId, (float) $payment['amount'])) {
            continue;
        }
        ActivityLog::record('invoice.advance_applied', 'invoice', $invoiceId, [
            'amount' => (float) $payment['amount'],
            'submission_payment_id' => (int) $payment['id'],
            'submission_id' => (int) $payment['submission_id'],
        ]);
        $result['count']++;
        $result['total'] = invoice_money($result['total'] + (float) $payment['amount']);
    }
    return $result;
}

/**
 * Add verified form payments to an existing invoice's advance (deducted from its total).
 * Each is capped at the remaining balance; payments that no longer fit stay verified.
 *
 * @param list<array<string, mixed>> $payments
 * @return array{count: int, total: float, marked_paid: bool}
 */
function submission_payments_add_to_invoice_advance(int $invoiceId, array $payments): array
{
    $invoiceRepo = new InvoiceRepository();
    $paymentRepo = new SubmissionPaymentRepository();
    $result = ['count' => 0, 'total' => 0.0, 'marked_paid' => false];

    foreach ($payments as $payment) {
        if (($payment['status'] ?? '') !== 'verified' || !empty($payment['invoice_id'])) {
            continue;
        }
        $invoice = $invoiceRepo->find($invoiceId);
        $balance = $invoice ? invoice_amount_due($invoice) : 0.0;
        if ($balance <= 0.004) {
            break;
        }
        $amount = invoice_money(min((float) $payment['amount'], $balance));
        if (!$paymentRepo->markApplied((int) $payment['id'], $invoiceId, $amount)) {
            continue;
        }
        $invoiceRepo->increaseAdvance($invoiceId, $amount, submission_payment_default_advance_label());
        ActivityLog::record('invoice.advance_applied', 'invoice', $invoiceId, [
            'number' => (string) ($invoice['invoice_number'] ?? ''),
            'amount' => $amount,
            'submission_payment_id' => (int) $payment['id'],
            'submission_id' => (int) $payment['submission_id'],
        ]);
        $result['count']++;
        $result['total'] = invoice_money($result['total'] + $amount);
    }

    if ($result['count'] > 0) {
        $result['marked_paid'] = invoice_sync_paid_status($invoiceId);
    }
    return $result;
}

function invoice_status_change_note(?string $newStatus): string
{
    return match ($newStatus) {
        'paid' => ' Invoice is now fully paid.',
        'sent' => ' A balance is due again, so the status changed from Paid to Sent.',
        default => '',
    };
}

/** Human summary for flash messages. */
function submission_payments_apply_note(array $result): string
{
    if (($result['count'] ?? 0) < 1) {
        return '';
    }
    return ' ' . invoice_format_money((float) $result['total']) . ' in form advance payment'
        . ($result['count'] > 1 ? 's' : '') . ' deducted from the total.'
        . (!empty($result['marked_paid']) ? ' Invoice is now fully paid.' : '');
}
