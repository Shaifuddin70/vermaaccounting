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
$answersById = collect_posted_answers_by_id($schema, $_POST);

foreach ($schema['fields'] as $field) {
    $type = $field['type'];
    $name = $field['name'];
    $id = $field['id'];

    if (in_array($type, ['heading', 'paragraph', 'page_break'], true)) {
        continue;
    }

    $visible = form_field_is_visible($field, $answersById);
    if (!$visible) {
        if ($type === 'checkbox') {
            $data[$name] = [];
        } elseif (!in_array($type, ['file', 'image'], true)) {
            $data[$name] = '';
        }
        continue;
    }

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
    $result = validate_scalar_form_field($field, $value, true, $_POST);
    foreach ($result['errors'] as $err) {
        $errors[] = $err;
    }
    $data[$name] = $result['value'];
    foreach ($result['extra'] as $extraKey => $extraVal) {
        $data[$extraKey] = $extraVal;
    }

    if ($type === 'yes_no' && in_array($result['value'], ['yes', 'no'], true)) {
        foreach (yes_no_follow_ups_for_answer($field, $result['value']) as $followUp) {
            $pseudo = yes_no_follow_up_as_field($field, $followUp);
            if (!in_array($pseudo['type'], ['file', 'image'], true)) {
                continue;
            }
            [$fileValue, $fieldFilesMeta] = process_field_file_uploads(
                $pseudo,
                $form,
                $schema,
                $uploadSession,
                $_POST,
                $maxBytes,
                $allowedMimes,
                $errors
            );
            $data[(string) $pseudo['name']] = $fileValue;
            foreach ($fieldFilesMeta as $meta) {
                $filesMeta[] = $meta;
            }
        }
    }
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
