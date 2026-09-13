<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Throttle enumeration / guessing of match fields (email + phone, etc.).
$lookupLimit = 10;
$lookupWindow = 3600;
if (form_spam_action_is_limited('lookup-submission', $lookupLimit, $lookupWindow)) {
    json_response(['error' => 'Too many lookup attempts. Please try again later.'], 429);
}
form_spam_action_record('lookup-submission', $lookupWindow);

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$slug = trim((string) ($input['form_slug'] ?? ''));
if ($slug === '') {
    json_response(['error' => 'Missing form'], 400);
}

$repo = new FormRepository();
$form = $repo->findBySlug($slug, true);
if (!$form) {
    json_response(['error' => 'Form not found'], 404);
}

$schema = $repo->decodeSchema($form);
if (!form_data_match_enabled($schema)) {
    json_response(['found' => false]);
}

$matchFieldIds = form_data_match_field_ids($schema);
$matchInput = is_array($input['match'] ?? null) ? $input['match'] : [];

$criteria = [];
foreach ($matchFieldIds as $fieldId) {
    $name = field_name_by_id($schema, $fieldId);
    if ($name === null) {
        continue;
    }
    $value = trim((string) ($matchInput[$name] ?? $matchInput[$fieldId] ?? ''));
    if ($value === '') {
        json_response(['found' => false, 'reason' => 'incomplete']);
    }
    $criteria[$name] = $value;
}

if (count($criteria) < 2) {
    json_response(['found' => false]);
}

$taxYear = null;
if (form_tax_year_enabled($schema)) {
    $taxYear = (int) ($input['tax_year'] ?? 0);
    if ($taxYear < 1) {
        json_response(['found' => false, 'reason' => 'tax_year']);
    }
}

$submission = $repo->findSubmissionByMatch((int) $form['id'], $criteria, $taxYear);
if (!$submission) {
    json_response(['found' => false]);
}

// Never return submission payloads to anonymous callers (SIN, DOB, documents metadata, etc.).
json_response([
    'found' => true,
    'submitted_at' => $submission['created_at'],
    'tax_year' => $submission['tax_year'] ?? null,
]);
