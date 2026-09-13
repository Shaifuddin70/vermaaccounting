<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

// Always return JSON — never leak PHP HTML warnings to the browser.
ini_set('display_errors', '0');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Method not allowed'], 405);
    }

    // Opportunistic cleanup of abandoned staging sessions (TTL).
    staging_cleanup_expired();

    $uploadLimit = 30;
    $uploadWindow = 3600;
    if (form_spam_action_is_limited('stage-upload', $uploadLimit, $uploadWindow)) {
        json_response(['error' => 'Too many uploads from your network. Please try again later.'], 429);
    }

    $slug = trim((string) ($_POST['form_slug'] ?? ''));
    $fieldId = trim((string) ($_POST['field_id'] ?? ''));
    $session = normalize_upload_session_id(trim((string) ($_POST['upload_session'] ?? '')));

    if ($slug === '' || $fieldId === '' || $session === '') {
        json_response(['error' => 'Missing upload parameters.'], 400);
    }

    if (!isset($_FILES['file'])) {
        // Empty $_FILES often means the file exceeded post_max_size
        json_response(['error' => 'No file received. The file may exceed the server upload limit.'], 400);
    }

    $repo = new FormRepository();
    $form = $repo->findBySlug($slug, true);
    if (!$form) {
        json_response(['error' => 'Form not found.'], 404);
    }

    $schema = $repo->decodeSchema($form);
    $field = staging_find_field($schema, $fieldId);
    if ($field === null || !in_array($field['type'] ?? '', ['file', 'image'], true)) {
        json_response(['error' => 'Invalid upload field.'], 400);
    }

    $maxFiles = staging_max_files_for_field($field);
    if (staging_count_tokens_for_field($session, $fieldId) >= $maxFiles) {
        json_response(['error' => 'Maximum number of files reached for this field.'], 422);
    }

    $config = app_config();
    $maxBytes = (int) ($config['max_upload_bytes'] ?? 10485760);
    $allowedMimes = $config['allowed_upload_mimes'] ?? [];
    $check = validate_form_upload_file($_FILES['file'], $field, $maxBytes, $allowedMimes);
    if (!$check['ok']) {
        json_response(['error' => (string) ($check['error'] ?? 'Upload failed.')], 422);
    }

    form_spam_action_record('stage-upload', $uploadWindow);

    $result = staging_store_upload($form, $field, $session, $_FILES['file'], (string) $check['mime']);

    json_response([
        'ok' => true,
        'token' => $result['token'],
        'original_name' => $result['original_name'],
        'size' => $result['size'],
        'mime' => $result['mime'],
    ]);
} catch (Throwable $e) {
    json_response(['error' => 'Could not upload file. Please try again or use a smaller file.'], 500);
}
