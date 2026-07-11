<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$stored = basename((string) ($_GET['f'] ?? ''));
$path = email_asset_path($stored);
if ($path === null) {
    http_response_code(404);
    exit('Not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
if (!in_array($mime, email_assets_allowed_mimes(), true)) {
    http_response_code(403);
    exit('Forbidden.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $stored) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000, immutable');

readfile($path);
exit;
