<?php

declare(strict_types=1);

/**
 * ServBay nginx "Default" rewrite is Laravel-style:
 *   try_files $uri $uri/ /index.php?$query_string;
 * so /about, /pay, /admin/invoices all execute index.php (the homepage)
 * unless we dispatch here. Apache/.htaccess and nginx.servbay.conf already
 * map those URLs; this is the fallback when ServBay is on Default.
 */
function verma_servbay_dispatch(): void
{
    $scriptFile = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $rootIndex = realpath(__DIR__ . '/../index.php');
    if ($scriptFile === false || $rootIndex === false || $scriptFile !== $rootIndex) {
        return;
    }

    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $path = '/' . trim(rawurldecode($path), '/');
    if ($path === '/' || $path === '/index' || $path === '/index.php') {
        return;
    }

    $root = dirname($rootIndex);
    $target = verma_servbay_resolve($path, $root);
    require $target ?? ($root . '/cms.php');
    exit;
}

function verma_servbay_resolve(string $path, string $root): ?string
{
    $redirects = [
        '/home' => '/',
        '/contact-us' => '/contact',
        '/our-services' => '/services',
        '/about-us' => '/about',
    ];
    if (isset($redirects[$path])) {
        header('Location: ' . $redirects[$path], true, 301);
        exit;
    }

    if (preg_match('#^/form/([a-zA-Z0-9_-]+)$#', $path, $m)) {
        $_GET['slug'] = $m[1];
        return $root . '/form.php';
    }
    if (preg_match('#^/industries/([a-z0-9-]+)$#', $path, $m)) {
        $_GET['slug'] = $m[1];
        return $root . '/industry.php';
    }
    if (preg_match('#^/(ottawa|ontario)/([a-z0-9-]+)$#', $path, $m)) {
        $_GET['city'] = $m[1];
        $_GET['slug'] = $m[2];
        return $root . '/location-seo.php';
    }
    if ($path === '/sitemap.xml') {
        return $root . '/sitemap.php';
    }
    if (preg_match('#^/sitemap-([a-z]+)\.xml$#', $path, $m)) {
        $_GET['part'] = $m[1];
        return $root . '/sitemap.php';
    }
    if ($path === '/robots.txt') {
        return $root . '/robots.php';
    }
    if (preg_match('#^/email-asset/([A-Za-z0-9._-]+)$#', $path, $m)) {
        $_GET['f'] = $m[1];
        return $root . '/email-asset.php';
    }
    if (preg_match('#^/email-open/([a-fA-F0-9]{32})(?:\.gif)?$#', $path, $m)) {
        $_GET['t'] = $m[1];
        return $root . '/email-open.php';
    }
    if ($path === '/admin') {
        return $root . '/admin/index.php';
    }
    if (preg_match('#^/api/([a-zA-Z0-9_-]+)$#', $path, $m)) {
        $file = $root . '/api/' . $m[1] . '.php';
        return is_file($file) ? $file : null;
    }

    if (!preg_match('#^/[a-zA-Z0-9/_-]+$#', $path)) {
        return null;
    }

    $relative = ltrim($path, '/');
    $first = explode('/', $relative, 2)[0];
    $blocked = ['lib', 'templates', 'tests', 'docs', 'database', 'data', 'vendor', 'config', 'cgi-bin'];
    if (in_array($first, $blocked, true)) {
        return null;
    }

    $candidate = $root . '/' . $relative . '.php';
    $realRoot = realpath($root);
    $realFile = realpath($candidate);
    if (
        is_string($realRoot)
        && is_string($realFile)
        && is_file($realFile)
        && str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)
    ) {
        return $realFile;
    }

    return null;
}
