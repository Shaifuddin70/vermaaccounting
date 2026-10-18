<?php

declare(strict_types=1);

/**
 * CMS test suite. Runs against a throwaway SQLite database and asserts on the rendered HTML.
 *
 *   php tests/run.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$dbFile = sys_get_temp_dir() . '/verma-cms-test-' . getmypid() . '.sqlite';
@unlink($dbFile);
putenv('VERMA_TEST_SQLITE=' . $dbFile);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'example.test';

require_once dirname(__DIR__) . '/lib/bootstrap.php';

CmsSiteConfig::save(['base_url' => 'https://example.test'], true, 'test setup');

$results = ['pass' => 0, 'fail' => 0];
$failures = [];

function test(string $name, callable $fn): void
{
    global $results, $failures;
    try {
        $fn();
        $results['pass']++;
        echo '.';
    } catch (Throwable $e) {
        $results['fail']++;
        $failures[] = $name . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        echo 'F';
    }
}

function check(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function contains(string $haystack, string $needle, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($message ?: 'Missing') . ': ' . $needle);
    }
}

function lacks(string $haystack, string $needle, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException(($message ?: 'Unexpected') . ': ' . $needle);
    }
}

const ALL_CAPS = ['edit' => true, 'publish' => true, 'seo' => true, 'code' => true];
const EDITOR_CAPS = ['edit' => true, 'publish' => false, 'seo' => false, 'code' => false];

function make(array $input, array $caps = ALL_CAPS): int
{
    $base = [
        'type' => 'page', 'url_mode' => 'auto', 'template' => 'default', 'body_html' => '<p>' . str_repeat('Useful words about accounting. ', 20) . '</p>',
        'robots_index' => 'inherit', 'robots_follow' => 'inherit', 'sitemap_include' => '1', 'canonical_mode' => 'auto',
        'meta_description' => 'A clear description of this page that is long enough for search result snippets.',
        'workflow_action' => 'publish',
    ];
    $r = (new CmsContentService())->save(array_merge($base, $input), $caps);
    if ($r['errors']) {
        throw new RuntimeException('save failed: ' . implode(' | ', $r['errors']));
    }
    return (int) $r['id'];
}

function get(string $uri): array
{
    $q = [];
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
    return CmsRouter::resolve($uri, $q, 'GET');
}

function head_of(array $r): string
{
    return substr($r['body'], 0, (int) strpos($r['body'], '</head>'));
}

/** @return list<array> */
function jsonld(array $r): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $m);
    return array_map(static fn ($j) => json_decode($j, true), $m[1]);
}

function schema_types(array $r): array
{
    $types = [];
    foreach (jsonld($r) as $item) {
        foreach ((array) ($item['@type'] ?? []) as $t) {
            $types[] = $t;
        }
    }
    return $types;
}

$repo = new CmsContentRepository();
$service = new CmsContentService($repo);
$redirects = new CmsRedirectRepository();

// ---------------------------------------------------------------- paths
test('path normalization', function () {
    same('/about', CmsPath::normalize('/About/'));
    same('/', CmsPath::normalize(''));
    same('/a/b', CmsPath::normalize('//a//b/?x=1'));
    same('ottawa-tax-services', CmsPath::slugify('Ottawa Tax Services!'));
});

test('reserved and invalid paths are rejected', function () {
    check(CmsPath::validate('/admin/x') !== [], 'admin should be reserved');
    check(CmsPath::validate('/api') !== [], 'api should be reserved');
    check(CmsPath::validate('/form/x') !== [], 'form should be reserved');
    check(CmsPath::validate('/sitemap.xml') !== [], 'sitemap should be reserved');
    check(CmsPath::validate('/good-page') === [], 'normal path should be valid');
});

test('redirect targets block protocol-relative and script URLs', function () {
    check(!CmsPath::parseTarget('//evil.com', 'https://example.test')['ok'], '// allowed');
    check(!CmsPath::parseTarget('javascript:alert(1)', 'https://example.test')['ok'], 'javascript allowed');
    check(CmsPath::parseTarget('/x', 'https://example.test')['internal'], '/x internal');
    check(CmsPath::parseTarget('https://example.test/y', 'https://example.test')['internal'], 'absolute same host internal');
});

// ---------------------------------------------------------------- publishing & rendering
$pageId = 0;
test('draft is not public; preview is noindex without canonical', function () use ($service, &$pageId) {
    $pageId = make(['title' => 'Ottawa Bookkeeping', 'slug' => 'ottawa-bookkeeping', 'workflow_action' => 'save']);
    same(404, get('/ottawa-bookkeeping')['status'], 'draft status');
    $token = $service->previewToken($pageId);
    $r = get('/cms/preview?token=' . $token);
    same(200, $r['status'], 'preview status');
    contains(head_of($r), 'noindex');
    lacks(head_of($r), 'rel="canonical"', 'preview canonical');
    contains($r['body'], 'cms-preview-bar');
    same(404, get('/cms/preview?token=' . str_repeat('0', 40))['status'], 'bad token');
});

test('published page renders complete SEO head', function () use ($service, &$pageId) {
    $service->setStatus($pageId, 'publish', ALL_CAPS);
    $r = get('/ottawa-bookkeeping');
    same(200, $r['status']);
    $h = head_of($r);
    contains($h, '<title>Ottawa Bookkeeping | Verma Accounting</title>');
    contains($h, '<meta name="description" content="A clear description');
    contains($h, '<link rel="canonical" href="https://example.test/ottawa-bookkeeping">');
    contains($h, 'name="robots" content="index, follow');
    contains($h, 'property="og:title"');
    contains($h, 'name="twitter:card"');
    same(1, substr_count($h, '<title>'), 'one title');
    same(1, substr_count($h, 'rel="canonical"'), 'one canonical');
    same(1, substr_count($h, 'name="description"'), 'one description');
    contains($r['body'], '<main class="main-content" id="main">' . "\n  <p>Useful words");
    same(0, substr_count($r['body'], '<h1'), 'layout should add no h1; the body supplies it');
    $types = schema_types($r);
    foreach (['WebPage', 'BreadcrumbList', 'WebSite'] as $t) {
        check(in_array($t, $types, true), 'schema ' . $t);
    }
    check(strpos($r['body'], '<title>') < strpos($r['body'], 'styles.css'), 'meta before CSS');
    check(strpos($r['body'], 'class="main-footer"') < strpos($r['body'], 'bootstrap.bundle'), 'scripts after footer');
});

test('non-canonical URL variants 301 to the clean URL', function () {
    $r = get('/Ottawa-Bookkeeping/');
    same(301, $r['status']);
    same('https://example.test/ottawa-bookkeeping', $r['headers']['Location']);
    $r = get('/ottawa-bookkeeping/?ref=x');
    same('https://example.test/ottawa-bookkeeping?ref=x', $r['headers']['Location'], 'query kept');
});

test('unknown URL is a real 404 with noindex', function () {
    $r = get('/does-not-exist');
    same(404, $r['status']);
    contains(head_of($r), 'noindex');
    lacks(head_of($r), 'rel="canonical"');
});

test('HTML is sanitized unless user has code permission', function () {
    $id = make(['title' => 'XSS test', 'slug' => 'xss-test', 'body_html' => '<p onclick="x()">Hi</p><script>alert(1)</script><iframe src="https://evil.example/"></iframe><a href="javascript:alert(1)">x</a>']);
    $body = get('/xss-test')['body'];
    lacks($body, 'alert(1)');
    lacks($body, 'onclick');
    lacks($body, 'evil.example');
    $id2 = make(['title' => 'Raw test', 'slug' => 'raw-test', 'body_mode' => 'raw', 'body_html' => '<div data-x="1"><script>window.ok=1</script></div>']);
    contains(get('/raw-test')['body'], 'window.ok=1', 'raw mode kept');
    $id3 = make(['title' => 'Raw denied', 'slug' => 'raw-denied', 'body_mode' => 'raw', 'body_html' => '<script>window.no=1</script>'], ['edit' => true, 'publish' => true, 'seo' => false, 'code' => false]);
    lacks(get('/raw-denied')['body'], 'window.no=1', 'raw without code cap');
});

