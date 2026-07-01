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
    if ($type === 'yes_no') {
        $value = in_array($value, ['yes', 'no'], true) ? $value : '';
        if ($field['required'] && $value === '') {
            $errors[] = $field['label'] . ' is required.';
        }
        $data[$name] = $value;

        $reasonWhen = $field['reasonWhen'] ?? '';
        if ($reasonWhen !== '') {
            $reasonKey = $name . '_reason';
            $reasonVal = trim((string) ($_POST[$reasonKey] ?? ''));
            if ($value === $reasonWhen) {
                if (!empty($field['reasonRequired']) && $reasonVal === '') {
                    $errors[] = ($field['reasonLabel'] ?? 'Reason') . ' is required.';
                }
                $data[$reasonKey] = $reasonVal;
            } else {
                unset($data[$reasonKey]);
            }
        }
        continue;
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
    $_SESSION['submission_edit_errors'] = $errors;
    header('Location: /admin/submission?id=' . $submissionId . '&form_id=' . $formId . '&edit=1');
    exit;
}

$repo->updateSubmissionData($submissionId, $data);
sync_submission_partners_from_data($submissionId, $schema, $data);

ActivityLog::record('submission.edited', 'submission', $submissionId, [
    'form_id' => $formId,
]);

header('Location: /admin/submission?id=' . $submissionId . '&form_id=' . $formId . '&saved=1');
exit;
