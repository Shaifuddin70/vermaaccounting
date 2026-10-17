<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$reference = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['ref'] ?? $_POST['ref'] ?? '')) ?? '';
if ($reference === '') {
    json_response(['error' => 'Missing payment reference'], 400);
}

$attempt = online_payment_find_by_reference($reference);
if ($attempt === null) {
    json_response(['error' => 'Payment not found', 'status' => 'unknown'], 404);
}

$status = (string) ($attempt['status'] ?? 'pending');
if ($status === 'pending' && elavon_enabled()) {
    $result = elavon_confirm_payment($attempt);
    $status = $result['status'];
    $attempt = online_payment_find_by_reference($reference) ?? $attempt;
    $message = $result['message'] ?? '';
} else {
    $message = (string) ($attempt['result_message'] ?? '');
}

json_response([
    'ok' => true,
    'status' => $status,
    'message' => $message,
    'amount' => (float) ($attempt['amount'] ?? 0),
    'amount_label' => invoice_format_money((float) ($attempt['amount'] ?? 0)),
    'reference' => $reference,
]);
