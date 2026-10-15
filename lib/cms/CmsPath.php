<?php

declare(strict_types=1);

/**
 * Public URL rules for CMS content: lowercase, no trailing slash, hyphenated ASCII segments.
 */
final class CmsPath
{
    public const MAX_DEPTH = 6;
    public const MAX_LENGTH = 400;

    /** First path segments that belong to the application and can never be CMS URLs. */
    public const RESERVED_PREFIXES = [
        'admin', 'api', 'form', 'data', 'database', 'lib', 'components', 'css', 'js', 'images',
        'media', 'scripts', 'includes', 'config', 'templates', 'tests', 'docs', 'resources-pdf',
        'email-asset', 'email-open', 'email-unsubscribe', 'cgi-bin', 'cms', 'index', 'vendor',
        'sitemap.xml', 'robots.txt', 'llms.txt', '.well-known',
    ];

    /** Root PHP files that are application endpoints, not replaceable website pages. */
    public const PROTECTED_LEGACY = [
        'form', 'submit-documents', 'tax-intake', 'sitemap', 'robots', 'cms', 'blog-post',
        'email-asset', 'email-open', 'email-unsubscribe', 'pay', 'pay-return', 'index',
    ];

    /**
     * Canonical form of a request path. Returns '/' for the root.
     */
    public static function normalize(string $raw): string
    {
        $path = $raw;
        $cut = strcspn($path, '?#');
        $path = substr($path, 0, $cut);
        $path = rawurldecode($path);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $path = mb_strtolower($path, 'UTF-8');
        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        if ($path === '/index') {
            return '/';
        }
        $path = rtrim($path, '/');
        if ($path === '') {
            return '/';
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== '' && $s !== '.'));
        $clean = [];
        foreach ($segments as $seg) {
            if ($seg === '..') {
                array_pop($clean);
                continue;
            }
            $clean[] = rawurlencode($seg);
        }
        return $clean === [] ? '/' : '/' . implode('/', $clean);
    }

    public static function slugify(string $text): string
    {
        $text = trim($text);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($ascii) && $ascii !== '') {
                $text = $ascii;
            }
        }
        $text = strtolower($text);
        $text = str_replace(['&', '+'], [' and ', ' plus '], $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        if (strlen($text) > 120) {
            $text = rtrim(substr($text, 0, 120), '-');
        }
        return $text;
    }

    public static function isValidSlug(string $slug): bool
    {
        return $slug !== '' && strlen($slug) <= 120 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /** Join a prefix like /blog with a slug, or return the prefix itself for an empty slug. */
    public static function join(string $prefix, string $slug): string
    {
        $prefix = rtrim(self::normalize($prefix === '' ? '/' : $prefix), '/');
        if ($slug === '') {
            return $prefix === '' ? '/' : $prefix;
        }
        return $prefix . '/' . $slug;
    }

    /** @return list<string> */
    public static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    }

    /**
     * Validate a CMS content path. Returns a list of blocking error messages.
     *
     * @return list<string>
     */
    public static function validate(string $path, bool $allowRoot = false): array
    {
        $errors = [];
        if ($path === '/') {
            return $allowRoot ? [] : ['The site root is set through the homepage setting, not a URL.'];
        }
        if ($path !== self::normalize($path)) {
            $errors[] = 'URL must be lowercase, start with / and have no trailing slash (' . self::normalize($path) . ').';
            return $errors;
        }
        if (strlen($path) > self::MAX_LENGTH) {
            $errors[] = 'URL is longer than ' . self::MAX_LENGTH . ' characters.';
        }
        $segments = self::segments($path);
        if (count($segments) > self::MAX_DEPTH) {
            $errors[] = 'URL is nested more than ' . self::MAX_DEPTH . ' levels deep.';
        }
        foreach ($segments as $seg) {
            if (!self::isValidSlug($seg)) {
                $errors[] = 'Each part of the URL may only contain lowercase letters, numbers and single hyphens ("' . rawurldecode($seg) . '").';
                break;
            }
        }
        $reserved = self::reservedReason($path);
        if ($reserved !== null) {
            $errors[] = $reserved;
        }
        return $errors;
    }

    /** Why a path can never be used for CMS content, or null. */
    public static function reservedReason(string $path): ?string
    {
        $segments = self::segments($path);
        if ($segments === []) {
            return null;
        }
        $first = $segments[0];
        if (in_array($first, self::RESERVED_PREFIXES, true)) {
            return '"/' . $first . '" is used by the application and cannot be a page URL.';
        }
        if (count($segments) === 1 && in_array($first, self::PROTECTED_LEGACY, true)) {
            return '"/' . $first . '" is an application page (forms, payments or feeds) and cannot be replaced.';
        }
        if (is_dir(PROJECT_ROOT . '/' . $first) && !is_file(PROJECT_ROOT . '/' . $first . '.php')) {
            return '"/' . $first . '" is an existing folder on the server.';
        }
        return null;
    }

    /** True when an existing hand-written PHP page answers this path (e.g. /about). */
    public static function legacyPageExists(string $path): bool
    {
        if ($path === '/') {
            return true;
        }
        $segments = self::segments($path);
        if (count($segments) !== 1) {
            return false;
        }
        return self::isLegacyPageName($segments[0]) && is_file(PROJECT_ROOT . '/' . $segments[0] . '.php');
    }

    private static function isLegacyPageName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name)
            && !in_array($name, self::PROTECTED_LEGACY, true)
            && !in_array($name, self::RESERVED_PREFIXES, true);
    }

    /** Paths of all hand-written public PHP pages, for inventories and sitemaps. */
    public static function legacyPages(): array
    {
        $out = ['/'];
        foreach (glob(PROJECT_ROOT . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (!self::isLegacyPageName($name) || $name === 'blog') {
                continue;
            }
            $out[] = '/' . $name;
        }
        if (is_file(PROJECT_ROOT . '/blog.php')) {
            $out[] = '/blog';
        }
        sort($out);
        return array_values(array_unique($out));
    }

    /**
     * Validate a redirect / canonical target. Internal targets are normalized paths; external must be http(s).
     *
     * @return array{ok: bool, value: string, internal: bool, error: string}
     */
    public static function parseTarget(string $target, string $baseUrl): array
    {
        $target = trim($target);
        if ($target === '') {
            return ['ok' => false, 'value' => '', 'internal' => true, 'error' => 'Target is empty.'];
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $target)) {
            return ['ok' => false, 'value' => '', 'internal' => true, 'error' => 'Target contains control characters.'];
        }
        if (str_starts_with($target, '//')) {
            return ['ok' => false, 'value' => '', 'internal' => false, 'error' => 'Protocol-relative URLs are not allowed.'];
        }
        if ($target[0] === '/') {
            $query = '';
            $qpos = strpos($target, '?');
            if ($qpos !== false) {
                $query = substr($target, $qpos);
                $target = substr($target, 0, $qpos);
            }
            return ['ok' => true, 'value' => self::normalize($target) . $query, 'internal' => true, 'error' => ''];
        }
        $parts = parse_url($target);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            return ['ok' => false, 'value' => '', 'internal' => false, 'error' => 'Use a path like /page or a full https:// URL.'];
        }
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $host = strtolower((string) $parts['host']);
        if ($baseHost !== '' && ($host === $baseHost || $host === 'www.' . $baseHost)) {
            $path = self::normalize((string) ($parts['path'] ?? '/'));
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            return ['ok' => true, 'value' => $path . $query, 'internal' => true, 'error' => ''];
        }
        return ['ok' => true, 'value' => $target, 'internal' => false, 'error' => ''];
    }
}
