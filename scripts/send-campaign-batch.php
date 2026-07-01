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
        "[%s] [%s] Campaign batch: %d campaign(s), %d processed, %d sent, %d failed",
        gmdate('Y-m-d H:i:s'),
        $env,
        $result['campaigns'],
        $result['processed'],
        $result['sent'],
        $result['failed']
    );
    if ($result['campaigns'] === 0) {
        $diag = campaign_queue_diagnostics();
        $line .= sprintf(
            ' | queue: sending=%d due=%d future=%d draft=%d failed=%d sent=%d',
            $diag['sending'],
            $diag['scheduled_due'],
            $diag['scheduled_future'],
            $diag['draft'],
            $diag['failed'],
            $diag['sent']
        );
        if ($diag['draft'] > 0) {
            $line .= ' (drafts are not sent until you click Start sending)';
        }
        if ($diag['scheduled_future'] > 0 && $diag['scheduled_due'] === 0 && $diag['sending'] === 0) {
            $line .= ' (scheduled campaigns are waiting for their send time)';
        }
        if (Mailer::fromAppConfig() === null) {
            $line .= ' | WARNING: mail not configured';
        }
    }
    fwrite(STDOUT, $line . "\n");
    exit(0);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
