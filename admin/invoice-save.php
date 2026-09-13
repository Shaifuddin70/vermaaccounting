<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('invoices.manage');

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
$advanceAmount = invoice_parse_discount_flat_input($_POST['advance_amount'] ?? 0);
$dueAdjustment = invoice_parse_signed_money_input($_POST['due_adjustment'] ?? 0);
$advanceLabel = invoice_sanitize_discount_label((string) ($_POST['advance_label'] ?? ''));
$dueAdjustmentLabel = invoice_sanitize_discount_label((string) ($_POST['due_adjustment_label'] ?? ''));
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
if ($advanceAmount < 0) {
    $errors[] = 'Advance cannot be negative.';
}
if ($items === []) {
    $errors[] = 'Select at least one service or add a custom line item.';
}

$billToName = trim((string) ($_POST['bill_to_name'] ?? ''));
$billToCompany = trim((string) ($_POST['bill_to_company'] ?? ''));
$billToEmail = strtolower(trim((string) ($_POST['bill_to_email'] ?? '')));
$billToPhone = trim((string) ($_POST['bill_to_phone'] ?? ''));
if ($billToName === '' && $billToCompany === '') {
    $errors[] = 'Enter a bill-to company or contact name.';
}
if ($billToEmail !== '' && !filter_var($billToEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid bill-to email address.';
}

if ($clientId) {
    $client = (new ClientRepository())->find($clientId);
    if (!$client) {
        $errors[] = 'Selected client was not found.';
        $clientId = null;
    } elseif (!partner_has_client_access((int) $clientId)) {
        $errors[] = 'You can only invoice clients linked to your submissions.';
        $clientId = null;
    }
}

$partnerId = partner_user_id();
if ($partnerId !== null && (!$clientId || $clientId < 1)) {
    $errors[] = 'Partners must select one of their linked clients for the invoice.';
}

if ($editId) {
    $existingInvoice = $repo->find($editId, $partnerId);
    if (!$existingInvoice) {
        $_SESSION['flash_error'] = 'Invoice not found.';
        header('Location: /admin/invoices');
        exit;
    }
    assert_invoice_access($existingInvoice);
}

if ($errors !== []) {
    $_SESSION['invoice_edit_errors'] = $errors;
    $_SESSION['invoice_edit_old'] = $_POST;
    header('Location: /admin/invoice-edit' . ($editId ? '?id=' . $editId : ''));
    exit;
}

if ($partnerId !== null) {
    // Keep the selected linked client; do not auto-create unscoped clients.
    $clientLink = ['client_id' => $clientId, 'created' => false];
} else {
    $clientLink = invoice_ensure_client_from_bill_to(
        $clientId,
        $billToName,
        $billToCompany,
        $billToEmail,
        $billToPhone
    );
}
$clientId = $clientLink['client_id'];
$clientCreated = !empty($clientLink['created']);

$user = Auth::currentUser();
$data = [
    'invoice_number' => $invoiceNumber,
    'client_id' => $clientId,
    'bill_to_company' => $billToCompany,
    'bill_to_name' => $billToName,
    'bill_to_email' => $billToEmail,
    'bill_to_phone' => $billToPhone,
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
    'advance_amount' => $advanceAmount,
    'due_adjustment' => $dueAdjustment,
    'advance_label' => $advanceLabel,
    'due_adjustment_label' => $dueAdjustmentLabel,
    'notes' => $notes,
    'status' => $status,
];

$clientNote = $clientCreated
    ? ' Client added to the directory.'
    : '';

if ($editId) {
    $repo->update($editId, $data, $items);
    ActivityLog::record('invoice.updated', 'invoice', $editId, [
        'number' => $invoiceNumber,
        'client_id' => $clientId,
        'client_created' => $clientCreated,
    ]);
    $_SESSION['flash_success'] = 'Invoice #' . $invoiceNumber . ' saved.' . $clientNote;
    header('Location: /admin/invoice-view?id=' . $editId);
    exit;
}

$data['created_by_user_id'] = $user['id'] ?? null;
$data['created_by_name'] = (string) ($user['name'] ?? 'Admin');
$id = $repo->create($data, $items);
ActivityLog::record('invoice.created', 'invoice', $id, [
    'number' => $invoiceNumber,
    'client_id' => $clientId,
    'client_created' => $clientCreated,
]);
$_SESSION['flash_success'] = 'Invoice #' . $invoiceNumber . ' created.' . $clientNote;
header('Location: /admin/invoice-view?id=' . $id);
exit;
