<?php
/**
 * One-off Converge credential / HPP token check. Run on the SERVER:
 *
 *   php scripts/elavon-diagnose.php
 *
 * Or briefly via browser (then delete this file):
 *   https://vermaaccounting.ca/scripts/elavon-diagnose.php?key=YOUR_SECRET
 *
 * Does not print the PIN.
 */
declare(strict_types=1);

$allowWebKey = getenv('ELAVON_DIAG_KEY') ?: '';
$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $key = (string) ($_GET['key'] ?? '');
    if ($allowWebKey === '' || !hash_equals($allowWebKey, $key)) {
        http_response_code(403);
        echo "Forbidden. Run via CLI: php scripts/elavon-diagnose.php\n";
        exit;
    }
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$config = elavon_config();
$base = elavon_base_url();
$outbound = @file_get_contents('https://api.ipify.org') ?: 'unknown';

echo "app_environment=" . (app_config()['environment'] ?? '?') . "\n";
echo "elavon_enabled=" . (elavon_enabled() ? 'yes' : 'no') . "\n";
echo "elavon_environment=" . $config['environment'] . "\n";
echo "api_base=" . $base . "\n";
echo "merchant_id=" . $config['merchant_id'] . "\n";
echo "user_id=" . $config['user_id'] . "\n";
echo "pin_len=" . strlen($config['pin']) . "\n";
echo "site_url=" . (app_config()['site_url'] ?? '') . "\n";
echo "app_base_url=" . app_base_url() . "\n";
echo "outbound_ip=" . $outbound . "\n";
echo "---\n";

$url = $base . '/hosted-payments/transaction_token';
$fields = elavon_credentials() + [
    'ssl_transaction_type' => 'ccsale',
    'ssl_amount' => '1.00',
    'ssl_invoice_number' => 'DIAG' . random_int(1000, 9999),
    'ssl_receipt_link_method' => 'REDG',
    'ssl_receipt_link_url' => rtrim(app_base_url(), '/') . '/pay-return?ref=DIAG',
    'ssl_email' => 'test@example.com',
];
$res = elavon_http_post($url, $fields);
$tokenOk = $res['ok'] && $res['body'] !== '' && !preg_match('/[\s<>]/', $res['body']);
echo "hpp_token_http=" . $res['status'] . "\n";
echo "hpp_token_curl=" . ($res['error'] !== '' ? $res['error'] : 'none') . "\n";
echo "hpp_token_ok=" . ($tokenOk ? 'YES' : 'NO') . "\n";
$preview = str_replace($config['pin'], '[PIN]', $res['body']);
$preview = substr(preg_replace('/\s+/', ' ', strip_tags($preview)), 0, 400);
echo "hpp_preview=" . $preview . "\n";
echo "---\n";

if ($res['status'] === 403) {
    echo "HINT: 403 usually means this outbound IP is not allowlisted for Hosted Payment Page.\n";
    echo "Send Elavon: outbound_ip above + demo Account ID + ask for HPP API access.\n";
} elseif ($res['status'] === 401 || str_contains($preview, '401')) {
    echo "HINT: 401 means invalid Account ID / User ID / PIN, or user is not a Hosted Payment API user.\n";
} elseif ($tokenOk) {
    echo "OK: session token works from this server. Payment page should open.\n";
} else {
    echo "HINT: Unexpected response. Paste this output to support (PIN is already redacted).\n";
}
