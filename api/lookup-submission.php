<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Throttle enumeration / guessing of match fields (DOB + SIN, email + phone, etc.).
$lookupLimit = 30;
$lookupWindow = 3600;
if (form_spam_action_is_limited('lookup-submission', $lookupLimit, $lookupWindow)) {
    json_response(['error' => 'Too many lookup attempts. Please try again later.'], 429);
}

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

form_spam_action_record('lookup-submission', $lookupWindow);

$submission = $repo->findSubmissionByMatch((int) $form['id'], $criteria, $taxYear);
if (!$submission && $taxYear !== null) {
    // Returning clients filing a new year: fall back to their most recent earlier submission.
    $submission = $repo->findSubmissionByMatch((int) $form['id'], $criteria, null);
}

if (!$submission) {
    $keys = form_data_match_dob_sin($schema, $criteria);
    $client = ($keys['dob'] !== '' && $keys['sin'] !== '')
        ? (new ClientRepository())->findByDobAndSin($keys['dob'], $keys['sin'])
        : null;
    if (!$client) {
        json_response(['found' => false]);
    }
    json_response([
        'found' => true,
        'source' => 'client',
        'submitted_at' => null,
        'tax_year' => null,
        'data' => form_client_prefill($schema, $client),
    ]);
}

$data = json_decode((string) ($submission['data_json'] ?? ''), true);
if (!is_array($data)) {
    $data = [];
}

json_response([
    'found' => true,
    'source' => 'submission',
    'submitted_at' => $submission['created_at'],
    'tax_year' => $submission['tax_year'] ?? null,
    // Strip SIN / DOB / files / etc. — returning visitors who know match keys can still autofill the rest.
    'data' => form_data_match_public_prefill($schema, $data),
]);
