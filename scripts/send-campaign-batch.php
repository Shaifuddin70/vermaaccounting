#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$result = process_due_campaign_batches();

if (PHP_SAPI === 'cli') {
    $line = sprintf(
        "[%s] Campaign batch: %d campaign(s), %d processed, %d sent, %d failed\n",
        gmdate('Y-m-d H:i:s'),
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
