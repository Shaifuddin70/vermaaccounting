<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$slug = trim($_POST['form_slug'] ?? '');
if ($slug === '') {
    json_response(['error' => 'Missing form'], 400);
}

$repo = new FormRepository();
$form = $repo->findBySlug($slug, true);
if (!$form) {
    json_response(['error' => 'Form not found or not published'], 404);
}

$schema = $repo->decodeSchema($form);
$config = app_config();
$maxBytes = (int) ($config['max_upload_bytes'] ?? 10485760);
$allowedMimes = $config['allowed_upload_mimes'] ?? [];

$data = [];
$filesMeta = [];
$errors = [];

foreach ($schema['fields'] as $field) {
    $type = $field['type'];
    $name = $field['name'];
    $id = $field['id'];

    if (in_array($type, ['heading', 'paragraph'], true)) {
        continue;
    }

    if ($type === 'checkbox') {
        $raw = $_POST[$name] ?? [];
        $data[$name] = is_array($raw) ? array_map('strval', $raw) : [];
        if ($field['required'] && count($data[$name]) === 0) {
            $errors[] = $field['label'] . ' is required.';
        }
        continue;
    }

    if (in_array($type, ['file', 'image'], true)) {
        $fileKey = $name;
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            if ($field['required']) {
                $errors[] = $field['label'] . ' is required.';
            }
            continue;
        }

        $upload = $_FILES[$fileKey];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for ' . $field['label'];
            continue;
        }
        if ($upload['size'] > $maxBytes) {
            $errors[] = $field['label'] . ' exceeds max file size.';
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($upload['tmp_name']) ?: $upload['type'];
        if ($type === 'image' && !str_starts_with($mime, 'image/')) {
            $errors[] = $field['label'] . ' must be an image.';
            continue;
        }
        if ($allowedMimes && !in_array($mime, $allowedMimes, true)) {
            $errors[] = $field['label'] . ' file type is not allowed.';
            continue;
        }

        $ext = pathinfo($upload['name'], PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $destDir = UPLOADS_DIR . '/' . $form['id'];
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . $stored;
        if (!move_uploaded_file($upload['tmp_name'], $dest)) {
            $errors[] = 'Could not save ' . $field['label'];
            continue;
        }

        $data[$name] = $stored;
        $filesMeta[] = [
            'field_id' => $id,
            'stored_name' => $form['id'] . '/' . $stored,
            'original_name' => $upload['name'],
            'mime' => $mime,
            'size' => (int) $upload['size'],
        ];
        continue;
    }

    $value = trim((string) ($_POST[$name] ?? ''));
    if ($type === 'yes_no') {
        $value = in_array($value, ['yes', 'no'], true) ? $value : '';
    }
    if ($field['required'] && $value === '') {
        $errors[] = $field['label'] . ' is required.';
    }
    if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $field['label'] . ' must be a valid email.';
    }
    $data[$name] = $value;
}

if ($errors) {
    json_response(['error' => implode(' ', $errors), 'errors' => $errors], 422);
}

$repo->saveSubmission((int) $form['id'], $data, $filesMeta);

json_response([
    'ok' => true,
    'message' => $schema['settings']['successMessage'] ?? 'Thank you!',
]);
