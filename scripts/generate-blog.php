#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron entry for AI blog generation.
 * Prefer calling via scripts/send-campaign-batch.php (already wired).
 * Standalone:
 *   VERMA_ENV=production php scripts/generate-blog.php
 *
 * @return array{generated: int, skipped: ?string, items: list<array>, errors: list<string>}
 */
function cms_run_blog_ai_cron(): array
{
    try {
        return (new CmsBlogAiService())->runCron();
    } catch (Throwable $e) {
        error_log('CMS blog AI cron failed: ' . $e->getMessage());
        return [
            'generated' => 0,
            'skipped' => null,
            'items' => [],
            'errors' => [$e->getMessage()],
        ];
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    $projectRoot = dirname(__DIR__);
    if (
        (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/'))
        && getenv('VERMA_ENV') !== 'local'
    ) {
        putenv('VERMA_ENV=production');
    }

    require_once $projectRoot . '/lib/bootstrap.php';

    $result = cms_run_blog_ai_cron();
    if (PHP_SAPI === 'cli') {
        $line = sprintf(
            '[%s] Blog AI: generated=%d skipped=%s',
            gmdate('Y-m-d H:i:s'),
            $result['generated'],
            $result['skipped'] ?? 'ok'
        );
        if ($result['items'] !== []) {
            $paths = array_map(static fn ($i) => $i['path'], $result['items']);
            $line .= ' paths=' . implode(',', $paths);
        }
        if ($result['errors'] !== []) {
            $line .= ' errors=' . implode('; ', $result['errors']);
        }
        fwrite(STDOUT, $line . "\n");
        exit($result['errors'] !== [] ? 1 : 0);
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => $result['errors'] === []] + $result, JSON_UNESCAPED_UNICODE);
}
