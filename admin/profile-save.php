<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/profile');
    exit;
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['profile_errors'] = ['Invalid request. Please try again.'];
    header('Location: /admin/profile');
    exit;
}

$profile = profile_for_current_user();
$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$removeAvatar = !empty($_POST['remove_avatar']);
$isConfigAdmin = !empty($profile['is_config_admin']);

$errors = [];
if ($name === '') {
    $errors[] = 'Full name is required.';
}
if (!$isConfigAdmin) {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
} elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address or leave it blank.';
}

if (!$isConfigAdmin && $password !== '' && strlen($password) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}

$userRepo = new UserRepository();
if (!$isConfigAdmin && $email !== '') {
    $userId = (int) ($profile['id'] ?? 0);
    if ($userId > 0 && $userRepo->emailExists($email, $userId)) {
        $errors[] = 'That email address is already in use.';
    }
}

if ($errors) {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['profile_old'] = compact('name', 'email');
    header('Location: /admin/profile');
    exit;
}

$currentAvatar = (string) ($profile['avatar_path'] ?? '');
$newAvatar = $currentAvatar;

try {
    if ($removeAvatar) {
        delete_user_avatar_file($currentAvatar);
        $newAvatar = '';
    }

    if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $ownerKey = $isConfigAdmin
            ? 'configadmin'
            : 'user' . (int) ($profile['id'] ?? 0);
        $stored = store_user_avatar_upload($_FILES['avatar'], $ownerKey);
        if ($stored !== null) {
            if ($currentAvatar !== '' && $currentAvatar !== $stored) {
                delete_user_avatar_file($currentAvatar);
            }
            $newAvatar = $stored;
        }
    }
} catch (Throwable $e) {
    $_SESSION['profile_errors'] = [$e->getMessage()];
    $_SESSION['profile_old'] = compact('name', 'email');
    header('Location: /admin/profile');
    exit;
}

if ($isConfigAdmin) {
    save_config_admin_profile([
        'name' => $name,
        'email' => $email,
        'avatar_path' => $newAvatar,
    ]);
} else {
    $userId = (int) ($profile['id'] ?? 0);
    if ($userId < 1) {
        $_SESSION['profile_errors'] = ['Could not update profile.'];
        header('Location: /admin/profile');
        exit;
    }

    $update = [
        'name' => $name,
        'email' => $email,
        'avatar_path' => $newAvatar !== '' ? $newAvatar : null,
    ];
    if ($password !== '') {
        $update['password'] = $password;
    }
    if (!$userRepo->updateProfile($userId, $update)) {
        $_SESSION['profile_errors'] = ['Could not save profile.'];
        header('Location: /admin/profile');
        exit;
    }

    ActivityLog::record('profile.updated', 'user', $userId, ['name' => $name]);
}

Auth::syncProfileToSession([
    'name' => $name,
    'email' => $email,
    'avatar_path' => $newAvatar,
]);

header('Location: /admin/profile?saved=1');
exit;