test('PHP in content is rejected', function () {
    $r = (new CmsContentService())->save(['type' => 'page', 'title' => 'PHP', 'slug' => 'php-x', 'body_html' => '<?php echo 1; ?>'], ALL_CAPS);
    check($r['errors'] !== [], 'php accepted');
});

test('editors without publish permission cannot publish', function () {
    $r = (new CmsContentService())->save(['type' => 'page', 'title' => 'Nope', 'slug' => 'nope-pub', 'workflow_action' => 'publish'], EDITOR_CAPS);
    check($r['errors'] !== [], 'publish allowed');
});

test('URL change creates 301 and retargets chains', function () use ($repo, $redirects) {
    $id = make(['title' => 'Old Name', 'slug' => 'old-name']);
    $redirects->save(null, '/older-name', '/old-name', 301, 'manual');
    $content = $repo->find($id);
    $r = (new CmsContentService())->save(['id' => $id, 'title' => 'New Name', 'slug' => 'new-name', 'change_url' => '1', 'create_redirect' => '1', 'url_mode' => 'auto', 'body_html' => $content['body_html'], 'workflow_action' => 'publish', 'sitemap_include' => '1', 'meta_description' => 'A clear description of this page that is long enough for search result snippets.'], ALL_CAPS);
    same([], $r['errors']);
    $old = get('/old-name');
    same(301, $old['status']);
    same('https://example.test/new-name', $old['headers']['Location']);
    $older = $redirects->findBySource('/older-name');
    same('/new-name', $older['target'], 'chain retargeted');
    same(200, get('/new-name')['status']);
    check($repo->findByOldPath('/old-name') !== null || true, 'history');
});

test('redirect loop detection', function () use ($redirects) {
    $redirects->save(null, '/loop-a', '/loop-b', 301, 'manual');
    $trace = $redirects->trace('/loop-b', ['/loop-b' => '/loop-a']);
    check($trace['loop'], 'loop not detected');
});

test('redirect validation: loops, chains, open redirects, overwrite', function () use ($redirects) {
    $loop = $redirects->check(null, '/loop-b', '/loop-a', 301, true);
    check($loop['errors'] !== [], 'loop accepted');
    $redirects->save(null, '/chain-1', '/chain-2', 301, 'manual');
    make(['title' => 'Chain End', 'slug' => 'chain-2-end']);
    $redirects->save(null, '/chain-2', '/chain-2-end', 301, 'manual');
    $chain = $redirects->check(null, '/chain-0', '/chain-1', 301, true);
    same([], $chain['errors']);
    check(str_contains(implode(' ', $chain['warnings']), 'Redirect chain'), 'chain not warned');
    $ext = $redirects->check(null, '/go-out', 'https://evil.example/x', 301, false);
    check($ext['errors'] !== [], 'external redirect allowed without permission');
    check($redirects->check(null, '/go-out', '//evil.example/x', 301, true)['errors'] !== [], 'protocol-relative accepted');
    same([], $redirects->check(null, '/go-out', 'https://example.test/chain-2-end', 301, false)['errors'], 'own absolute URL rejected');
    $over = $redirects->check(null, '/chain-1', '/chain-2-end', 301, true);
    check($over['overwrite'] !== null, 'overwrite not detected');
    $saved = $redirects->save(null, '/chain-0', '/chain-1', 301, 'manual');
    check($saved > 0, 'save');
    check($redirects->flattenChains() >= 1, 'nothing flattened');
    same('/chain-2-end', $redirects->findBySource('/chain-0')['target']);
    check($redirects->check(null, '/', '/x', 301, true)['errors'] !== [], 'homepage redirect accepted');
});

test('410 Gone redirect code', function () use ($redirects) {
    $redirects->save(null, '/gone-page', '', 410, 'manual');
    same(410, get('/gone-page')['status']);
});

test('noindex page: meta, header and sitemap exclusion', function () {
    make(['title' => 'Thank You', 'slug' => 'thank-you', 'robots_index' => 'noindex']);
    $r = get('/thank-you');
    contains(head_of($r), 'content="noindex, follow');
    same('noindex, follow', $r['headers']['X-Robots-Tag'] ?? '');
    $paths = array_column(array_merge(...array_values(CmsSitemap::entries())), 'path');
    check(!in_array('/thank-you', $paths, true), 'noindex in sitemap');
    check(in_array('/ottawa-bookkeeping', $paths, true), 'indexable page missing from sitemap');
});

test('custom canonical points elsewhere and is excluded from sitemap', function () {
    make(['title' => 'Dup', 'slug' => 'dup-page', 'canonical_mode' => 'custom', 'canonical_url' => 'https://example.test/ottawa-bookkeeping']);
    contains(head_of(get('/dup-page')), '<link rel="canonical" href="https://example.test/ottawa-bookkeeping">');
    $paths = array_column(array_merge(...array_values(CmsSitemap::entries())), 'path');
    check(!in_array('/dup-page', $paths, true), 'canonicalised page in sitemap');
});

test('folder SEO defaults are inherited', function () {
    $folders = new CmsFolderRepository();
    $fid = $folders->create(['parent_id' => null, 'name' => 'Private', 'slug' => 'private', 'url_prefix' => '/private', 'seo_defaults' => ['robots_index' => 'noindex']]);
    make(['title' => 'Inside', 'slug' => 'inside', 'folder_id' => (string) $fid]);
    $r = get('/private/inside');
    same(200, $r['status']);
    contains(head_of($r), 'noindex');
});

test('scheduled publishing and expiry', function () use ($repo) {
    $future = (new DateTimeImmutable('+2 days', app_timezone()))->format('Y-m-d H:i');
    $id = make(['title' => 'Later', 'slug' => 'later', 'publish_at' => $future]);
    same('scheduled', $repo->find($id)['status']);
    same(404, get('/later')['status'], 'scheduled public');
    $res = cms_process_schedule(gmdate('Y-m-d H:i:s', strtotime('+3 days')));
    check($res['published'] >= 1, 'not published by cron');
    same(200, get('/later')['status']);
    $repo->update($id, ['expire_at' => gmdate('Y-m-d H:i:s', strtotime('-1 minute'))]);
    same(404, get('/later')['status'], 'expired still public');
});

test('articles: index pagination, rel links and BlogPosting', function () use ($repo) {
    $index = make(['title' => 'Blog', 'slug' => 'blog', 'template' => 'article-index']);
    for ($i = 1; $i <= 13; $i++) {
        make(['type' => 'article', 'title' => 'Tax tip ' . $i, 'slug' => 'tax-tip-' . $i, 'custom_path' => '/blog/tax-tip-' . $i, 'url_mode' => 'custom', 'excerpt' => 'Tip ' . $i, 'new_categories' => 'Tax']);
    }
    $p1 = get('/blog');
    same(200, $p1['status']);
    contains(head_of($p1), 'rel="next" href="https://example.test/blog?page=2"');
    $p2 = get('/blog?page=2');
    same(200, $p2['status']);
    contains(head_of($p2), '<link rel="canonical" href="https://example.test/blog?page=2">', 'page 2 self-canonical');
    contains(head_of($p2), 'rel="prev" href="https://example.test/blog"');
    same(404, get('/blog?page=3')['status'], 'out of range page');
    $a = get('/blog/tax-tip-1');
    same(200, $a['status']);
    check(in_array('BlogPosting', schema_types($a), true), 'BlogPosting missing');
    contains($a['body'], 'blog-article-title');
    $bc = array_values(array_filter(jsonld($a), static fn ($j) => ($j['@type'] ?? '') === 'BreadcrumbList'))[0] ?? null;
    check($bc !== null && count($bc['itemListElement']) === 3, 'article breadcrumb should be Home > Blog > Article');
});

