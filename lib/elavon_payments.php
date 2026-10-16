<?php

declare(strict_types=1);

/**
 * Elavon Converge card payments for invoices and form advance payments, using the Converge
 * Hosted Payment Page so card details are entered on Elavon's site and never reach this server.
 *
 * Flow: public pay link -> session token (server to Converge) -> client pays on the hosted page
 * -> Converge redirects back -> the transaction is re-queried server-side before anything is recorded.
 */

/**
 * @return array{enabled: bool, environment: 'demo'|'live', merchant_id: string, user_id: string, pin: string}
 */
function elavon_config(): array
{
    $raw = app_config()['elavon'] ?? [];
    $raw = is_array($raw) ? $raw : [];
    $environment = ($raw['environment'] ?? 'demo') === 'live' ? 'live' : 'demo';
    return [
        'enabled' => !empty($raw['enabled']),
        'environment' => $environment,
        'merchant_id' => trim((string) ($raw['merchant_id'] ?? '')),
        'user_id' => trim((string) ($raw['user_id'] ?? '')),
        'pin' => trim((string) ($raw['pin'] ?? '')),
    ];
}

function elavon_enabled(): bool
{
    $config = elavon_config();
    return $config['enabled'] && $config['merchant_id'] !== '' && $config['user_id'] !== '' && $config['pin'] !== '';
}

function elavon_base_url(): string
{
    return elavon_config()['environment'] === 'live'
        ? 'https://api.convergepay.com'
        : 'https://api.demo.convergepay.com';
}

/** @return array{ok: bool, status: int, body: string, error: string} */
function elavon_http_post(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body === false ? '' : (string) $body,
        'error' => $error,
    ];
}

/** @return array<string, string> */
function elavon_credentials(): array
{
    $config = elavon_config();
    return [
        'ssl_merchant_id' => $config['merchant_id'],
        'ssl_user_id' => $config['user_id'],
        'ssl_pin' => $config['pin'],
    ];
}

