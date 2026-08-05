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

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$repo = new InvoiceServiceRepository();
$service = $id > 0 ? $repo->find($id) : null;

if (!$service) {
    $_SESSION['flash_error'] = 'Service not found.';
    header('Location: /admin/invoice-services');
    exit;
}

if ($action === 'delete') {
    $repo->delete($id);
    ActivityLog::record('invoice_service.deleted', 'invoice_service', $id, ['name' => $service['name'] ?? '']);
    $_SESSION['flash_success'] = 'Service deleted.';
} elseif ($action === 'toggle') {
    $repo->update($id, ['is_active' => empty($service['is_active'])]);
    $_SESSION['flash_success'] = 'Service updated.';
}

header('Location: /admin/invoice-services');
exit;