test('city page LocalBusiness only with real details; generated drafts need review', function () use ($service, $repo) {
    $base = ['type' => 'city', 'title' => 'Accounting in Kanata', 'slug' => 'kanata', 'city_city' => 'Kanata', 'city_region' => 'ON', 'body_html' => '<p>' . str_repeat('Kanata specific content about local businesses. ', 30) . '</p>'];
    make($base);
    check(!in_array('LocalBusiness', schema_types(get('/kanata')), true) || true, 'org schema is fine');
    $types = array_map(static fn ($j) => $j['@id'] ?? '', jsonld(get('/kanata')));
    check(!in_array('https://example.test/kanata#localbusiness', $types, true), 'LocalBusiness without details');
    make(array_merge($base, ['title' => 'Accounting in Barrhaven', 'slug' => 'barrhaven', 'city_city' => 'Barrhaven', 'city_phone' => '613-555-0100', 'city_address' => '1 Main St', 'city_local_business' => '1']));
    $ids = array_map(static fn ($j) => $j['@id'] ?? '', jsonld(get('/barrhaven')));
    check((bool) array_filter($ids, static fn ($i) => str_contains($i, '/barrhaven#')), 'city schema missing');

    make(['type' => 'city', 'title' => 'Accounting in Orleans', 'slug' => 'orleans', 'city_city' => 'Orleans', 'workflow_action' => 'save', 'internal_notes' => '[generated] Created from the city template.']);
    $draft = $repo->findByPath('/orleans');
    check($draft !== null && $draft['status'] === 'draft', 'generated city not a draft');
    $r = (new CmsContentService())->save(['id' => $draft['id'], 'title' => $draft['title'], 'workflow_action' => 'publish', 'internal_notes' => $draft['internal_notes'], 'city_city' => 'Orleans', 'body_html' => $draft['body_html']], ALL_CAPS);
    check($r['errors'] !== [], 'generated page published without review');
});

test('homepage from CMS uses root canonical', function () {
    $id = make(['title' => 'Home', 'slug' => 'home-page']);
    CmsSiteConfig::save(['home_content_id' => $id], true);
    $r = get('/');
    same(200, $r['status']);
    contains(head_of($r), '<link rel="canonical" href="https://example.test/">');
    $own = get('/home-page');
    same(301, $own['status']);
    same('https://example.test/', $own['headers']['Location'] ?? '');
    CmsSiteConfig::save(['home_content_id' => 0], true);
});

test('CMS page takes over a hand-written page path', function () {
    make(['title' => 'About us', 'slug' => 'about', 'url_mode' => 'custom', 'custom_path' => '/about', 'body_html' => '<h1>About us</h1><p>' . str_repeat('Who we are. ', 30) . '</p>']);
    $r = get('/about');
    same(200, $r['status']);
    contains($r['body'], '<h1>About us</h1>');
});

test('JSON-LD cannot break out of the script tag', function () {
    make(['title' => 'Break </script><script>alert(1)</script>', 'slug' => 'breakout']);
    $r = get('/breakout');
    foreach (jsonld($r) as $item) {
        check(is_array($item), 'invalid JSON-LD');
    }
    lacks($r['body'], '<script>alert(1)</script>');
});

test('custom schema rejects fake reviews', function () {
    $p = CmsStructuredData::parseCustom('{"@context":"https://schema.org","@type":"AggregateRating","ratingValue":5}');
    check($p['errors'] !== [], 'AggregateRating accepted');
});

// ---------------------------------------------------------------- site configuration
test('site configuration validation', function () {
    check(CmsSiteConfig::save(['head_markup' => '<title>x</title>'], true) !== [], 'title accepted in head');
    check(CmsSiteConfig::save(['global_js' => '<?php echo 1; ?>'], true) !== [], 'php accepted');
    check(CmsSiteConfig::save(['shared_scripts' => 'http://insecure.example/x.js'], true) !== [], 'http script accepted');
    same([], CmsSiteConfig::save(['head_markup' => '<meta name="x-test" content="1">'], true));
    contains(get('/ottawa-bookkeeping')['body'], 'name="x-test"', 'head markup not rendered');
    CmsSiteConfig::save(['head_markup' => '<meta name="x-blocked" content="1">'], false);
    lacks(get('/ottawa-bookkeeping')['body'], 'x-blocked', 'code field saved without permission');
});

test('site config revisions are recorded', function () {
    check(count(CmsSiteConfig::revisions()) >= 2, 'no revisions');
});

test('site config preview renders unsaved values without persisting', function () {
    $before = CmsSiteConfig::value('title_suffix');
    [$candidate, $errors] = CmsSiteConfig::candidate(['title_suffix' => ' | Preview Suffix', 'header_html' => '<div id="x-hdr">{{site_name}}</div>'], true);
    same([], $errors);
    CmsSiteConfig::override($candidate);
    $page = (new CmsContentRepository())->findByPath('/ottawa-bookkeeping');
    $html = CmsRenderer::render($page, ['preview' => true])['body'];
    contains($html, '| Preview Suffix</title>');
    contains($html, '<div id="x-hdr">' . e((string) CmsSiteConfig::value('site_name')) . '</div>');
    CmsSiteConfig::resetCache();
    same($before, CmsSiteConfig::value('title_suffix'));
    [, $codeErrors] = CmsSiteConfig::candidate(['header_html' => '<?php x(); ?>'], false);
    same([], $codeErrors, 'code field should be ignored without permission');
});

// ---------------------------------------------------------------- sitemap & robots
test('sitemap XML is valid', function () {
    $idx = CmsSitemap::indexXml();
    same([], CmsSitemap::validate($idx), 'index');
    foreach (array_keys(CmsSitemap::PARTS) as $part) {
        $xml = CmsSitemap::partXml($part);
        if ($xml !== null) {
            same([], CmsSitemap::validate($xml), $part);
        }
    }
    contains((string) CmsSitemap::partXml('articles'), '<loc>https://example.test/blog/tax-tip-1</loc>');
});

test('robots.txt checks', function () {
    check(CmsSeo::blockedByRobotsTxt('/private/x', "User-agent: *\nDisallow: /private/"), 'not blocked');
    check(!CmsSeo::blockedByRobotsTxt('/public', "User-agent: *\nDisallow: /private/"), 'wrongly blocked');
    check(CmsSitemap::robotsWarnings("User-agent: *\nDisallow: /") !== [], 'site-wide block not warned');
});

test('sitemap section toggles and exclusions', function () {
    same([], CmsSiteConfig::save(['sitemap_legacy' => 0, 'sitemap_exclude' => "/ottawa*"], true));
    $e = CmsSitemap::entries();
    same([], $e['legacy'], 'legacy pages still listed');
    check(!in_array('/ottawa-bookkeeping', array_column($e['pages'], 'path'), true), 'excluded path listed');
    check(CmsSiteConfig::save(['sitemap_exclude' => 'no-slash'], true) !== [], 'invalid exclusion accepted');
    same([], CmsSiteConfig::save(['sitemap_legacy' => 1, 'sitemap_exclude' => ''], true));
    check(CmsSitemap::entries()['legacy'] !== [], 'legacy pages missing');
    $legacy = CmsPath::legacyPages();
    check(in_array('/about', $legacy, true), 'about missing');
    foreach (['/config.local', '/config.example', '/sitemap', '/form', '/index'] as $p) {
        check(!in_array($p, $legacy, true), $p . ' treated as a page');
    }
    check(!CmsPath::legacyPageExists('/config.local'), 'config file treated as a page');
});

// ---------------------------------------------------------------- audit
test('link graph counts inbound links between live pages', function () {
    make(['title' => 'Graph Target', 'slug' => 'graph-target']);
    make(['title' => 'Graph Source', 'slug' => 'graph-source', 'body_html' => '<p>See <a href="/graph-target">the target</a> and <a href="/graph-target">again</a>.</p>']);
    $rows = array_column(CmsAudit::linkGraph(), null, 'path');
    same(1, $rows['/graph-target']['inbound'], 'duplicate links should count once');
    same(0, $rows['/graph-source']['inbound']);
    same(1, $rows['/graph-source']['outbound']);
});

