<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/clients');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request. Please try again.';
    header('Location: /admin/clients');
    exit;
}

$clientRepo = new ClientRepository();
$result = $clientRepo->syncFromSubmissions();

ActivityLog::record('clients.synced', 'clients', null, $result);

$parts = [];
if ($result['clients_created'] > 0) {
    $parts[] = $result['clients_created'] . ' new client' . ($result['clients_created'] === 1 ? '' : 's');
}
if ($result['submissions_linked'] > 0) {
    $parts[] = $result['submissions_linked'] . ' submission' . ($result['submissions_linked'] === 1 ? '' : 's') . ' linked';
}
if ($result['skipped'] > 0) {
    $parts[] = $result['skipped'] . ' skipped (no name/email)';
}

$_SESSION['flash_success'] = $parts
    ? 'Sync complete: ' . implode(', ', $parts) . '.'
    : 'Sync complete. Everything is already up to date.';

header('Location: /admin/clients');
exit;
