<?php

declare(strict_types=1);

/**
 * HTTP smoke test against a running site (local or production). Read-only: only GET requests.
 *
 *   php tests/http-smoke.php http://vermaaccounting.host
 *   php tests/http-smoke.php https://vermaaccounting.ca /some-cms-page /blog/some-article
 *
 * Extra arguments are CMS paths expected to return 200 with a self-referencing canonical.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = rtrim((string) ($argv[1] ?? ''), '/');
if (!preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Usage: php tests/http-smoke.php <base-url> [cms-path ...]\n");
    exit(2);
}

$failures = 0;
function fetch_url(string $url): array
{
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'VermaCMS-Smoke/1.0',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => is_string($body) ? $body : '', 'error' => $error];
}

function expect(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) {
        $failures++;
    }
}

echo "Smoke testing {$base}\n";

$home = fetch_url($base . '/');
expect('homepage returns 200', $home['status'] === 200, 'got ' . $home['status'] . ' ' . $home['error']);
expect('homepage has one <title>', substr_count(strtolower($home['body']), '<title') === 1);
expect('homepage has a canonical', (bool) preg_match('/<link[^>]+rel="canonical"/i', $home['body']));

$missing = fetch_url($base . '/this-page-should-not-exist-' . bin2hex(random_bytes(4)));
expect('unknown URL returns 404', $missing['status'] === 404, 'got ' . $missing['status']);
expect('404 page is noindex', str_contains(strtolower(($missing['headers']['x-robots-tag'] ?? '') . $missing['body']), 'noindex'));

$robots = fetch_url($base . '/robots.txt');
expect('robots.txt returns 200', $robots['status'] === 200, 'got ' . $robots['status']);
expect('robots.txt lists the sitemap', (bool) preg_match('/^\s*sitemap:\s*https?:\/\//im', $robots['body']));
expect('robots.txt does not block the whole site', !preg_match('/^\s*disallow:\s*\/\s*$/im', $robots['body']));
expect('robots.txt has no unreplaced placeholders', !str_contains($robots['body'], '{{'));

$index = fetch_url($base . '/sitemap.xml');
expect('sitemap.xml returns 200', $index['status'] === 200, 'got ' . $index['status']);
expect('sitemap.xml is XML', str_contains($index['headers']['content-type'] ?? '', 'xml'));
$parts = [];
libxml_use_internal_errors(true);
$doc = new DOMDocument();
if ($index['body'] !== '' && $doc->loadXML($index['body'])) {
    expect('sitemap.xml is a sitemap index', $doc->documentElement?->localName === 'sitemapindex');
    foreach ($doc->getElementsByTagName('loc') as $loc) {
        $parts[] = trim($loc->textContent);
    }
} else {
    expect('sitemap.xml parses', false);
}
$checked = 0;
foreach ($parts as $partUrl) {
    $part = fetch_url($base . (string) parse_url($partUrl, PHP_URL_PATH));
    $partDoc = new DOMDocument();
    $ok = $part['status'] === 200 && $partDoc->loadXML($part['body']);
    expect('sub-sitemap ' . basename((string) parse_url($partUrl, PHP_URL_PATH)) . ' parses', $ok);
    if (!$ok) {
        continue;
    }
    foreach ($partDoc->getElementsByTagName('loc') as $loc) {
        if ($checked >= 15) {
            break;
        }
        $path = (string) parse_url(trim($loc->textContent), PHP_URL_PATH);
        $r = fetch_url($base . ($path ?: '/'));
        expect('sitemap URL ' . ($path ?: '/') . ' returns 200', $r['status'] === 200, 'got ' . $r['status']);
        $checked++;
    }
}

$admin = fetch_url($base . '/admin/cms');
expect('admin requires login', in_array($admin['status'], [302, 303, 401, 403], true), 'got ' . $admin['status']);
foreach (['/data/', '/lib/bootstrap.php', '/config.local.php', '/media/../config.local.php'] as $private) {
    $r = fetch_url($base . $private);
    expect($private . ' is not served', $r['status'] !== 200 || !str_contains($r['body'], '<?php'), 'got ' . $r['status']);
}

foreach (array_slice($argv, 2) as $path) {
    $r = fetch_url($base . $path);
    expect($path . ' returns 200', $r['status'] === 200, 'got ' . $r['status']);
    preg_match('/<link[^>]+rel="canonical"[^>]+href="([^"]+)"/i', $r['body'], $m);
    expect($path . ' canonical is self-referencing', isset($m[1]) && rtrim((string) parse_url($m[1], PHP_URL_PATH), '/') === rtrim($path, '/'), $m[1] ?? 'missing');
    expect($path . ' has one H1', substr_count(strtolower($r['body']), '<h1') === 1);
    expect($path . ' has JSON-LD', str_contains($r['body'], 'application/ld+json'));
}

echo $failures === 0 ? "\nAll checks passed.\n" : "\n{$failures} check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
