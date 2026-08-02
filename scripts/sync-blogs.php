#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sync blogs from Uplift AI.
 *
 * Also runs automatically inside scripts/send-campaign-batch.php (existing cron).
 * Use this script if you want a dedicated blog-only schedule.
 *
 * Example crontab (daily at 7:00):
 *   0 7 * * * VERMA_ENV=production /usr/bin/php /path/to/vermaaccounting/scripts/sync-blogs.php >> /path/to/logs/blog-sync.log 2>&1
 */

$projectRoot = dirname(__DIR__);
if (
    (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/'))
    && getenv('VERMA_ENV') !== 'local'
) {
    putenv('VERMA_ENV=production');
}

require_once $projectRoot . '/lib/bootstrap.php';

$payload = [
    'ok' => false,
    'fetched' => 0,
    'created' => 0,
    'updated' => 0,
    'error' => null,
    'environment' => app_environment(),
];

try {
    $result = blog_sync_from_uplift();
    $payload['ok'] = true;
    $payload['fetched'] = $result['fetched'];
    $payload['created'] = $result['created'];
    $payload['updated'] = $result['updated'];
    if ($result['created'] > 0 || $result['updated'] > 0) {
        ActivityLog::record('blog.synced', 'blog', null, $result + ['source' => 'cron']);
    }
} catch (Throwable $e) {
    $payload['error'] = $e->getMessage();
}

if (PHP_SAPI === 'cli') {
    if ($payload['ok']) {
        fwrite(STDOUT, sprintf(
            "[%s] [%s] Blog sync: fetched=%d created=%d updated=%d\n",
            gmdate('Y-m-d H:i:s'),
            $payload['environment'],
            $payload['fetched'],
            $payload['created'],
            $payload['updated']
        ));
        exit(0);
    }
    fwrite(STDERR, sprintf(
        "[%s] [%s] Blog sync failed: %s\n",
        gmdate('Y-m-d H:i:s'),
        $payload['environment'],
        (string) $payload['error']
    ));
    exit(1);
}

header('Content-Type: application/json');
http_response_code($payload['ok'] ? 200 : 500);
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
