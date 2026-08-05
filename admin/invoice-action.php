<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

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
$invoice = $id > 0 ? $repo->find($id) : null;

if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}

$redirect = '/admin/invoice-view?id=' . $id;

if ($action === 'delete') {
    $number = (string) ($invoice['invoice_number'] ?? '');
    $repo->delete($id);
    ActivityLog::record('invoice.deleted', 'invoice', $id, ['number' => $number]);
    $_SESSION['flash_success'] = 'Invoice #' . $number . ' deleted.';
    header('Location: /admin/invoices');
    exit;
}

if ($action === 'status') {
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

if ($action === 'send_email') {
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

    if (($invoice['status'] ?? '') === 'draft') {
        $repo->updateStatus($id, 'sent');
    }

    ActivityLog::record('invoice.emailed', 'invoice', $id, [
        'number' => (string) ($invoice['invoice_number'] ?? ''),
        'to' => $result['to'] ?? $to,
    ]);
    $_SESSION['flash_success'] = 'Invoice PDF sent to ' . ($result['to'] ?? $to) . '.';
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
