<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$lookupLimit = 20;
$lookupWindow = 3600;
if (form_spam_action_is_limited('lookup-client', $lookupLimit, $lookupWindow)) {
    json_response([
        'found' => false,
        'message' => 'Too many lookups. Please try again later.',
    ], 429);
}
form_spam_action_record('lookup-client', $lookupWindow);

$reference = trim((string) ($_POST['customer_id'] ?? ''));
if ($reference === '') {
    json_response([
        'found' => false,
        'message' => 'Enter your client ID or email.',
    ]);
}

$client = document_submission_resolve_client($reference);
if ($client === null) {
    json_response([
        'found' => false,
        'message' => 'Client ID or email not found. Please check your details.',
    ]);
}

$name = trim((string) ($client['name'] ?? ''));
$firstName = $name !== '' ? explode(' ', $name)[0] : 'Customer';

// Do not return internal client IDs to anonymous callers — existence + greeting only.
json_response([
    'found' => true,
    'message' => 'Account found — hi, ' . $firstName . '!',
]);