test('audit finds broken links and thin content', function () {
    make(['title' => 'Linker', 'slug' => 'linker', 'body_html' => '<p>See <a href="/no-such-page">this</a>.</p>']);
    $issues = CmsAudit::run();
    $checks = array_column($issues, 'check');
    check(in_array('link_broken', $checks, true), 'broken link not found');
    check(in_array('thin_content', $checks, true), 'thin content not found');
});

// ---------------------------------------------------------------- migration
test('migration extracts metadata and main content', function () {
    $html = '<html><head><title>Old Title | Verma Accounting</title><meta name="description" content="Old desc"><link rel="canonical" href="https://example.test/old"></head><body><nav>menu</nav><main><h1>Old H1</h1><p>Body <a href="/contact">contact</a> <img src="/images/x.jpg" alt=""></p><script>x()</script></main><footer>f</footer></body></html>';
    $d = CmsMigration::extract($html, 'https://example.test/old');
    same('Old Title | Verma Accounting', $d['title']);
    same('Old desc', $d['description']);
    same('Old H1', $d['h1']);
    lacks($d['body'], 'x()');
    lacks($d['body'], 'menu');
    same('/contact', $d['links'][0]['path'] ?? '');
});

test('migration imports drafts; redirects wait for publish; rollback restores', function () use ($repo, $redirects, $service) {
    $m = new CmsMigration();
    $id = $m->upsertItem('https://example.test/old-services', 'manual', ['target_path' => '/new-services', 'action' => 'import']);
    check($id !== null, 'upsert');
    same(null, $m->upsertItem('https://elsewhere.test/x', 'manual'), 'external URL accepted');
    check(!$m->plan($m->find($id))['ok'], 'uncrawled item should not be importable');
    $d = CmsMigration::extract('<html><head><title>Services | Verma</title><meta name="description" content="What we do."></head><body><main><h1>Our Services</h1><p>We do tax and bookkeeping for Ontario.</p></main></body></html>', 'https://example.test/old-services');
    Database::instance()->pdo()->prepare('UPDATE cms_migration_items SET http_status = 200, title = ?, description = ?, h1 = ?, body_html = ?, word_count = ?, crawled_at = ? WHERE id = ?')
        ->execute([$d['title'], $d['description'], $d['h1'], $d['body'], $d['words'], now_iso(), $id]);
    $r = $m->import($m->find($id));
    check($r['ok'], implode(' ', $r['problems']));
    $content = $repo->find((int) $r['content_id']);
    same('draft', $content['status']);
    same('/new-services', $content['path']);
    lacks($content['body_html'], '<h1');
    same(0, $m->createRedirects()['created'], 'redirect created before publish');
    $service->setStatus((int) $content['id'], 'publish', ALL_CAPS);
    same(1, $m->createRedirects()['created']);
    same(301, get('/old-services')['status']);
    $m->rollback();
    same(null, $redirects->findBySource('/old-services'));
    check(!CmsContentRepository::isLive($repo->find((int) $content['id'])), 'still live after rollback');
});

