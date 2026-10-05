<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/invoices');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/invoices');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$repo = new InvoiceRepository();
$partnerId = partner_user_id();
$invoice = $id > 0 ? $repo->find($id, $partnerId) : null;

if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}
assert_invoice_access($invoice);

$redirect = '/admin/invoice-view?id=' . $id;

if ($action === 'delete') {
    Auth::requireCapability('invoices.delete');
    $number = (string) ($invoice['invoice_number'] ?? '');
    $repo->delete($id);
    ActivityLog::record('invoice.deleted', 'invoice', $id, ['number' => $number]);
    $_SESSION['flash_success'] = 'Invoice #' . $number . ' deleted.';
    header('Location: /admin/invoices');
    exit;
}

if ($action === 'status') {
    Auth::requireCapability('invoices.edit');
    $status = trim((string) ($_POST['status'] ?? ''));
    if (!in_array($status, invoice_status_options(), true)) {
        $_SESSION['flash_error'] = 'Invalid status.';
        header('Location: ' . $redirect);
        exit;
    }
    $repo->updateStatus($id, $status);
    ActivityLog::record('invoice.status_changed', 'invoice', $id, ['status' => $status]);
    $_SESSION['flash_success'] = 'Status updated to ' . invoice_status_label($status) . '.';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'record_payment') {
    Auth::requireCapability('invoices.edit');
    $balance = invoice_amount_due($invoice);
    $amountRaw = str_replace([',', '$', ' '], '', (string) ($_POST['amount'] ?? ''));
    $amount = is_numeric($amountRaw) ? invoice_money((float) $amountRaw) : 0.0;
    $paymentDate = trim((string) ($_POST['payment_date'] ?? ''));
    $method = trim((string) ($_POST['method'] ?? ''));
    $reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 191);
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);

    $error = null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
    if ($amount <= 0) {
        $error = 'Enter a payment amount greater than zero.';
    } elseif ($amount - $balance > 0.004) {
        $error = 'Payment of ' . invoice_format_money($amount) . ' is more than the balance due ('
            . invoice_format_money($balance) . ').';
    } elseif (!$date || $date->format('Y-m-d') !== $paymentDate) {
        $error = 'Enter a valid payment date.';
    } elseif ($method !== '' && !array_key_exists($method, invoice_payment_methods())) {
        $error = 'Choose a valid payment method.';
    }

    if ($error !== null) {
        $_SESSION['flash_error'] = $error;
        $_SESSION['invoice_payment_old'] = [
            'amount' => (string) ($_POST['amount'] ?? ''),
            'payment_date' => $paymentDate,
            'method' => $method,
            'reference' => $reference,
            'note' => $note,
        ];
        header('Location: ' . $redirect . '#payments');
        exit;
    }

    $user = Auth::currentUser();
    $paid = $repo->addPayment($id, [
        'amount' => $amount,
        'payment_date' => $paymentDate,
        'method' => $method,
        'reference' => $reference,
        'note' => $note,
        'recorded_by_user_id' => $user['id'] ?? null,
        'recorded_by_name' => (string) ($user['name'] ?? 'Admin'),
    ]);
    $newBalance = invoice_amount_due(array_merge($invoice, ['amount_paid' => $paid]));

    $statusNote = '';
    if ($newBalance <= 0 && ($invoice['status'] ?? '') !== 'paid') {
        $repo->updateStatus($id, 'paid');
        $statusNote = ' Invoice is now fully paid.';
    }

    ActivityLog::record('invoice.payment_recorded', 'invoice', $id, [
        'number' => (string) ($invoice['invoice_number'] ?? ''),
        'amount' => $amount,
        'method' => $method,
        'balance' => $newBalance,
    ]);
    $_SESSION['flash_success'] = 'Payment of ' . invoice_format_money($amount) . ' recorded. Balance due: '
        . invoice_format_money($newBalance) . '.' . $statusNote;
    header('Location: ' . $redirect . '#payments');
    exit;
}

if ($action === 'delete_payment') {
    Auth::requireCapability('invoices.edit');
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $payment = $paymentId > 0 ? $repo->findPayment($paymentId, $id) : null;
    if (!$payment) {
        $_SESSION['flash_error'] = 'Payment not found.';
        header('Location: ' . $redirect . '#payments');
        exit;
    }

    $paid = $repo->deletePayment($paymentId, $id);
    $newBalance = invoice_raw_amount_due(array_merge($invoice, ['amount_paid' => $paid]));

    $statusNote = '';
    if ($newBalance > 0 && ($invoice['status'] ?? '') === 'paid') {
        $repo->updateStatus($id, 'sent');
        $statusNote = ' Status changed from Paid to Sent.';
    }

    ActivityLog::record('invoice.payment_deleted', 'invoice', $id, [
        'number' => (string) ($invoice['invoice_number'] ?? ''),
        'amount' => (float) $payment['amount'],
        'balance' => $newBalance,
    ]);
    $_SESSION['flash_success'] = 'Payment of ' . invoice_format_money((float) $payment['amount'])
        . ' removed. Balance due: ' . invoice_format_money($newBalance) . '.' . $statusNote;
    header('Location: ' . $redirect . '#payments');
    exit;
}

if ($action === 'send_email') {
    Auth::requireCapability('invoices.send');
    $to = trim((string) ($_POST['to_email'] ?? ''));
    $message = trim((string) ($_POST['email_message'] ?? ''));
    $items = $repo->itemsForInvoice($id);
    $result = invoice_send_to_client($invoice, $items, $to, $message !== '' ? $message : null);

    if (!$result['ok']) {
        $_SESSION['flash_error'] = $result['error'] ?? 'Failed to send invoice.';
        $_SESSION['invoice_send_old'] = [
            'to_email' => $to,
            'email_message' => $message,
        ];
        header('Location: ' . $redirect . '#send-invoice');
        exit;
    }

    $previousStatus = (string) ($invoice['status'] ?? '');
    $markSent = in_array($previousStatus, ['draft', 'approved'], true);
    if ($markSent) {
        $repo->updateStatus($id, 'sent');
    }

    ActivityLog::record('invoice.emailed', 'invoice', $id, [
        'number' => (string) ($invoice['invoice_number'] ?? ''),
        'to' => $result['to'] ?? $to,
        'status' => $markSent ? 'sent' : $previousStatus,
    ]);
    $_SESSION['flash_success'] = 'Invoice PDF sent to ' . ($result['to'] ?? $to) . '.'
        . ($markSent ? ' Status set to Sent.' : '');
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
