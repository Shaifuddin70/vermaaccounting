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
require_once $projectRoot . '/scripts/generate-blog.php';

$holidayResult = process_due_holiday_sends();
$birthdayResult = process_due_birthday_sends();
$result = process_due_campaign_batches();
try {
    $cmsResult = cms_process_schedule() + ['error' => null];
} catch (Throwable $e) {
    $cmsResult = ['published' => 0, 'expired' => 0, 'error' => $e->getMessage()];
}
$blogAiResult = cms_run_blog_ai_cron();
$elavonPending = elavon_confirm_pending_payments(50);
$recurringInvoices = process_due_invoice_recurrences();

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
    if ($cmsResult['published'] > 0 || $cmsResult['expired'] > 0) {
        $line .= sprintf(' | website: published=%d expired=%d', $cmsResult['published'], $cmsResult['expired']);
    }
    if ($cmsResult['error'] !== null) {
        $line .= ' | website schedule error: ' . $cmsResult['error'];
    }
    if ($blogAiResult['generated'] > 0) {
        $line .= sprintf(' | blog AI: generated=%d', $blogAiResult['generated']);
    } elseif (($blogAiResult['skipped'] ?? null) !== null && $blogAiResult['skipped'] !== 'empty_queue' && $blogAiResult['skipped'] !== 'disabled' && $blogAiResult['skipped'] !== 'cooldown' && $blogAiResult['skipped'] !== 'no_api_key') {
        $line .= ' | blog AI skipped: ' . $blogAiResult['skipped'];
    }
    if ($blogAiResult['errors'] !== []) {
        $line .= ' | blog AI errors: ' . implode('; ', $blogAiResult['errors']);
    }
    if ($elavonPending['checked'] > 0 || $elavonPending['approved'] > 0) {
        $line .= sprintf(
            ' | elavon pending: checked=%d approved=%d declined=%d still=%d',
            $elavonPending['checked'],
            $elavonPending['approved'],
            $elavonPending['declined'],
            $elavonPending['pending']
        );
    }
    if ($elavonPending['errors'] !== []) {
        $line .= ' | elavon errors: ' . implode('; ', $elavonPending['errors']);
    }
    if ($recurringInvoices['created'] > 0 || $recurringInvoices['checked'] > 0) {
        $line .= sprintf(
            ' | recurring invoices: checked=%d created=%d emailed=%d',
            $recurringInvoices['checked'],
            $recurringInvoices['created'],
            $recurringInvoices['emailed']
        );
    }
    if ($recurringInvoices['errors'] !== []) {
        $line .= ' | recurring errors: ' . implode('; ', $recurringInvoices['errors']);
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
    'website' => $cmsResult,
    'blog_ai' => $blogAiResult,
    'elavon_pending' => $elavonPending,
] + $result, JSON_UNESCAPED_UNICODE);