/** Public pay link for an invoice; the token is created on first use. */
function invoice_pay_url(array $invoice): string
{
    $token = trim((string) ($invoice['pay_token'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(20));
        $stmt = Database::instance()->pdo()->prepare(
            'UPDATE invoices SET pay_token = ? WHERE id = ? AND (pay_token IS NULL OR pay_token = \'\')'
        );
        $stmt->execute([$token, (int) $invoice['id']]);
        if ($stmt->rowCount() === 0) {
            $fresh = (new InvoiceRepository())->find((int) $invoice['id']);
            $token = (string) ($fresh['pay_token'] ?? $token);
        }
    }
    return app_base_url() . '/pay?t=' . $token;
}

function invoice_find_by_pay_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    $stmt = Database::instance()->pdo()->prepare('SELECT id FROM invoices WHERE pay_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $id = (int) $stmt->fetchColumn();
    return $id > 0 ? (new InvoiceRepository())->find($id) : null;
}

/** Whether a client can pay this invoice online right now. */
function invoice_can_pay_online(array $invoice): bool
{
    return elavon_enabled()
        && ($invoice['status'] ?? 'draft') !== 'draft'
        && invoice_amount_due($invoice) > 0;
}

/** Public pay link for a form submission advance; the token is created on first use. */
function submission_pay_url(array $submission): string
{
    $token = trim((string) ($submission['pay_token'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(20));
        $stmt = Database::instance()->pdo()->prepare(
            'UPDATE submissions SET pay_token = ? WHERE id = ? AND (pay_token IS NULL OR pay_token = \'\')'
        );
        $stmt->execute([$token, (int) $submission['id']]);
        if ($stmt->rowCount() === 0) {
            $fresh = (new FormRepository())->findSubmission((int) $submission['id']);
            $token = (string) ($fresh['pay_token'] ?? $token);
        }
    }
    return app_base_url() . '/pay?t=' . $token;
}

function submission_find_by_pay_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    $stmt = Database::instance()->pdo()->prepare('SELECT id FROM submissions WHERE pay_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $id = (int) $stmt->fetchColumn();
    return $id > 0 ? (new FormRepository())->findSubmission($id) : null;
}

/**
 * @param array<string, mixed> $schema
 * @param array<string, mixed> $data
 */
function submission_can_pay_online(array $submission, array $schema, array $data): bool
{
    return submission_elavon_pay_target((int) $submission['id'], $schema, $data) !== null;
}

function online_payment_status_label(string $status): string
{
    return [
        'pending' => 'Not confirmed',
        'approved' => 'Approved',
        'declined' => 'Declined',
        'error' => 'Could not start',
    ][$status] ?? ucfirst($status);
}

function online_payment_find_by_reference(string $reference): ?array
{
    $stmt = Database::instance()->pdo()->prepare('SELECT * FROM online_payments WHERE reference = ? LIMIT 1');
    $stmt->execute([$reference]);
    return $stmt->fetch() ?: null;
}

/** @return list<array<string, mixed>> */
function online_payments_for_invoice(int $invoiceId): array
{
    $stmt = Database::instance()->pdo()->prepare(
        'SELECT * FROM online_payments WHERE invoice_id = ? ORDER BY id DESC'
    );
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

/** @return list<array<string, mixed>> */
function online_payments_for_submission(int $submissionId): array
{
    $stmt = Database::instance()->pdo()->prepare(
        'SELECT * FROM online_payments WHERE submission_id = ? ORDER BY id DESC'
    );
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

/** @param array<string, mixed> $changes */
function online_payment_update(int $id, array $changes): void
{
    $allowed = ['status', 'txn_id', 'approval_code', 'card_summary', 'result_message', 'invoice_payment_id', 'amount'];
    $sets = [];
    $values = [];
    foreach ($changes as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $sets[] = $key . ' = ?';
            $values[] = $value;
        }
    }
    if ($sets === []) {
        return;
    }
    $sets[] = 'updated_at = ?';
    $values[] = now_iso();
    $values[] = $id;
    Database::instance()->pdo()
        ->prepare('UPDATE online_payments SET ' . implode(', ', $sets) . ' WHERE id = ?')
        ->execute($values);
}

/**
 * Start a hosted-page payment for the invoice's full balance.
 *
 * @return array{ok: bool, redirect?: string, error?: string}
 */
function elavon_start_invoice_payment(array $invoice): array
{
    if (!invoice_can_pay_online($invoice)) {
        return ['ok' => false, 'error' => 'This invoice cannot be paid online.'];
    }

    $amount = invoice_money(invoice_amount_due($invoice));
    $number = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($invoice['invoice_number'] ?? '')) ?: (string) $invoice['id'];
    $reference = mb_substr($number, 0, 12) . '-' . strtoupper(bin2hex(random_bytes(4)));
    $config = elavon_config();
    $now = now_iso();

    $pdo = Database::instance()->pdo();
    $pdo->prepare('
        INSERT INTO online_payments (
            invoice_id, submission_id, submission_payment_id, gateway, environment,
            reference, amount, status, created_at, updated_at
        ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([(int) $invoice['id'], 'elavon', $config['environment'], $reference, $amount, 'pending', $now, $now]);
    $attemptId = (int) $pdo->lastInsertId();

    return elavon_hosted_redirect($attemptId, $reference, $amount, 'Invoice #' . (string) ($invoice['invoice_number'] ?? ''), trim((string) ($invoice['bill_to_email'] ?? '')));
}

/**
 * Start a hosted-page payment for a form submission's advance card amount.
 *
 * @param array<string, mixed> $schema
 * @param array<string, mixed> $data
 * @return array{ok: bool, redirect?: string, error?: string}
 */
function elavon_start_submission_payment(array $submission, array $schema, array $data): array
{
    $target = submission_elavon_pay_target((int) $submission['id'], $schema, $data);
    if ($target === null) {
        return ['ok' => false, 'error' => 'This form payment cannot be paid online.'];
    }

    $amount = invoice_money($target['amount']);
    $paymentId = isset($target['payment']['id']) ? (int) $target['payment']['id'] : 0;
    $reference = 'S' . (int) $submission['id'] . '-' . strtoupper(bin2hex(random_bytes(4)));
    $reference = mb_substr($reference, 0, 25);
    $config = elavon_config();
    $now = now_iso();
    $form = (new FormRepository())->find((int) $submission['form_id']);
    $label = trim((string) ($form['title'] ?? 'Form')) ?: 'Form';

    $pdo = Database::instance()->pdo();
    $pdo->prepare('
        INSERT INTO online_payments (
            invoice_id, submission_id, submission_payment_id, gateway, environment,
            reference, amount, status, created_at, updated_at
        ) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        (int) $submission['id'],
        $paymentId > 0 ? $paymentId : null,
        'elavon',
        $config['environment'],
        $reference,
        $amount,
        'pending',
        $now,
        $now,
    ]);
    $attemptId = (int) $pdo->lastInsertId();

    $email = '';
    foreach (['email', 'Email', 'client_email', 'your_email'] as $key) {
        $candidate = trim((string) ($data[$key] ?? ''));
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $email = $candidate;
            break;
        }
    }

    return elavon_hosted_redirect($attemptId, $reference, $amount, 'Advance: ' . $label, $email);
}

/**
 * @return array{ok: bool, redirect?: string, error?: string}
 */
function elavon_hosted_redirect(int $attemptId, string $reference, float $amount, string $description, string $email = ''): array
{
    // Prefer HTTPS return URLs when available — NextGen HPP often hangs redirecting to http:// local hosts.
    $returnUrl = app_base_url() . '/pay-return?ref=' . rawurlencode($reference);
    $fields = elavon_credentials() + [
        'ssl_transaction_type' => 'ccsale',
        'ssl_amount' => number_format($amount, 2, '.', ''),
        'ssl_invoice_number' => $reference,
        'ssl_description' => mb_substr($description, 0, 255),
        // REDG = redirect browser to our return URL (GET). Also accept POST on pay-return.php.
        'ssl_receipt_link_method' => 'REDG',
        'ssl_receipt_link_url' => $returnUrl,
    ];
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fields['ssl_email'] = $email;
    }

    $response = elavon_http_post(elavon_base_url() . '/hosted-payments/transaction_token', $fields);
    $token = trim($response['body']);
    if (!$response['ok'] || $token === '' || preg_match('/[\s<>]/', $token)) {
        $message = $response['error'] !== '' ? $response['error'] : mb_substr(strip_tags($token), 0, 200);
        error_log('Elavon session token failed (HTTP ' . $response['status'] . '): ' . $message);
        online_payment_update($attemptId, ['status' => 'error', 'result_message' => 'Could not start payment: ' . $message]);
        return ['ok' => false, 'error' => 'The card payment page is not available right now. Please try again later or contact us.'];
    }

    return [
        'ok' => true,
        'redirect' => elavon_base_url() . '/hosted-payments?ssl_txn_auth_token=' . rawurlencode($token),
    ];
}

/**
 * Query Converge for transactions. Returns a list of transactions (field => value) or an error.
 *
 * @param array<string, string> $criteria
 * @return array{ok: bool, txns: list<array<string, string>>, error?: string}
 */
function elavon_txn_query(array $criteria): array
{
    $xml = new SimpleXMLElement('<txn/>');
    foreach (elavon_credentials() + ['ssl_transaction_type' => 'txnquery'] + $criteria as $key => $value) {
        $xml->addChild($key, htmlspecialchars($value, ENT_XML1));
    }
    $payload = preg_replace('/^<\?xml[^>]*\?>\s*/', '', (string) $xml->asXML());

    $xmlPath = elavon_config()['environment'] === 'live' ? '/VirtualMerchant/processxml.do' : '/VirtualMerchantDemo/processxml.do';
    $response = elavon_http_post(elavon_base_url() . $xmlPath, ['xmldata' => $payload]);
    if (!$response['ok']) {
        return ['ok' => false, 'txns' => [], 'error' => 'HTTP ' . $response['status'] . ' ' . $response['error']];
    }

    $previous = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($response['body']);
    libxml_use_internal_errors($previous);
    if ($doc === false) {
        return ['ok' => false, 'txns' => [], 'error' => 'Unreadable response from Converge.'];
    }
    if (isset($doc->errorCode)) {
        return ['ok' => false, 'txns' => [], 'error' => trim((string) $doc->errorCode . ' ' . (string) $doc->errorMessage)];
    }

    $nodes = $doc->getName() === 'txnlist' ? $doc->txn : [$doc];
    $txns = [];
    foreach ($nodes as $node) {
        $row = [];
        foreach ($node->children() as $child) {
            $row[$child->getName()] = trim((string) $child);
        }
        if ($row !== []) {
            $txns[] = $row;
        }
    }
    return ['ok' => true, 'txns' => $txns];
}

/** @param array<string, string> $txn */
function elavon_txn_approved(array $txn): bool
{
    if (in_array(strtoupper($txn['ssl_trans_status'] ?? ''), ['FPR', 'PST'], true)) {
        return false;
    }
    if (($txn['ssl_result'] ?? '') !== '') {
        return $txn['ssl_result'] === '0';
    }
    return in_array(strtoupper($txn['ssl_result_message'] ?? ''), ['APPROVAL', 'APPROVED', 'PARTIAL APPROVAL'], true);
}

/**
 * Confirm an attempt with Converge and record the payment on the invoice exactly once.
 *
 * @return array{status: string, message: string}
 */
function elavon_confirm_payment(array $attempt, string $txnId = ''): array
{
    if (($attempt['status'] ?? '') === 'approved') {
        return ['status' => 'approved', 'message' => 'Payment already recorded.'];
    }

    $txnId = preg_replace('/[^A-Za-z0-9-]/', '', $txnId) ?? '';
    if ($txnId !== '') {
        $query = elavon_txn_query(['ssl_txn_id' => $txnId]);
    } else {
        $from = new DateTimeImmutable((string) $attempt['created_at']);
        $query = elavon_txn_query([
            'ssl_search_start_date' => $from->modify('-1 day')->format('m/d/Y'),
            'ssl_search_end_date' => (new DateTimeImmutable('+1 day'))->format('m/d/Y'),
            'ssl_invoice_number' => (string) $attempt['reference'],
        ]);
    }
    if (!$query['ok']) {
        error_log('Elavon txnquery failed for ' . $attempt['reference'] . ': ' . ($query['error'] ?? ''));
        return ['status' => 'pending', 'message' => 'We could not confirm the payment with Elavon yet.'];
    }

    $match = null;
    foreach ($query['txns'] as $txn) {
        if (($txn['ssl_invoice_number'] ?? '') !== (string) $attempt['reference']) {
            continue;
        }
        if ($match === null || elavon_txn_approved($txn)) {
            $match = $txn;
        }
    }
    if ($match === null) {
        return ['status' => 'pending', 'message' => 'No matching card transaction was found yet.'];
    }

    return elavon_apply_txn($attempt, $match, $txnId);
}

/**
 * Apply a transaction returned by Converge's txnquery to its attempt.
 *
 * @param array<string, string> $match
 * @return array{status: string, message: string}
 */
function elavon_apply_txn(array $attempt, array $match, string $txnId = ''): array
{
    $card = trim(($match['ssl_card_short_description'] ?? $match['ssl_card_type'] ?? '') . ' ' . ($match['ssl_card_number'] ?? ''));
    $resultMessage = mb_substr((string) ($match['ssl_result_message'] ?? ''), 0, 255);
    $foundTxnId = (string) ($match['ssl_txn_id'] ?? $txnId);

    if (!elavon_txn_approved($match)) {
        online_payment_update((int) $attempt['id'], [
            'status' => 'declined',
            'txn_id' => $foundTxnId !== '' ? $foundTxnId : null,
            'card_summary' => mb_substr($card, 0, 64),
            'result_message' => $resultMessage !== '' ? $resultMessage : 'Declined',
        ]);
        return ['status' => 'declined', 'message' => $resultMessage !== '' ? $resultMessage : 'The card was declined.'];
    }

    $pdo = Database::instance()->pdo();
    $claim = $pdo->prepare("UPDATE online_payments SET status = 'approved', txn_id = ?, updated_at = ? WHERE id = ? AND status <> 'approved'");
    $claim->execute([$foundTxnId, now_iso(), (int) $attempt['id']]);
    if ($claim->rowCount() === 0) {
        return ['status' => 'approved', 'message' => 'Payment already recorded.'];
    }

    $amount = invoice_money((float) ($match['ssl_amount'] ?? $attempt['amount']));
    $approval = mb_substr((string) ($match['ssl_approval_code'] ?? ''), 0, 32);
    $referenceNote = mb_substr('Elavon ' . $foundTxnId . ($approval !== '' ? ' / auth ' . $approval : ''), 0, 191);

    $submissionId = (int) ($attempt['submission_id'] ?? 0);
    if ($submissionId > 0) {
        try {
            (new SubmissionPaymentRepository())->markVerifiedFromOnline(
                $submissionId,
                (int) ($attempt['submission_payment_id'] ?? 0),
                $amount,
                $referenceNote
            );
        } catch (Throwable $e) {
            online_payment_update((int) $attempt['id'], ['status' => 'pending', 'result_message' => 'Approved but not recorded yet']);
            error_log('Recording Elavon submission payment ' . $foundTxnId . ' failed: ' . $e->getMessage());
            return ['status' => 'pending', 'message' => 'Your payment was approved and is being recorded.'];
        }
        online_payment_update((int) $attempt['id'], [
            'amount' => $amount,
            'approval_code' => $approval,
            'card_summary' => mb_substr($card, 0, 64),
            'result_message' => $resultMessage !== '' ? $resultMessage : 'APPROVAL',
        ]);
        ActivityLog::record('submission.paid_online', 'submission', $submissionId, [
            'amount' => $amount,
            'txn_id' => $foundTxnId,
            'reference' => (string) $attempt['reference'],
        ]);
        return ['status' => 'approved', 'message' => 'Payment received.'];
    }

    $repo = new InvoiceRepository();
    $paymentId = null;
    try {
        $repo->addPayment((int) $attempt['invoice_id'], [
            'amount' => $amount,
            'payment_date' => date('Y-m-d'),
            'method' => 'credit_card',
            'reference' => $referenceNote,
            'note' => 'Paid online by card' . ($card !== '' ? ' (' . $card . ')' : '') . '.',
            'recorded_by_user_id' => null,
            'recorded_by_name' => 'Online payment',
        ], $paymentId);
    } catch (Throwable $e) {
        online_payment_update((int) $attempt['id'], ['status' => 'pending', 'result_message' => 'Approved but not recorded yet']);
        error_log('Recording Elavon payment ' . $foundTxnId . ' failed: ' . $e->getMessage());
        return ['status' => 'pending', 'message' => 'Your payment was approved and is being recorded.'];
    }
    online_payment_update((int) $attempt['id'], [
        'amount' => $amount,
        'approval_code' => $approval,
        'card_summary' => mb_substr($card, 0, 64),
        'result_message' => $resultMessage !== '' ? $resultMessage : 'APPROVAL',
        'invoice_payment_id' => $paymentId,
    ]);
    invoice_sync_paid_status((int) $attempt['invoice_id']);
    ActivityLog::record('invoice.paid_online', 'invoice', (int) $attempt['invoice_id'], [
        'amount' => $amount,
        'txn_id' => $foundTxnId,
        'reference' => (string) $attempt['reference'],
    ]);

    return ['status' => 'approved', 'message' => 'Payment received.'];
}
