<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('team.manage');

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
$role     = isset(permission_roles()[$_POST['role'] ?? '']) ? (string) $_POST['role'] : 'reviewer';
$status   = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
$referenceCode = trim((string) ($_POST['reference_code'] ?? ''));
$customPermissions = ($_POST['permissions_custom'] ?? '') === '1' || ($_POST['permissions_custom'] ?? '') === 'on';
$postedPermissions = $_POST['permissions'] ?? [];
if (!is_array($postedPermissions)) {
    $postedPermissions = [];
}

$errors = [];
$actorIsSuper = Auth::isSuperAdmin();
$targetUser = $isNew ? null : (new UserRepository())->find((int) $editId);

if (permission_is_privileged_role($role) && !$actorIsSuper) {
    $errors[] = 'Only a super admin can give someone the admin or super admin role.';
}
if ($targetUser && permission_is_privileged_role((string) ($targetUser['role'] ?? '')) && !$actorIsSuper) {
    $errors[] = 'Only a super admin can change admin and super admin accounts.';
}

// Super admins always have full access. Otherwise store the posted checkbox set; a form without
// the permission panel keeps the member's existing permissions unless their role changes.
$permissionsJson = null;
if ($role !== 'super_admin') {
    if (!array_key_exists('permissions_custom', $_POST) && !array_key_exists('permissions', $_POST)) {
        if ($targetUser && ($targetUser['role'] ?? '') === $role) {
            $permissionsJson = permission_encode_for_storage(permission_parse_stored($targetUser['permissions_json'] ?? null));
        }
    } elseif ($customPermissions || $postedPermissions !== []) {
        $permissionsJson = permission_encode_for_storage($postedPermissions);
    }
}
if (!$actorIsSuper && $role !== 'super_admin') {
    // Other managers can only grant capabilities they hold themselves, never team access or
    // website content; website content the member already has is left as a super admin set it.
    $restricted = permission_restricted_keys();
    $filtered = array_values(array_filter(
        permission_parse_stored($permissionsJson) ?? [],
        static fn(string $key): bool => $key !== 'team.manage' && !in_array($key, $restricted, true) && Auth::can($key)
    ));
    $kept = array_values(array_intersect(permission_parse_stored($targetUser['permissions_json'] ?? null) ?? [], $restricted));
    if ($kept !== []) {
        $filtered = array_merge($filtered ?: permission_defaults_for_role($role), $kept);
    }
    $permissionsJson = $filtered === [] && $permissionsJson === null ? null : permission_encode_for_storage($filtered);
}
if ($permissionsJson !== null) {
    $chosen = permission_parse_stored($permissionsJson) ?? [];
    $defaults = permission_defaults_for_role($role);
    sort($chosen);
    sort($defaults);
    if ($chosen === $defaults) {
        $permissionsJson = null;
    }
}

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
    $_SESSION['user_edit_old']    = compact('name', 'email', 'role', 'status', 'referenceCode', 'customPermissions', 'postedPermissions');
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
        'permissions_json' => $permissionsJson,
    ]);
    ActivityLog::record('user.created', 'user', $newId, [
        'name' => $name, 'email' => $email, 'role' => $role,
        'custom_permissions' => $customPermissions,
    ]);
    $successMsg = "Team member {$name} created successfully.";
} else {
    $data = [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'status' => $status,
        'reference_code' => $referenceCode,
        'permissions_json' => $permissionsJson,
    ];
    if ($password !== '') {
        $data['password'] = $password;
    }
    $userRepo->update($editId, $data);
    ActivityLog::record('user.updated', 'user', $editId, [
        'name' => $name, 'email' => $email, 'role' => $role, 'status' => $status,
        'custom_permissions' => $customPermissions,
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
