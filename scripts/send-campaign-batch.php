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

$holidayResult = process_due_holiday_sends();
$birthdayResult = process_due_birthday_sends();
$result = process_due_campaign_batches();
$blogResult = [
    'ok' => false,
    'skipped' => true,
    'fetched' => 0,
    'created' => 0,
    'updated' => 0,
    'error' => null,
];

try {
    $client = new UpliftAiClient();
    if (!$client->isConfigured()) {
        $blogResult['error'] = 'Uplift AI token not configured';
    } else {
        $sync = blog_sync_from_uplift($client);
        $blogResult = [
            'ok' => true,
            'skipped' => false,
            'fetched' => $sync['fetched'],
            'created' => $sync['created'],
            'updated' => $sync['updated'],
            'error' => null,
        ];
        if ($sync['created'] > 0 || $sync['updated'] > 0) {
            ActivityLog::record('blog.synced', 'blog', null, $sync + ['source' => 'cron']);
        }
    }
} catch (Throwable $e) {
    $blogResult['skipped'] = false;
    $blogResult['error'] = $e->getMessage();
}

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
    if ($holidayResult['triggered'] > 0) {
        $line .= sprintf(' | holidays triggered: %d', $holidayResult['triggered']);
    }
    if ($holidayResult['errors'] !== []) {
        $line .= ' | holiday errors: ' . implode('; ', $holidayResult['errors']);
    }
    if ($birthdayResult['triggered'] > 0) {
        $line .= sprintf(' | birthdays: %d recipient(s)', $birthdayResult['recipients']);
    }
    if ($birthdayResult['errors'] !== []) {
        $line .= ' | birthday errors: ' . implode('; ', $birthdayResult['errors']);
    }
    if (!empty($blogResult['ok'])) {
        $line .= sprintf(
            ' | blogs: fetched=%d created=%d updated=%d',
            $blogResult['fetched'],
            $blogResult['created'],
            $blogResult['updated']
        );
    } elseif (!empty($blogResult['error'])) {
        $line .= ' | blog sync error: ' . $blogResult['error'];
    }
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
    }
    if (Mailer::fromAppConfig() === null) {
        $line .= ' | WARNING: mail not configured';
    }
    fwrite(STDOUT, $line . "\n");
    exit(0);
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'holidays' => $holidayResult,
    'birthdays' => $birthdayResult,
    'blogs' => $blogResult,
] + $result, JSON_UNESCAPED_UNICODE);
