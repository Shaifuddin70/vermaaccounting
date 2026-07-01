#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
if (
    (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/'))
    && getenv('VERMA_ENV') !== 'local'
) {
    putenv('VERMA_ENV=production');
}

require_once $projectRoot . '/lib/bootstrap.php';

$result = process_due_campaign_batches();
$env = app_environment();

if (PHP_SAPI === 'cli') {
    $line = sprintf(
        "[%s] [%s] Campaign batch: %d campaign(s), %d processed, %d sent, %d failed\n",
        gmdate('Y-m-d H:i:s'),
        $env,
        $result['campaigns'],
        $result['processed'],
        $result['sent'],
        $result['failed']
    );
    fwrite(STDOUT, $line);
    exit(0);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
