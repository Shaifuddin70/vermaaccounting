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
$uploadSession = normalize_upload_session_id(trim((string) ($_POST['upload_session'] ?? '')));

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

    if ($type === 'page_break') {
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
        [$fileValue, $fieldFilesMeta] = process_field_file_uploads(
            $field,
            $form,
            $schema,
            $uploadSession,
            $_POST,
            $maxBytes,
            $allowedMimes,
            $errors
        );
        $data[$name] = $fileValue;
        foreach ($fieldFilesMeta as $meta) {
            $filesMeta[] = $meta;
        }
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
    if ($type === 'number' && $value !== '') {
        $format = normalize_number_format((string) ($field['numberFormat'] ?? ''));
        if ($format !== '') {
            $value = apply_number_format($value, $format);
            $formatError = validate_number_format_value((string) $field['label'], $value, $format, false);
            if ($formatError !== null) {
                $errors[] = $formatError;
            }
        } elseif (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            $errors[] = $field['label'] . ' must be a valid number.';
        }
    }
    if ($type === 'date' && $value !== '') {
        $minAge = normalize_min_age($field['minAge'] ?? 0);
        $ageError = validate_date_min_age((string) $field['label'], $value, $minAge);
        if ($ageError !== null) {
            $errors[] = $ageError;
        }
    }
    $data[$name] = $value;
}

$taxYear = null;
$taxYearCfg = $schema['settings']['taxYear'] ?? [];
if (!empty($taxYearCfg['enabled'])) {
    $taxYear = (int) ($_POST['tax_year'] ?? 0);
    $allowed = $taxYearCfg['years'] ?? [];
    if ($taxYear < 1 || !in_array($taxYear, $allowed, true)) {
        $errors[] = 'Please select a valid tax year.';
    }
}

if ($errors) {
    json_response(['error' => implode(' ', $errors), 'errors' => $errors], 422);
}

$submissionId = $repo->saveSubmission((int) $form['id'], $data, $filesMeta, $taxYear > 0 ? $taxYear : null);
sync_submission_partners_from_data($submissionId, $schema, $data);

$clientRepo = new ClientRepository();
$clientRepo->linkFromSubmission([
    'id' => $submissionId,
    'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
], $schema);

send_submission_notification_emails(
    $form,
    $schema,
    $submissionId,
    $data,
    $taxYear > 0 ? $taxYear : null
);

json_response([
    'ok' => true,
    'message' => $schema['settings']['successMessage'] ?? 'Thank you!',
]);
