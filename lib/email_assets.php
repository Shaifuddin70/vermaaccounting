<?php

declare(strict_types=1);

function email_assets_allowed_mimes(): array
{
    return [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
}

function email_asset_max_bytes(): int
{
    return 5 * 1024 * 1024;
}

function email_asset_is_valid_stored_name(string $stored): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}\.(jpe?g|png|gif|webp)$/i', $stored);
}

function email_asset_public_url(string $stored): string
{
    $base = app_base_url();
    if ($base === '') {
        $base = 'https://vermaaccounting.ca';
    }
    return $base . '/email-asset/' . rawurlencode($stored);
}

function email_asset_path(string $stored): ?string
{
    if (!email_asset_is_valid_stored_name($stored)) {
        return null;
    }

    $path = EMAIL_ASSETS_DIR . '/' . $stored;
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $realBase = realpath(EMAIL_ASSETS_DIR);
    $realPath = realpath($path);
    if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $realPath;
}

/**
 * @param array{name?: string, tmp_name?: string, size?: int, error?: int} $upload
 * @return array{stored: string, url: string, name: string, mime: string, size: int}
 */
function email_asset_upload(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    $size = (int) ($upload['size'] ?? 0);
    if ($size < 1 || $size > email_asset_max_bytes()) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }

    $tmp = (string) ($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: 'application/octet-stream';
    if (!in_array($mime, email_assets_allowed_mimes(), true)) {
        throw new RuntimeException('Only JPG, PNG, GIF, and WebP images are allowed.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'img',
    };

    if (!is_dir(EMAIL_ASSETS_DIR)) {
        mkdir(EMAIL_ASSETS_DIR, 0755, true);
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = EMAIL_ASSETS_DIR . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    $originalName = trim((string) ($upload['name'] ?? 'image'));
    if ($originalName === '') {
        $originalName = 'image';
    }

    return [
        'stored' => $stored,
        'url' => email_asset_public_url($stored),
        'name' => $originalName,
        'mime' => $mime,
        'size' => $size,
    ];
}
