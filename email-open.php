<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$token = (string) ($_GET['t'] ?? '');
if ($token === '' && isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/email-open/([a-f0-9]{32})#i', (string) $_SERVER['REQUEST_URI'], $m)) {
        $token = $m[1];
    }
}

email_tracking_record_open($token, isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null);

$gif = email_tracking_transparent_gif();
header('Content-Type: image/gif');
header('Content-Length: ' . (string) strlen($gif));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
echo $gif;
exit;
