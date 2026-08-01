<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid session.');
}

Auth::requireRole('admin');

$submissionId = (int) ($_POST['submission_id'] ?? 0);
$formId = (int) ($_POST['form_id'] ?? 0);

$repo = new FormRepository();
$form = $repo->find($formId);
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$form || !$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

$schema = $repo->decodeSchema($form);
$config = app_config();
$maxBytes = (int) ($config['max_upload_bytes'] ?? 10485760);
$allowedMimes = $config['allowed_upload_mimes'] ?? [];
$existingFiles = files_by_field_id($repo->filesForSubmission($submissionId));

$data = [];
$errors = [];

foreach ($schema['fields'] as $field) {
    $type = $field['type'];
    $name = $field['name'];
    $id = $field['id'];

    if (in_array($type, ['heading', 'paragraph', 'page_break'], true)) {
        continue;
    }

    // Admin edit form shows every field (no conditional hide), so always validate.
    $visible = true;

    if ($type === 'checkbox') {
        $raw = $_POST[$name] ?? [];
        $values = is_array($raw) ? array_map('strval', $raw) : [];
        $choiceError = validate_checkbox_field_values(
            (string) $field['label'],
            $values,
            $field,
            !empty($field['required'])
        );
        if ($choiceError !== null) {
            $errors[] = $choiceError;
        }
        $data[$name] = $values;
        continue;
    }

    if ($type === 'partners') {
        $data[$name] = partners_field_from_post($field, $_POST, $errors);
        continue;
    }

    if (in_array($type, ['file', 'image'], true)) {
        $hasExisting = !empty($existingFiles[$id]);
        $fileKey = $name;
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            if ($field['required'] && !$hasExisting) {
                $errors[] = $field['label'] . ' is required.';
            }
            if ($hasExisting) {
                $data[$name] = $existingFiles[$id][0]['stored_name'] ?? '';
            }
            continue;
        }

        $upload = $_FILES[$fileKey];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for ' . $field['label'];
            continue;
        }
        $check = validate_form_upload_file($upload, $field, $maxBytes, $allowedMimes);
        if (!$check['ok']) {
            $errors[] = (string) ($check['error'] ?? ($field['label'] . ' upload failed.'));
            continue;
        }
        $mime = (string) $check['mime'];

        $ext = pathinfo($upload['name'], PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $destDir = UPLOADS_DIR . '/' . $formId;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . $stored;
        if (!move_uploaded_file($upload['tmp_name'], $dest)) {
            $errors[] = 'Could not save ' . $field['label'];
            continue;
        }

        $repo->deleteFilesForField($submissionId, $id);
        $storedPath = $formId . '/' . $stored;
        $data[$name] = $storedPath;
        $repo->addSubmissionFile($submissionId, [
            'field_id' => $id,
            'stored_name' => $storedPath,
            'original_name' => $upload['name'],
            'mime' => $mime,
            'size' => (int) $upload['size'],
        ]);
        continue;
    }

    $value = trim((string) ($_POST[$name] ?? ''));
    $result = validate_scalar_form_field($field, $value, $visible, $_POST);
    foreach ($result['errors'] as $err) {
        $errors[] = $err;
    }
    $data[$name] = $result['value'];
    foreach ($result['extra'] as $extraKey => $extraVal) {
        $data[$extraKey] = $extraVal;
    }
}

if ($errors) {
    $_SESSION['submission_edit_errors'] = $errors;
    header('Location: /admin/submission?id=' . $submissionId . '&form_id=' . $formId . '&edit=1');
    exit;
}

$repo->updateSubmissionData($submissionId, $data);
sync_submission_partners_from_data($submissionId, $schema, $data);

ActivityLog::record('submission.edited', 'submission', $submissionId, [
    'form_id' => $formId,
]);

$_SESSION['flash_success'] = 'Submission saved.';
header('Location: /admin/submission?id=' . $submissionId . '&form_id=' . $formId);
exit;
