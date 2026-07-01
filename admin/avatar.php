<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$profile = profile_for_current_user();
$path = trim((string) ($profile['avatar_path'] ?? ''));
if ($path === '') {
    http_response_code(404);
    exit('No profile image.');
}

$disk = user_avatar_disk_path($path);
$realBase = realpath(UPLOADS_DIR);
$realPath = realpath($disk);
if ($realBase === false || $realPath === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    exit('Access denied.');
}

if (!is_file($realPath) || !is_readable($realPath)) {
    http_response_code(404);
    exit('Image not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($realPath) ?: 'image/jpeg';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: inline; filename="avatar"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($realPath);
exit;
