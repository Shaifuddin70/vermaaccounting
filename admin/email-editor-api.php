<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (!Auth::verifyCsrf($csrf)) {
    json_response(['ok' => false, 'error' => 'Invalid request token.'], 403);
}

$action = (string) ($_POST['action'] ?? 'upload_image');

if ($action === 'upload_template_image') {
    if (empty($_FILES['image'])) {
        json_response(['ok' => false, 'error' => 'No image uploaded.'], 422);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    try {
        $asset = canada_holiday_email_image_upload($name, $_FILES['image']);
    } catch (RuntimeException $e) {
        json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
    }

    ActivityLog::record('email_template_image.uploaded', 'email_template_image', 0, [
        'filename' => $asset['filename'],
        'slug' => $asset['slug'],
        'name' => $name,
    ]);

    json_response([
        'ok' => true,
        'url' => $asset['url'],
        'filename' => $asset['filename'],
        'slug' => $asset['slug'],
    ]);
}

if ($action !== 'upload_image') {
    json_response(['ok' => false, 'error' => 'Unknown action.'], 400);
}

if (empty($_FILES['image'])) {
    json_response(['ok' => false, 'error' => 'No image uploaded.'], 422);
}

try {
    $asset = email_asset_upload($_FILES['image']);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => app_safe_error_message($e)], 422);
}

ActivityLog::record('email_asset.uploaded', 'email_asset', 0, [
    'stored' => $asset['stored'],
    'name' => $asset['name'],
]);

json_response([
    'ok' => true,
    'url' => $asset['url'],
    'stored' => $asset['stored'],
    'name' => $asset['name'],
]);
