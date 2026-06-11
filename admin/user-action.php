<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'Invalid session.']); exit; }
    exit('Invalid session.');
}

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$userRepo = new UserRepository();
$user = $userRepo->find($id);

if (!$user || $id < 1) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'User not found.']); exit; }
    header('Location: /admin/users');
    exit;
}

if ($action === 'deactivate') {
    $userRepo->setStatus($id, 'inactive');
    ActivityLog::record('user.deactivated', 'user', $id, ['name' => $user['name']]);
    $_SESSION['flash_success'] = $user['name'] . ' has been deactivated.';
} elseif ($action === 'activate') {
    $userRepo->setStatus($id, 'active');
    ActivityLog::record('user.activated', 'user', $id, ['name' => $user['name']]);
    $_SESSION['flash_success'] = $user['name'] . ' has been activated.';
}

if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
header('Location: /admin/users');
exit;
