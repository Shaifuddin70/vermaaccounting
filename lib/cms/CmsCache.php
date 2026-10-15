<?php

declare(strict_types=1);

/**
 * Rendered-page file cache for anonymous GET requests. Any content, redirect or configuration
 * change flushes the whole cache, so pages never outlive the data they were built from.
 */
final class CmsCache
{
    public const DEFAULT_TTL = 600;

    public static function dir(): string
    {
        return DATA_DIR . '/cache/cms';
    }

    public static function enabled(): bool
    {
        return (bool) (app_config()['cms']['page_cache'] ?? true);
    }

    private static function file(string $key): string
    {
        return self::dir() . '/' . sha1($key) . '.json';
    }

    /** @return array{status: int, headers: array<string, string>, body: string}|null */
    public static function get(string $key): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $file = self::file($key);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || ($data['expires'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }
        return $data;
    }

    public static function put(string $key, int $status, array $headers, string $body, int $ttl = self::DEFAULT_TTL): void
    {
        if (!self::enabled() || $ttl <= 0) {
            return;
        }
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return;
        }
        $tmp = tempnam($dir, 'tmp');
        if ($tmp === false) {
            return;
        }
        file_put_contents($tmp, json_encode(['status' => $status, 'headers' => $headers, 'body' => $body, 'expires' => time() + $ttl, 'created' => time()]));
        @rename($tmp, self::file($key));
    }

    public static function flush(): int
    {
        $n = 0;
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            if (@unlink($file)) {
                $n++;
            }
        }
        return $n;
    }

    public static function stats(): array
    {
        $files = glob(self::dir() . '/*.json') ?: [];
        $bytes = 0;
        foreach ($files as $f) {
            $bytes += (int) @filesize($f);
        }
        return ['entries' => count($files), 'bytes' => $bytes];
    }
}
