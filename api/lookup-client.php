<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

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

json_response([
    'found' => true,
    'message' => 'Account found — hi, ' . $firstName . '!',
    'client_id' => (int) ($client['id'] ?? 0),
]);
