#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create due recurring invoices (and email them when configured).
 *
 * Cron example (daily after midnight in America/Toronto):
 *   VERMA_ENV=production php /path/to/scripts/generate-recurring-invoices.php
 *
 * Also runs from send-campaign-batch.php.
 */

$projectRoot = dirname(__DIR__);
if (
    (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/'))
    && getenv('VERMA_ENV') !== 'local'
) {
    putenv('VERMA_ENV=production');
}

require_once $projectRoot . '/lib/bootstrap.php';

$result = process_due_invoice_recurrences();

if (PHP_SAPI === 'cli') {
    $line = sprintf(
        '[%s] [%s] Recurring invoices: checked=%d created=%d emailed=%d',
        gmdate('Y-m-d H:i:s'),
        app_environment(),
        $result['checked'],
        $result['created'],
        $result['emailed']
    );
    if ($result['invoice_ids'] !== []) {
        $line .= ' | ids=' . implode(',', $result['invoice_ids']);
    }
    if ($result['errors'] !== []) {
        $line .= ' | errors: ' . implode('; ', $result['errors']);
    }
    echo $line . PHP_EOL;
}

return $result;
