<?php
/**
 * Confirm Elavon card payments that succeeded in Converge but never redirected to /pay-return.
 *
 *   VERMA_ENV=production php scripts/elavon-confirm-pending.php
 *
 * Also runs from scripts/send-campaign-batch.php when that cron is already installed.
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
if (
    (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/'))
    && getenv('VERMA_ENV') !== 'local'
) {
    putenv('VERMA_ENV=production');
}

require_once $projectRoot . '/lib/bootstrap.php';

$result = elavon_confirm_pending_payments(50);

if (PHP_SAPI === 'cli') {
    echo sprintf(
        "[%s] Elavon pending: checked=%d approved=%d declined=%d still_pending=%d\n",
        gmdate('Y-m-d H:i:s'),
        $result['checked'],
        $result['approved'],
        $result['declined'],
        $result['pending']
    );
    foreach ($result['errors'] as $err) {
        echo '  error: ' . $err . "\n";
    }
}
