<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/invoice-services');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: /admin/invoice-services');
    exit;
}

$editId = isset($_POST['id']) ? (int) $_POST['id'] : null;
$repo = new InvoiceServiceRepository();
$existing = $editId ? $repo->find($editId) : null;

if ($editId && !$existing) {
    $_SESSION['flash_error'] = 'Service not found.';
    header('Location: /admin/invoice-services');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$unitPrice = (float) ($_POST['unit_price'] ?? 0);
$sortOrder = (int) ($_POST['sort_order'] ?? 0);
$isActive = !empty($_POST['is_active']);

$errors = [];
if ($name === '') {
    $errors[] = 'Service name is required.';
}
if ($unitPrice < 0) {
    $errors[] = 'Price cannot be negative.';
}

if ($errors !== []) {
    $_SESSION['invoice_service_errors'] = $errors;
    $_SESSION['invoice_service_old'] = $_POST;
    header('Location: /admin/invoice-service-edit' . ($editId ? '?id=' . $editId : ''));
    exit;
}

$data = [
    'name' => $name,
    'description' => $description,
    'unit_price' => $unitPrice,
    'sort_order' => $sortOrder,
    'is_active' => $isActive,
];

if ($editId) {
    $repo->update($editId, $data);
    ActivityLog::record('invoice_service.updated', 'invoice_service', $editId, ['name' => $name]);
    $_SESSION['flash_success'] = 'Service updated.';
    header('Location: /admin/invoice-service-edit?id=' . $editId);
    exit;
}

$id = $repo->create($data);
ActivityLog::record('invoice_service.created', 'invoice_service', $id, ['name' => $name]);
$_SESSION['flash_success'] = 'Service added.';
header('Location: /admin/invoice-services');
exit;
