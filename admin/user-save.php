<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
    || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['errors' => ['Invalid session. Please refresh the page.']]);
        exit;
    }
    http_response_code(403);
    exit('Invalid session.');
}

$editId   = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isNew    = ($editId === null);
$name     = trim($_POST['name'] ?? '');
$email    = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$role     = in_array($_POST['role'] ?? '', ['admin', 'reviewer', 'partner'], true) ? $_POST['role'] : 'reviewer';
$status   = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
$referenceCode = trim((string) ($_POST['reference_code'] ?? ''));

$errors = [];

if ($name === '') {
    $errors[] = 'Name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if ($isNew && strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if (!$isNew && $password !== '' && strlen($password) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}

$userRepo = new UserRepository();

if ($email !== '' && $userRepo->emailExists($email, $editId)) {
    $errors[] = 'That email address is already in use.';
}

if ($role === 'partner') {
    if ($referenceCode === '') {
        $errors[] = 'Reference code is required for partners.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]{2,32}$/', $referenceCode)) {
        $errors[] = 'Reference code must be 2–32 characters (letters, numbers, hyphen, underscore).';
    } elseif ($userRepo->referenceCodeExists($referenceCode, $editId)) {
        $errors[] = 'That reference code is already in use.';
    }
} else {
    $referenceCode = '';
}

if ($errors) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['errors' => $errors]);
        exit;
    }
    $_SESSION['user_edit_errors'] = $errors;
    $_SESSION['user_edit_old']    = compact('name', 'email', 'role', 'status', 'referenceCode');
    $back = $isNew ? '/admin/user-edit' : '/admin/user-edit?id=' . $editId;
    header('Location: ' . $back);
    exit;
}

if ($isNew) {
    $newId = $userRepo->create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => $role,
        'status' => $status,
        'reference_code' => $referenceCode,
    ]);
    ActivityLog::record('user.created', 'user', $newId, [
        'name' => $name, 'email' => $email, 'role' => $role,
    ]);
    $successMsg = "Team member {$name} created successfully.";
} else {
    $data = [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'status' => $status,
        'reference_code' => $referenceCode,
    ];
    if ($password !== '') {
        $data['password'] = $password;
    }
    $userRepo->update($editId, $data);
    ActivityLog::record('user.updated', 'user', $editId, [
        'name' => $name, 'email' => $email, 'role' => $role, 'status' => $status,
    ]);
    $successMsg = "Team member {$name} updated.";
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'message' => $successMsg]);
    exit;
}

$_SESSION['flash_success'] = $successMsg;
header('Location: /admin/users');
exit;