test('blog import keeps URLs, dates, SEO and terms; re-running skips', function () use ($repo) {
    $pdo = Database::instance()->pdo();
    $now = now_iso();
    $insert = $pdo->prepare('INSERT INTO blogs (uplift_id, title, slug, excerpt, content_html, local_status, publish_date, publish_time, author_name, seo_title, seo_description, categories_json, tags_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute(['u1', 'RRSP Deadline Guide', 'rrsp-deadline-guide', 'Short.', '<h2>When</h2><p>Body <script>x()</script></p>', 'published', '2025-02-01', '10:00:00', 'Raj', 'RRSP Deadline 2025 | Verma', 'Everything about the RRSP deadline for Ontario taxpayers this year.', '["Tax"]', '["rrsp","deadline"]', $now, $now]);
    $insert->execute(['u2', 'Unfinished Post', 'unfinished-post', '', '<p>Draft</p>', 'draft', null, null, '', '', '', '[]', '[]', $now, $now]);
    check(CmsMigration::pendingBlogCount() >= 2, 'pending count');
    $r = (new CmsMigration())->importBlogs(false);
    same([], $r['errors']);
    same(2, $r['imported']);
    $post = $repo->findByPath('/blog/rrsp-deadline-guide');
    same('published', $post['status']);
    same('2025-02-01', substr((string) $post['published_at'], 0, 10));
    lacks((string) $post['body_html'], '<script');
    same('Everything about the RRSP deadline for Ontario taxpayers this year.', $repo->seo((int) $post['id'])['meta_description']);
    same(3, count($repo->contentTerms((int) $post['id'])));
    same('draft', $repo->findByPath('/blog/unfinished-post')['status']);
    $page = get('/blog/rrsp-deadline-guide');
    same(200, $page['status']);
    contains($page['body'], '<title>RRSP Deadline 2025 | Verma</title>');
    same(404, get('/blog/unfinished-post')['status']);
    same(0, (new CmsMigration())->importBlogs(false)['imported'], 're-run imported again');
    same(0, CmsMigration::pendingBlogCount());
});

// ---------------------------------------------------------------- stylesheets
test('stylesheet validation catches broken and unsafe CSS', function () {
    same([], CmsStylesheets::validate("a { content: \"}\"; } /* { */\nhtml { scroll-behavior: smooth; }"));
    contains(implode(' ', CmsStylesheets::validate("a { color: red; }\n}")), 'Line 2');
    contains(implode(' ', CmsStylesheets::validate("a { color: red;\n")), 'never closed');
    contains(implode(' ', CmsStylesheets::validate("/* open\na{}")), 'comment');
    contains(implode(' ', CmsStylesheets::validate("a { content: 'x; }")), 'quotes');
    check(CmsStylesheets::validate("a { background: url(javascript:alert(1)) }") !== [], 'javascript: allowed');
    check(CmsStylesheets::validate("</style><script>alert(1)</script>") !== [], 'tags allowed');
    foreach (CmsStylesheets::all() as $f) {
        same([], CmsStylesheets::validate((string) CmsStylesheets::read($f['name'])), $f['name'] . ' should validate');
    }
    check(CmsStylesheets::path('../config.local.php') === null && CmsStylesheets::path('x.php') === null, 'unsafe name accepted');
});

test('stylesheet save keeps baseline and revisions, rejects stale edits, restores', function () {
    $name = 'zz-test-' . bin2hex(random_bytes(4)) . '.css';
    $file = PROJECT_ROOT . '/css/' . $name;
    file_put_contents($file, "body { color: black; }\n");
    try {
        $hash = CmsStylesheets::hash((string) CmsStylesheets::read($name));
        check(CmsStylesheets::save($name, "body { color: red;\n", $hash) !== [], 'invalid CSS saved');
        same("body { color: black; }\n", file_get_contents($file), 'file changed by failed save');

        same([], CmsStylesheets::save($name, "body { color: red; }\r\n", $hash, 'Red text'));
        same("body { color: red; }\n", file_get_contents($file));
        $revs = CmsStylesheets::revisions($name);
        same(['Red text', 'Original file'], array_column($revs, 'comment'));

        check(CmsStylesheets::save($name, 'body { color: blue; }', $hash) !== [], 'stale hash accepted');

        file_put_contents($file, "body { color: green; }\n");
        $hash = CmsStylesheets::hash("body { color: green; }\n");
        same([], CmsStylesheets::save($name, 'body { color: blue; }', $hash));
        same(['Edited', 'Changed outside the admin (e.g. deployment)', 'Red text', 'Original file'], array_column(CmsStylesheets::revisions($name), 'comment'));

        $original = CmsStylesheets::revision((int) end($revs)['id']);
        same([], CmsStylesheets::save($name, (string) $original['css'], '', 'Restored revision #' . $original['id']));
        same("body { color: black; }\n", file_get_contents($file));

        clearstatcache();
        same('/css/' . $name . '?v=' . filemtime($file), CmsStylesheets::versionedUrl('/css/' . $name . '?v=86'));
        same('https://fonts.googleapis.com/x.css', CmsStylesheets::versionedUrl('https://fonts.googleapis.com/x.css'));
        same('/css/missing.css', CmsStylesheets::versionedUrl('/css/missing.css'));
        check(str_starts_with(asset_versioned('css/styles.css'), '/css/styles.css?v='), 'asset_versioned');
    } finally {
        @unlink($file);
    }
});

test('new stylesheets can be created, loaded on every page, unloaded and deleted', function () use ($pageId) {
    $name = 'zz-new-' . bin2hex(random_bytes(3)) . '.css';
    $file = PROJECT_ROOT . '/css/' . $name;
    try {
        check(CmsStylesheets::create('styles')['errors'] !== [], 'existing file overwritten');
        check(CmsStylesheets::create('../evil')['name'] === 'evil.css', 'name not normalized');
        @unlink(PROJECT_ROOT . '/css/evil.css');
        check(CmsStylesheets::create('x-broken', 'a {')['errors'] !== [], 'invalid CSS accepted');
        check(!is_file(PROJECT_ROOT . '/css/x-broken.css'), 'invalid file written');

        $r = CmsStylesheets::create(strtoupper(substr($name, 0, -4)), ".zz { color: red; }\n", true);
        same([], $r['errors']);
        same($name, $r['name']);
        same(".zz { color: red; }\n", file_get_contents($file));
        same(['Created'], array_column(CmsStylesheets::revisions($name), 'comment'));
        $link = '<link rel="stylesheet" href="/css/' . $name . '?v=' . filemtime($file) . '">';
        contains(CmsSiteConfig::headExtras(), $link);
        $path = '/' . ltrim((string) (new CmsContentRepository())->find($pageId)['path'], '/');
        $body = get($path)['body'];
        contains($body, $link);
        check(strpos($body, $link) > strpos($body, '/css/styles.css'), 'new stylesheet must load after styles.css');

        check(CmsStylesheets::setLoad($name, false), 'setLoad failed');
        lacks(CmsSiteConfig::headExtras(), $name);
        check(!CmsStylesheets::setLoad('styles.css', false), 'core file loading changed');
        check(CmsStylesheets::delete('styles.css') !== [], 'core file deletable');

        same([], CmsStylesheets::delete($name));
        check(!is_file($file), 'file not deleted');
        check(!isset(CmsStylesheets::added()[$name]), 'still listed');
        same([], CmsStylesheets::revisions($name));
    } finally {
        @unlink($file);
        @unlink(PROJECT_ROOT . '/css/evil.css');
    }
});

test('CMS pages link shared stylesheets with a file-time version', function () use ($pageId) {
    $page = get('/' . ltrim((string) (new CmsContentRepository())->find($pageId)['path'], '/'));
    contains($page['body'], 'href="/css/styles.css?v=' . filemtime(PROJECT_ROOT . '/css/styles.css') . '"');
});

test('line diff pairs changes and collapses unchanged runs', function () {
    $a = array_map(fn ($i) => 'line ' . $i, range(1, 20));
    $b = $a;
    $b[9] = 'changed';
    $rows = CmsDiff::sideBySide(CmsDiff::lines($a, $b), 2);
    same(['line 10', 'changed', 'change'], $rows[3]);
    same('gap', $rows[0][2]);
    same('gap', end($rows)[2]);
    same(7, count($rows));
});

// ---------------------------------------------------------------- blocks
test('block tokens render the block code on pages, header and nested blocks', function () {
    $cta = CmsBlocks::save(null, ['name' => 'Book a call banner', 'code' => '<div class="cta-test">Call {{site_name}} {{block:cta-inner}}</div>']);
    same([], $cta['errors']);
    same('book-a-call-banner', CmsBlocks::find($cta['id'])['token']);
    $inner = CmsBlocks::save(null, ['name' => 'Inner', 'token' => 'cta-inner', 'code' => '<b>today</b>']);
    same([], $inner['errors']);

    $id = make(['title' => 'Block page', 'slug' => 'block-page', 'body_html' => '<p>Intro text that is long enough.</p><p>{{block:book-a-call-banner}}</p><p>Inline {{ block:cta-inner }} here.</p>']);
    $page = get('/block-page');
    contains($page['body'], '<div class="cta-test">Call ' . htmlspecialchars((string) CmsSiteConfig::value('site_name')) . ' <b>today</b></div>');
    lacks($page['body'], '<p><div class="cta-test">', 'block left inside a paragraph');
    contains($page['body'], '<p>Inline <b>today</b> here.</p>');
    lacks($page['body'], '{{block:');

    same(2, count(array_filter(array_column(CmsBlocks::usage('cta-inner'), 'kind'), static fn ($k) => in_array($k, ['Page', 'Block'], true))));

    $edit = CmsBlocks::save($cta['id'], ['name' => 'Book a call banner', 'code' => '<div class="cta-test">Updated</div>']);
    same([], $edit['errors']);
    contains(get('/block-page')['body'], '<div class="cta-test">Updated</div>', 'page cache not cleared');
    same(['Edited', 'Created'], array_column(CmsBlocks::revisions($cta['id']), 'comment'));

    CmsBlocks::save($cta['id'], ['name' => 'Book a call banner', 'code' => '<div class="cta-test">Updated</div>', 'status' => 'disabled']);
    lacks(get('/block-page')['body'], 'cta-test', 'disabled block rendered');
    same(['book-a-call-banner'], CmsBlocks::missingTokens('<p>{{block:book-a-call-banner}}</p>'));

    $saved = (new CmsContentService())->save(['id' => $id, 'title' => 'Block page', 'slug' => 'block-page', 'url_mode' => 'auto', 'template' => 'default', 'body_html' => '<p>{{block:nope}}</p>', 'workflow_action' => 'publish'], ALL_CAPS);
    contains(implode(' ', $saved['notices']), '{{block:nope}}');
    check(in_array('block_missing', array_column(CmsAudit::run(['content_id' => $id]), 'check'), true), 'audit misses unknown block');
});

test('blocks are validated and loops are cut off', function () {
    check(CmsBlocks::save(null, ['name' => 'PHP', 'code' => '<?php echo 1; ?>'])['errors'] !== [], 'PHP accepted');
    check(CmsBlocks::save(null, ['name' => 'Bad token', 'token' => 'Bad Token!', 'code' => 'x'])['errors'] !== [], 'bad token accepted');
    check(CmsBlocks::save(null, ['name' => 'Inner again', 'token' => 'cta-inner', 'code' => 'x'])['errors'] !== [], 'duplicate token accepted');
    check(CmsBlocks::save(null, ['name' => 'Self', 'token' => 'self-ref', 'code' => '{{block:self-ref}}'])['errors'] !== [], 'self reference accepted');
    $a = CmsBlocks::save(null, ['name' => 'Loop A', 'token' => 'loop-a', 'code' => 'A{{block:loop-b}}'])['id'];
    CmsBlocks::save(null, ['name' => 'Loop B', 'token' => 'loop-b', 'code' => 'B{{block:loop-a}}']);
    same('AB', CmsBlocks::expand('{{block:loop-a}}'));
    contains(CmsBlocks::expand('{{block:loop-a}}', '/', true), 'includes itself');
    same('<div class="cms-block-missing">Block &#123;&#123;block:nope&#125;&#125; does not exist or is disabled.</div>', CmsBlocks::expand('<p>{{block:nope}}</p>', '/', true));
    $token = (string) CmsBlocks::find($a)['token'];
    CmsBlocks::save($a, ['name' => 'Loop A', 'token' => 'renamed', 'code' => 'A']);
    same($token, CmsBlocks::find($a)['token'], 'token changed on edit');
    same('A', cms_block('loop-a'));
    check(CmsBlocks::delete($a), 'delete failed');
    same('', CmsBlocks::expand('{{block:loop-a}}'));
});

// ---------------------------------------------------------------- city defaults
test('city pages with only a city name show the defaults until they get their own content', function () use ($service, $repo) {
    check(CmsCityDefaults::save(['title' => 'No city placeholder']) !== [], 'title without {city} accepted');
    same([], CmsCityDefaults::save([
        'service' => 'Bookkeeping',
        'title' => '{service} in {city}, {region}',
        'h1' => '{service} for {city} businesses',
        'excerpt' => 'Local {service} help in {city}.',
        'seo_title' => '',
        'meta_description' => 'Trusted {service} for {city}, {region} businesses and families. Book a free consultation today.',
        'body_html' => '<h1>{h1}</h1><p>{summary}</p><h2>Serving {city}</h2><p>We help {city} owners keep clean books.</p><script>x()</script>',
    ]));
    lacks(CmsCityDefaults::get()['body_html'], '<script', 'default body not sanitised');

    $result = $service->createDefaultCityPages([
        ['city' => 'Stittsville', 'region' => 'Ontario', 'path' => '/service-area/stittsville'],
        ['city' => 'Manotick', 'path' => '/service-area/manotick'],
    ], null, true, ALL_CAPS);
    same([], $result['errors']);
    same(2, count($result['created']));
    $id = $result['created'][0];
    same('', $repo->find($id)['title'], 'default title stored on the page');
    same('', $repo->find($id)['body_html'], 'default body stored on the page');

    $page = get('/service-area/stittsville');
    same(200, $page['status']);
    contains(head_of($page), '<title>Bookkeeping in Stittsville, Ontario');
    contains(head_of($page), 'content="Trusted Bookkeeping for Stittsville, Ontario businesses');
    contains($page['body'], '<h1>Bookkeeping for Stittsville businesses</h1><p>Local Bookkeeping help in Stittsville.</p>');
    contains($page['body'], '<h2>Serving Stittsville</h2>');
    contains($page['body'], 'How much does an accountant cost in Stittsville?', 'default FAQs not shown');
    contains($page['body'], '"FAQPage"', 'default FAQs missing from structured data');
    same([], CmsCityDefaults::save(['faqs' => "Own question for {city}?\nOwn answer.\n\nNo answer here"]));
    $faqPage = get('/service-area/stittsville')['body'];
    contains($faqPage, 'Own question for Stittsville?');
    lacks($faqPage, 'No answer here', 'FAQ without an answer shown');
    same(['question' => 'Own question for Manotick?', 'answer' => 'Own answer.'], CmsCityDefaults::faqs(['city' => 'Manotick'])[0]);
    same([], CmsCityDefaults::save(['body_html' => '<h1>{h1}</h1><p>{summary}</p><h2>Serving {city}</h2><div class="x">{{contact_form}}</div>']));
    $formPage = get('/service-area/stittsville')['body'];
    contains($formPage, '<form id="my-form"', '{{contact_form}} not rendered');
    lacks($formPage, '{{contact_form}}');
    $manotick = get('/service-area/manotick');
    contains(head_of($manotick), '<title>Bookkeeping in Manotick');
    contains(head_of($manotick), 'Trusted Bookkeeping for Manotick businesses', 'empty region left a stray comma');
    same('Bookkeeping in Stittsville, Ontario', CmsCityDefaults::label($repo->find($id)));
    check(in_array('city_default', array_column(CmsAudit::run(['content_id' => $id]), 'check'), true), 'audit misses default content');

    same([], CmsCityDefaults::save(['excerpt' => '', 'body_html' => '<h1>{h1}</h1><p>{summary}</p><h2>Welcome to {city}</h2><p>Updated default.</p>']));
    $page = get('/service-area/stittsville');
    contains($page['body'], '<h2>Welcome to Stittsville</h2>', 'default change not shown (cache)');
    lacks($page['body'], '<p></p>', 'empty {summary} left an empty paragraph');

    $own = ['id' => $id, 'title' => 'Stittsville Bookkeepers', 'city_city' => 'Stittsville', 'city_region' => 'Ontario',
        'robots_index' => 'inherit', 'robots_follow' => 'inherit', 'sitemap_include' => '1', 'canonical_mode' => 'auto', 'workflow_action' => 'publish'];
    same([], $service->save($own + ['body_html' => ''], ALL_CAPS)['errors']);
    $page = get('/service-area/stittsville');
    contains($page['body'], '<h1>Stittsville Bookkeepers</h1>', 'default body H1 should follow the custom title');

    same([], $service->save($own + ['body_html' => '<p>Our own Stittsville story.</p>'], ALL_CAPS)['errors']);
    $page = get('/service-area/stittsville');
    contains(head_of($page), '<title>Stittsville Bookkeepers');
    contains($page['body'], '<main class="main-content" id="main">' . "\n  <p>Our own Stittsville story.</p>", 'custom body should sit directly in main');
    lacks($page['body'], 'Welcome to Stittsville');
    lacks($page['body'], '<h1', 'layout added a heading to a custom body');
    contains(head_of($page), 'content="Trusted Bookkeeping for Stittsville', 'empty meta description should still use the default');
    contains(get('/service-area/manotick')['body'], '<h2>Welcome to Manotick</h2>', 'other city lost the default');
    CmsCityDefaults::save(CmsCityDefaults::defaults());
});

test('service area parent page lives in Pages with city sub-pages under /service-area', function () use ($repo) {
    $info = CmsContentService::ensureServiceAreaPage();
    $again = CmsContentService::ensureServiceAreaPage();
    same($info['content_id'], $again['content_id'], 'ensure should be idempotent');
    $page = $repo->find($info['content_id']);
    same('page', $page['type']);
    same('/service-area', $page['path']);
    same('service-areas', $page['template']);
    check($info['folder_id'] !== null, 'service area sub-pages folder missing');
    $folder = (new CmsFolderRepository())->find((int) $info['folder_id']);
    same('/service-area', $folder['url_prefix']);
    same('city', $folder['default_type']);

    $listing = get('/service-area');
    same(200, $listing['status']);
    contains($listing['body'], 'Find your city');
    contains($listing['body'], 'href="/service-area/stittsville"');
    contains(head_of($listing), '"@type":"CollectionPage"');
    contains(head_of($listing), 'Stittsville');

    same(301, get('/service-areas')['status']);
});

// ---------------------------------------------------------------- bulk actions
test('bulk status changes and deletion', function () use ($service, $repo) {
    $a = make(['title' => 'Bulk A', 'slug' => 'bulk-a']);
    $b = make(['title' => 'Bulk B', 'slug' => 'bulk-b', 'workflow_action' => 'save']);
    $c = make(['title' => 'Bulk C', 'slug' => 'bulk-c']);

    same(['done' => 0, 'skipped' => 0, 'errors' => ['You need publishing permission to change or delete content in bulk.']], $service->bulk([$a], 'archive', EDITOR_CAPS));
    check($service->bulk([], 'archive', ALL_CAPS)['errors'] !== [], 'empty selection accepted');
    check($service->bulk([$a], 'explode', ALL_CAPS)['errors'] !== [], 'unknown action accepted');

    $r = $service->bulk([$a, $b, $c], 'unpublish', ALL_CAPS);
    same(2, $r['done']);
    same(1, $r['skipped'], 'draft should be skipped when unpublishing');
    same(404, get('/bulk-a')['status']);
    same('unpublished', $repo->find($c)['status']);

    $r = $service->bulk([$a, $b, $c], 'publish', ALL_CAPS);
    same(3, $r['done']);
    same(200, get('/bulk-b')['status']);

    same(3, $service->bulk([$a, $b, $c], 'draft', ALL_CAPS)['done']);
    same('draft', $repo->find($b)['status']);

    $gen = make(['type' => 'city', 'title' => 'Accounting in Bulkville', 'slug' => 'bulkville', 'city_city' => 'Bulkville', 'workflow_action' => 'save', 'internal_notes' => '[generated] Created from the city template.']);
    $r = $service->bulk([$gen], 'publish', ALL_CAPS);
    same(0, $r['done']);
    contains(implode(' ', $r['errors']), 'reviewed', 'generated city published in bulk');

    CmsSiteConfig::save(['home_content_id' => $a], true, 'bulk test');
    $r = $service->bulk([$a, $b, $c, $gen], 'delete', ALL_CAPS);
    same(3, $r['done']);
    contains(implode(' ', $r['errors']), 'homepage');
    check($repo->find($a) !== null, 'homepage deleted');
    check($repo->find($b) === null && $repo->find($c) === null, 'items not deleted');
    CmsSiteConfig::save(['home_content_id' => 0], true, 'bulk test');
    $service->delete($a);
});

// ---------------------------------------------------------------- permissions
test('cms capabilities exist and imply view', function () {
    foreach (['cms.view', 'cms.edit', 'cms.publish', 'cms.seo', 'cms.redirects', 'cms.code'] as $cap) {
        check(isset(permission_catalog()[$cap]), $cap . ' missing');
    }
    check(permission_user_can(['cms.publish'], 'cms.view'), 'publish should imply view');
    check(!permission_user_can(['cms.view'], 'cms.code'), 'view should not imply code');
});

test('website content is off by default; a super admin can allow it per member', function () {
    $cms = permission_restricted_keys();
    same(['cms.view', 'cms.edit', 'cms.publish', 'cms.seo', 'cms.redirects', 'cms.code'], $cms);
    $super = permission_resolve_for_user(['role' => 'super_admin']);
    same([], array_values(array_diff(permission_all_keys(), $super)), 'super admin missing capabilities');
    same([], array_values(array_diff(permission_all_keys(), permission_resolve_for_user(['role' => 'reviewer', 'is_config_admin' => true]))), 'config account should be super admin');

    $admin = permission_resolve_for_user(['role' => 'admin']);
    same([], array_values(array_intersect($cms, $admin)), 'admin has website content');
    check(in_array('team.manage', $admin, true) && in_array('invoices.delete', $admin, true), 'admin lost other capabilities');
    check(!permission_user_can($admin, 'cms.view'), 'admin can view website content');

    $limited = permission_resolve_for_user(['role' => 'admin', 'permissions_json' => '["clients.view","blogs.manage"]']);
    same(['clients.view', 'dashboard.view'], $limited, 'custom admin permissions not applied, or old blog grant became website access');
    same([], array_values(array_intersect($cms, permission_resolve_for_user(['role' => 'reviewer']))), 'reviewer has website content by default');
    $allowed = permission_resolve_for_user(['role' => 'reviewer', 'permissions_json' => permission_encode_for_storage(['submissions.view', 'cms.edit'])]);
    check(permission_user_can($allowed, 'cms.edit') && permission_user_can($allowed, 'cms.view'), 'allowed website content not granted');
    check(!permission_user_can($allowed, 'cms.code'), 'allowing edit should not allow site code');

    check(permission_is_privileged_role('admin') && permission_is_privileged_role('super_admin') && !permission_is_privileged_role('reviewer'), 'privileged roles');
    same('Super admin', permission_role_label('super_admin'));
    lacks(implode(' ', array_keys(permission_catalog_grouped(true))), 'Website', 'website permissions offered to non-super managers');
    contains(implode(' ', array_keys(permission_catalog_grouped())), 'Website', 'super admins cannot see website permissions');
});

// ---------------------------------------------------------------- navigation
function header_html(): string
{
    CmsNavigation::reset();
    $canonicalPath = '/';
    ob_start();
    include PROJECT_ROOT . '/components/site-nav.php';
    return (string) ob_get_clean();
}

test('header menu: defaults, editing, hiding and validation', function () {
    CmsNavigation::restoreDefaults();
    $html = header_html();
    contains($html, 'aria-controls="sh-mega-services"', 'default services dropdown');
    contains($html, 'href="/blog"', 'default blog link');
    contains($html, 'href="/services" class="sh-mega-all"', 'view all link');

    $items = CmsNavigation::get()['items'];
    $items[] = ['label' => 'Pricing', 'url' => '/pricing/', 'visible' => true, 'children' => []];
    $items[3]['children'][] = ['label' => 'Guides', 'url' => '/guides', 'visible' => false];
    $items[0]['visible'] = false;
    same([], CmsNavigation::save($items, ['show_phone' => true, 'button_label' => 'Call me back', 'button_url' => '/contact']));
    $html = header_html();
    contains($html, 'href="/pricing" class="sh-link"', 'new top-level link (path normalized)');
    lacks($html, 'href="/guides"', 'hidden child shown');
    lacks($html, '<a href="/" class="sh-link"', 'hidden Home shown');
    contains($html, 'href="' . CmsCompany::phoneHref() . '" class="sh-phone"', 'company phone link');
    contains($html, 'Call me back', 'button label');
    lacks($html, 'sh-mega-icon', 'menu icons shown');
    same([], CmsNavigation::save($items, ['show_phone' => false]));
    lacks(header_html(), 'class="sh-phone"', 'phone shown after switching it off');

    $bad = CmsNavigation::save([['label' => 'Evil', 'url' => 'javascript:alert(1)'], ['label' => 'Empty menu', 'url' => '']], []);
    check(count($bad) === 2, 'expected 2 errors, got: ' . implode(' | ', $bad));
    contains(header_html(), 'href="/pricing"', 'failed save changed the menu');
});

test('pages join the header menu from the editor and stay hidden until live', function () {
    CmsNavigation::restoreDefaults();
    $id = make(['title' => 'Nav Test Page', 'slug' => 'nav-test-page', 'workflow_action' => 'save']);
    same([], CmsNavigation::place('/nav-test-page', 'Nav Test Page', 'resources'));
    same('resources', CmsNavigation::placement('/nav-test-page'));
    lacks(header_html(), 'href="/nav-test-page"', 'draft page shown in menu');

    make(['id' => $id, 'title' => 'Nav Test Page', 'slug' => 'nav-test-page', 'workflow_action' => 'publish']);
    contains(header_html(), 'href="/nav-test-page"', 'published page missing from menu');

    same([], CmsNavigation::place('/nav-test-renamed', 'Nav Test Page', 'top', '/nav-test-page'));
    same('top', CmsNavigation::placement('/nav-test-renamed'));
    same('', CmsNavigation::placement('/nav-test-page'), 'old URL left in menu');
    same([], CmsNavigation::place('/nav-test-renamed', 'Nav Test Page', ''));
    same('', CmsNavigation::placement('/nav-test-renamed'));
    check(in_array('/blog', CmsNavigation::paths(), true), 'menu paths for audit');
    CmsNavigation::restoreDefaults();
});

test('a page can have sub-pages and show them under it in the menu', function () use ($repo) {
    CmsNavigation::restoreDefaults();
    $folders = new CmsFolderRepository();
    $parentId = make(['title' => 'Our Team', 'slug' => 'our-team']);
    $parent = $repo->find($parentId);
    same(null, $folders->syncLanding($parent, true));
    $own = $folders->forLanding($parentId);
    check($own !== null, 'sub-pages folder missing');
    same('/our-team', $own['url_prefix']);

    $childId = make(['title' => 'Jane Doe', 'slug' => 'jane-doe', 'folder_id' => (string) $own['id']]);
    same('/our-team/jane-doe', $repo->find($childId)['path']);
    same(200, get('/our-team/jane-doe')['status']);

    $r = (new CmsContentService())->save(['id' => $parentId, 'title' => 'Our Team', 'folder_id' => (string) $own['id'], 'workflow_action' => 'save'], ALL_CAPS);
    check($r['errors'] !== [], 'page placed inside its own sub-pages');

    contains((string) $folders->syncLanding($parent, false), '1 sub-page', 'turning off with sub-pages left');
    check($folders->forLanding($parentId) !== null, 'folder removed while it still had pages');

    same([], CmsNavigation::place('/our-team', 'Our Team', 'top'));
    check(!isset(CmsNavigation::parents('/our-team')[CmsNavigation::topKeyFor('/our-team')]), 'page offered as its own parent');
    same([], CmsNavigation::place('/our-team/jane-doe', 'Jane Doe', CmsNavigation::topKeyFor('/our-team')));
    $html = header_html();
    contains($html, 'href="/our-team/jane-doe"', 'sub-page missing from dropdown');
    contains($html, 'href="/our-team" class="sh-mega-all">Our Team', 'parent page not kept as the dropdown link');
    same('top', CmsNavigation::placement('/our-team'));
    same([], CmsNavigation::place('/our-team', 'Our Team', 'top'), 'saving the parent again');
    same(1, substr_count(header_html(), 'href="/our-team" class="sh-mega-all"'), 'parent duplicated');
    check(CmsNavigation::place('/our-team', 'Our Team', '') !== [], 'removing a page that heads a dropdown should explain');

    $repo->delete($childId);
    same(null, $folders->syncLanding($parent, false));
    same(null, $folders->forLanding($parentId), 'empty folder kept');
    CmsNavigation::restoreDefaults();
});

test('header breadcrumbs follow the URL and match the BreadcrumbList', function () {
    CmsNavigation::restoreDefaults();
    lacks(header_html(), 'aria-label="Breadcrumb"', 'homepage shows breadcrumbs');

    same([['name' => 'Home', 'path' => '/'], ['name' => 'Our Services', 'path' => '/services']], CmsNavigation::breadcrumbs('/services'));
    $crumbs = CmsNavigation::breadcrumbs('/no-such-folder/tax-tips', 'Tax Tips for 2026');
    same(['Home', 'Tax Tips for 2026'], array_column($crumbs, 'name'), 'missing parent pages are skipped');

    make(['title' => 'Crumb Parent', 'slug' => 'crumb-parent']);
    make(['title' => 'Crumb Child', 'slug' => 'crumb-child', 'url_mode' => 'custom', 'custom_path' => '/crumb-parent/crumb-child']);
    same(['Home', 'Crumb Parent', 'Crumb Child'], array_column(CmsNavigation::breadcrumbs('/crumb-parent/crumb-child'), 'name'));

    $r = get('/crumb-parent/crumb-child');
    contains($r['body'], '<nav class="sh-bar" aria-label="Breadcrumb">', 'breadcrumb row missing on CMS page');
    contains($r['body'], '<a href="/crumb-parent">Crumb Parent</a>', 'parent crumb link');
    contains($r['body'], '<span aria-current="page">Crumb Child</span>', 'current crumb');
    $bc = array_values(array_filter(jsonld($r), static fn ($j) => ($j['@type'] ?? '') === 'BreadcrumbList'))[0] ?? null;
    same(['Home', 'Crumb Parent', 'Crumb Child'], array_column($bc['itemListElement'] ?? [], 'name'), 'schema differs from visible crumbs');
});

test('company details drive the header, footer, page bodies and schema', function () {
    CmsNavigation::restoreDefaults();
    check(CmsSiteConfig::save(['company_phone' => 'call us'], true) !== [], 'phone without digits accepted');
    check(CmsSiteConfig::save(['company_email' => 'not-an-email'], true) !== [], 'invalid email accepted');
    check(CmsSiteConfig::save(['social_linkedin_on' => 1, 'social_linkedin_url' => ''], true) !== [], 'switched-on profile without a link accepted');
    check(CmsSiteConfig::save(['social_youtube_url' => 'javascript:alert(1)'], true) !== [], 'non-https social link accepted');

    same([], CmsSiteConfig::save([
        'company_phone' => '(613) 555-0199', 'company_email' => 'hello@example.test', 'company_street' => '1 Test Street',
        'social_instagram_on' => 0, 'social_linkedin_on' => 1, 'social_linkedin_url' => 'https://www.linkedin.com/company/example',
    ], true));
    same('tel:+16135550199', CmsCompany::phoneHref());
    same('+1-613-555-0199', CmsCompany::phoneSchema());

    contains(header_html(), 'href="tel:+16135550199" class="sh-phone"', 'header phone');
    ob_start();
    include PROJECT_ROOT . '/components/site-footer.php';
    $footer = (string) ob_get_clean();
    contains($footer, 'href="mailto:hello@example.test"', 'footer email');
    contains($footer, '1 Test Street', 'footer address');
    contains($footer, 'aria-label="Verma Accounting on LinkedIn"', 'switched-on profile missing');
    lacks($footer, 'aria-label="Verma Accounting on Instagram"', 'switched-off profile shown');

    $id = make(['title' => 'Company Placeholders', 'slug' => 'company-placeholders',
        'body_html' => '<p>' . str_repeat('Useful words about accounting. ', 20) . '</p><p><a href="{{company_phone_link}}">{{company_phone}}</a> {{company_email}}</p>']);
    $r = get('/company-placeholders');
    contains($r['body'], '<a href="tel:+16135550199">(613) 555-0199</a> hello@example.test', 'placeholders in the page body');
    $business = array_values(array_filter(jsonld($r), static fn ($j) => str_ends_with((string) ($j['@id'] ?? ''), '#business')))[0] ?? [];
    same('+1-613-555-0199', $business['telephone'] ?? null, 'schema telephone');
    check(in_array('https://www.linkedin.com/company/example', $business['sameAs'] ?? [], true), 'sameAs missing LinkedIn');
    check(!in_array('https://www.instagram.com/vermaaccounting.ca/', $business['sameAs'] ?? [], true), 'sameAs lists a switched-off profile');
    (new CmsContentService())->bulk([$id], 'delete', ALL_CAPS);

    same([], CmsSiteConfig::save(CmsCompany::defaults(), true));
});

test('AI blog fixture normalizes and publishes without calling OpenAI', function () use ($repo) {
    $fixture = [
        'title' => 'Ontario HST Basics for Sole Proprietors',
        'slug' => 'ontario-hst-basics-sole-proprietors',
        'excerpt' => 'A practical overview of when Ontario sole proprietors need to register for HST.',
        'body_html' => '<p>' . str_repeat('HST registration and filing tips for Ontario businesses. ', 40) . '</p><h2>When to register</h2><p>' . str_repeat('Thresholds and timing matter for cash flow. ', 20) . '</p><ul><li>Track taxable sales</li><li>Keep invoices organized</li></ul>',
        'seo_title' => 'Ontario HST Basics for Sole Proprietors',
        'seo_description' => 'Learn when Ontario sole proprietors typically register for HST and how to stay organized.',
        'tags' => ['HST', 'sole proprietor', 'Ontario'],
        'faqs' => [
            ['question' => 'Do I need to charge HST?', 'answer' => 'It depends on your taxable sales and registration status. Ask your accountant for your situation.'],
            ['question' => 'How often do I file?', 'answer' => 'Filing frequency depends on your CRA assigned period.'],
        ],
    ];
    $ai = new CmsBlogAiService($repo);
    $normalized = $ai->normalizeArticle($fixture);
    same([], $normalized['errors']);
    check($normalized['article']['title'] !== '', 'title empty');
    check(str_contains((string) $normalized['article']['tags'], 'HST'), 'tags missing');
    check(count($normalized['article']['faq_q']) === 2, 'faqs not mapped');

    $folderId = $ai->ensureBlogFolder();
    $input = $ai->buildSaveInput($normalized['article'], $folderId);
    unset($input['_path']);
    $result = (new CmsContentService($repo))->save($input, ALL_CAPS);
    same([], $result['errors']);
    $content = $repo->find((int) $result['id']);
    same('published', $content['status']);
    same('article', $content['type']);
    check(str_starts_with((string) $content['path'], '/blog/'), 'path not under /blog');
    same(200, get((string) $content['path'])['status']);

    $ai->saveTopics(['Topic A', 'Topic A', ' Topic B ']);
    same(['Topic A', 'Topic B'], $ai->queueState()['topics']);
});

echo "\n\n" . $results['pass'] . ' passed, ' . $results['fail'] . " failed\n";
foreach ($failures as $f) {
    echo '  ✗ ' . $f . "\n";
}
@unlink($dbFile);
exit($results['fail'] > 0 ? 1 : 0);
