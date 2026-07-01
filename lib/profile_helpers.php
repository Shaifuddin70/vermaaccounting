<?php

declare(strict_types=1);

const USER_AVATAR_MAX_BYTES = 2 * 1024 * 1024;

function user_avatar_dir(): string
{
    return UPLOADS_DIR . '/avatars';
}

function ensure_avatar_dir(): void
{
    $dir = user_avatar_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'VA';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

/** @return array{name: string, email: string, avatar_path: string}> */
function config_admin_profile(): array
{
    $repo = new SettingsRepository();
    $stored = $repo->get('config_admin_profile', []);
    $profile = is_array($stored) ? $stored : [];

    return [
        'name' => trim((string) ($profile['name'] ?? 'Admin')) ?: 'Admin',
        'email' => strtolower(trim((string) ($profile['email'] ?? ''))),
        'avatar_path' => trim((string) ($profile['avatar_path'] ?? '')),
    ];
}

/** @param array{name?: string, email?: string, avatar_path?: string} $profile */
function save_config_admin_profile(array $profile): void
{
    $repo = new SettingsRepository();
    $current = config_admin_profile();
    $repo->set('config_admin_profile', [
        'name' => trim((string) ($profile['name'] ?? $current['name'])) ?: 'Admin',
        'email' => strtolower(trim((string) ($profile['email'] ?? $current['email']))),
        'avatar_path' => trim((string) ($profile['avatar_path'] ?? $current['avatar_path'])),
    ]);
}

/** @return array<string, mixed> */
function profile_for_current_user(): array
{
    $user = Auth::currentUser();
    if (!$user) {
        return [];
    }

    if (!empty($user['is_config_admin'])) {
        $profile = config_admin_profile();
        return [
            'is_config_admin' => true,
            'id' => null,
            'name' => $profile['name'],
            'email' => $profile['email'],
            'role' => 'admin',
            'avatar_path' => $profile['avatar_path'],
            'reference_code' => null,
            'username' => app_config()['admin_username'] ?? 'admin',
        ];
    }

    $id = (int) ($user['id'] ?? 0);
    if ($id < 1) {
        return $user;
    }

    $dbUser = (new UserRepository())->find($id);
    if (!$dbUser) {
        return $user;
    }

    return [
        'is_config_admin' => false,
        'id' => $id,
        'name' => (string) ($dbUser['name'] ?? ''),
        'email' => (string) ($dbUser['email'] ?? ''),
        'role' => (string) ($dbUser['role'] ?? ''),
        'avatar_path' => (string) ($dbUser['avatar_path'] ?? ''),
        'reference_code' => (string) ($dbUser['reference_code'] ?? ''),
        'username' => (string) ($dbUser['email'] ?? ''),
    ];
}

function user_avatar_disk_path(string $avatarPath): string
{
    return UPLOADS_DIR . '/' . ltrim($avatarPath, '/');
}

function user_avatar_url(?array $user = null): ?string
{
    $user ??= Auth::currentUser();
    if (!$user) {
        return null;
    }

    $path = trim((string) ($user['avatar_path'] ?? ''));
    if ($path === '') {
        return null;
    }

    $disk = user_avatar_disk_path($path);
    if (!is_file($disk)) {
        return null;
    }

    $version = (string) (@filemtime($disk) ?: time());
    return '/admin/avatar?v=' . rawurlencode($version);
}

/**
 * @param array{error:int,name:string,tmp_name:string,size:int,type:string} $upload
 */
function store_user_avatar_upload(array $upload, string $ownerKey): ?string
{
    ensure_avatar_dir();

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Avatar upload failed.');
    }
    if ((int) ($upload['size'] ?? 0) > USER_AVATAR_MAX_BYTES) {
        throw new RuntimeException('Profile image must be 2 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($upload['tmp_name']) ?: ($upload['type'] ?? '');
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Profile image must be a JPEG, PNG, GIF, or WebP file.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $safeOwner = preg_replace('/[^a-zA-Z0-9_-]/', '', $ownerKey) ?: 'user';
    $filename = $safeOwner . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $relative = 'avatars/' . $filename;
    $dest = user_avatar_disk_path($relative);

    if (!move_uploaded_file($upload['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save profile image.');
    }

    return $relative;
}

function delete_user_avatar_file(?string $avatarPath): void
{
    $path = trim((string) $avatarPath);
    if ($path === '') {
        return;
    }
    $disk = user_avatar_disk_path($path);
    if (is_file($disk)) {
        unlink($disk);
    }
}
