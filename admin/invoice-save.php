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

$editId = isset($_POST['id']) ? (int) $_POST['id'] : null;
$repo = new InvoiceRepository();
$existing = $editId ? $repo->find($editId) : null;

if ($editId && !$existing) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}

$invoiceNumber = trim((string) ($_POST['invoice_number'] ?? ''));
$clientIdRaw = trim((string) ($_POST['client_id'] ?? ''));
$clientId = $clientIdRaw !== '' ? (int) $clientIdRaw : null;
$invoiceDate = trim((string) ($_POST['invoice_date'] ?? ''));
$dueDate = trim((string) ($_POST['due_date'] ?? ''));
$discountPercent = invoice_parse_discount_percent_input($_POST['discount_percent'] ?? 0);
$discountFlat = invoice_parse_discount_flat_input($_POST['discount_flat'] ?? 0);
$discountPercentLabel = invoice_sanitize_discount_label((string) ($_POST['discount_percent_label'] ?? ''));
$discountFlatLabel = invoice_sanitize_discount_label((string) ($_POST['discount_flat_label'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'draft'));
$notes = trim((string) ($_POST['notes'] ?? ''));

$items = invoice_parse_items_from_post($_POST);

$errors = [];
if ($invoiceNumber === '') {
    $errors[] = 'Invoice number is required.';
} elseif ($repo->invoiceNumberExists($invoiceNumber, $editId)) {
    $errors[] = 'That invoice number is already in use.';
}
if ($invoiceDate === '' || !DateTimeImmutable::createFromFormat('Y-m-d', $invoiceDate)) {
    $errors[] = 'Choose a valid invoice date.';
}
if ($dueDate === '' || !DateTimeImmutable::createFromFormat('Y-m-d', $dueDate)) {
    $errors[] = 'Choose a valid due date.';
}
if (!in_array($status, invoice_status_options(), true)) {
    $errors[] = 'Invalid status.';
}
if ($discountPercent < 0 || $discountPercent > 100) {
    $errors[] = 'Discount must be between 0 and 100%.';
}
if ($discountFlat < 0) {
    $errors[] = 'Flat discount cannot be negative.';
}
if ($items === []) {
    $errors[] = 'Select at least one service or add a custom line item.';
}

$billToName = trim((string) ($_POST['bill_to_name'] ?? ''));
$billToCompany = trim((string) ($_POST['bill_to_company'] ?? ''));
if ($billToName === '' && $billToCompany === '') {
    $errors[] = 'Enter a bill-to company or contact name.';
}

if ($clientId) {
    $client = (new ClientRepository())->find($clientId);
    if (!$client) {
        $errors[] = 'Selected client was not found.';
        $clientId = null;
    }
}

if ($errors !== []) {
    $_SESSION['invoice_edit_errors'] = $errors;
    $_SESSION['invoice_edit_old'] = $_POST;
    header('Location: /admin/invoice-edit' . ($editId ? '?id=' . $editId : ''));
    exit;
}

$user = Auth::currentUser();
$data = [
    'invoice_number' => $invoiceNumber,
    'client_id' => $clientId,
    'bill_to_company' => $billToCompany,
    'bill_to_name' => $billToName,
    'bill_to_street' => trim((string) ($_POST['bill_to_street'] ?? '')),
    'bill_to_city' => trim((string) ($_POST['bill_to_city'] ?? '')),
    'bill_to_province' => trim((string) ($_POST['bill_to_province'] ?? '')),
    'bill_to_postal' => trim((string) ($_POST['bill_to_postal'] ?? '')),
    'bill_to_country' => trim((string) ($_POST['bill_to_country'] ?? 'Canada')),
    'invoice_date' => $invoiceDate,
    'due_date' => $dueDate,
    'currency' => 'CAD',
    'discount_percent' => $discountPercent,
    'discount_flat' => $discountFlat,
    'discount_percent_label' => $discountPercentLabel,
    'discount_flat_label' => $discountFlatLabel,
    'notes' => $notes,
    'status' => $status,
];

if ($editId) {
    $repo->update($editId, $data, $items);
    ActivityLog::record('invoice.updated', 'invoice', $editId, ['number' => $invoiceNumber]);
    $_SESSION['flash_success'] = 'Invoice #' . $invoiceNumber . ' saved.';
    header('Location: /admin/invoice-view?id=' . $editId);
    exit;
}

$data['created_by_user_id'] = $user['id'] ?? null;
$data['created_by_name'] = (string) ($user['name'] ?? 'Admin');
$id = $repo->create($data, $items);
ActivityLog::record('invoice.created', 'invoice', $id, ['number' => $invoiceNumber]);
$_SESSION['flash_success'] = 'Invoice #' . $invoiceNumber . ' created.';
header('Location: /admin/invoice-view?id=' . $id);
exit;
