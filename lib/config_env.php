<?php

declare(strict_types=1);

if (!function_exists('verma_resolve_environment')) {
    /**
     * @return 'local'|'production'
     */
    function verma_resolve_environment(string $mode): string
    {
        if ($mode === 'local' || $mode === 'production') {
            return $mode;
        }

        $forced = getenv('VERMA_ENV');
        if ($forced === 'local' || $forced === 'production') {
            return $forced;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $hostWithoutPort = preg_replace('/:\d+$/', '', $host) ?? $host;

            $productionHosts = [
                'vermaaccounting.ca',
                'www.vermaaccounting.ca',
            ];

            if (in_array($hostWithoutPort, $productionHosts, true)) {
                return 'production';
            }

            if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host)) {
                return 'local';
            }

            return 'production';
        }

        if (PHP_SAPI === 'cli') {
            $projectRoot = dirname(__DIR__);
            if (str_contains($projectRoot, 'public_html') || str_contains($projectRoot, '/home/')) {
                return 'production';
            }
            return 'local';
        }

        return 'production';
    }
}
