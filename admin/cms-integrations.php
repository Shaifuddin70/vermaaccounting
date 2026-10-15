<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$canRefresh = Auth::can('cms.seo');
$status = CmsIntegrations::status();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    if (!$canRefresh) {
        cms_admin_back('/admin/cms-integrations', null, 'Refreshing search data needs SEO permission.');
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'gsc') {
            $r = CmsIntegrations::searchConsole();
            cms_admin_back('/admin/cms-integrations', 'Search Console data refreshed (' . count($r['data']) . ' pages).');
        }
        if ($action === 'ga4') {
            $r = CmsIntegrations::analytics();
            cms_admin_back('/admin/cms-integrations', 'Analytics data refreshed (' . count($r['data']) . ' pages).');
        }
        if ($action === 'pagespeed') {
            $path = CmsPath::normalize((string) ($_POST['path'] ?? '/'));
            $base = CmsSiteConfig::baseUrl();
            if (!preg_match('#^https://#', $base)) {
                cms_admin_back('/admin/cms-integrations', null, 'Set the public https:// base URL in Site configuration first; PageSpeed tests the live site.');
            }
            CmsIntegrations::pageSpeed($base . ($path === '/' ? '/' : $path), (string) ($_POST['strategy'] ?? 'mobile'));
            cms_admin_back('/admin/cms-integrations#pagespeed', 'PageSpeed test finished for ' . $path . '.');
        }
    } catch (Throwable $e) {
        cms_admin_back('/admin/cms-integrations', null, app_safe_error_message($e, 'The request failed.'));
    }
    cms_admin_back('/admin/cms-integrations', null, 'Unknown action.');
}

$cached = CmsIntegrations::cached();
$pathToContent = [];
$published = (new CmsContentRepository())->published(null, 2000);
$homeId = (int) CmsSiteConfig::value('home_content_id', 0);
foreach ($published as $c) {
    $pathToContent[(int) $c['id'] === $homeId ? '/' : (string) $c['path']] = $c;
}
$gsc = $cached['gsc']['data'] ?? [];
$ga4 = $cached['ga4']['data'] ?? [];
$opportunities = array_values(array_filter($gsc, static fn ($r) => $r['impressions'] >= 100 && $r['ctr'] < 2.0 && $r['position'] <= 20));
usort($opportunities, static fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
$speed = [];
foreach ($cached as $key => $entry) {
    if (str_starts_with($key, 'pagespeed:')) {
        $speed[] = $entry;
    }
}
usort($speed, static fn ($a, $b) => strcmp($b['fetched_at'], $a['fetched_at']));
$csrf = Auth::csrfToken();
$pageLink = static function (string $path) use ($pathToContent): string {
    $c = $pathToContent[$path] ?? null;
    return $c ? '<a href="/admin/cms-edit?id=' . (int) $c['id'] . '"><code>' . e($path) . '</code></a>' : '<code>' . e($path) . '</code>';
};

$pageTitle = 'Search data';
$activeNav = 'cms-integrations';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Search data</h1>
</div>
<p class="admin-table-sub">Optional connections to Google. The website works the same without them. Numbers below come from Google and show when they were fetched.</p>

<dl class="cms-stat-list">
  <?php foreach ($status as $key => $s): ?>
    <div>
      <dt><?= e($s['label']) ?></dt>
      <dd><?= $s['configured'] ? '<span class="admin-badge admin-badge-success">Connected</span>' : '<span class="admin-badge admin-badge-muted">Not set up</span>' ?></dd>
    </div>
  <?php endforeach; ?>
</dl>
<?php if (in_array(false, array_column($status, 'configured'), true)): ?>
  <details class="admin-card cms-mt">
    <summary>How to connect</summary>
    <p>Credentials are kept in <code>config.local.php</code> on the server, never in the database:</p>
    <pre class="cms-code-preview">'cms' => [
    'integrations' => [
        'pagespeed_api_key' => '…',
        'google_service_account_file' => '/path/outside/the/website/service-account.json',
        'gsc_site' => 'sc-domain:example.com',
        'ga4_property_id' => '123456789',
    ],
],</pre>
    <ul><?php foreach ($status as $s): if ($s['configured']) { continue; } ?><li><strong><?= e($s['label']) ?>:</strong> <?= e($s['help']) ?></li><?php endforeach; ?></ul>
  </details>
<?php endif; ?>

<div class="admin-card">
  <div class="cms-card-head">
    <h2 class="admin-card-title">Google Search Console</h2>
    <?php if ($canRefresh && $status['gsc']['configured']): ?>
      <form method="post" action="/admin/cms-integrations" class="cms-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button type="submit" name="action" value="gsc" class="admin-btn admin-btn-sm admin-btn-secondary" data-busy="Loading…">Refresh (last 28 days)</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$gsc): ?>
    <p><?= $status['gsc']['configured'] ? 'No data fetched yet.' : 'Not connected.' ?></p>
  <?php else: ?>
    <p class="admin-table-sub">Source: <?= e((string) $cached['gsc']['source']) ?> · fetched <?= e(cms_admin_date((string) $cached['gsc']['fetched_at'])) ?></p>
    <?php if ($opportunities): ?>
      <h3>Titles and descriptions worth improving</h3>
      <p class="admin-table-sub">Shown often on the first two pages of results but rarely clicked (CTR under 2%). A clearer title or description may earn more clicks.</p>
      <table class="admin-table cms-mb">
        <thead><tr><th>Page</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
        <tbody><?php foreach (array_slice($opportunities, 0, 10) as $r): ?><tr><td><?= $pageLink($r['path']) ?></td><td><?= number_format($r['impressions']) ?></td><td><?= $r['ctr'] ?>%</td><td><?= $r['position'] ?></td></tr><?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
    <details><summary>All pages (<?= count($gsc) ?>)</summary>
      <div class="cms-table-scroll"><table class="admin-table">
        <thead><tr><th>Page</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
        <tbody><?php foreach ($gsc as $r): ?><tr><td><?= $pageLink($r['path']) ?></td><td><?= number_format($r['clicks']) ?></td><td><?= number_format($r['impressions']) ?></td><td><?= $r['ctr'] ?>%</td><td><?= $r['position'] ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </details>
  <?php endif; ?>
</div>

<div class="admin-card">
  <div class="cms-card-head">
    <h2 class="admin-card-title">Google Analytics 4</h2>
    <?php if ($canRefresh && $status['ga4']['configured']): ?>
      <form method="post" action="/admin/cms-integrations" class="cms-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button type="submit" name="action" value="ga4" class="admin-btn admin-btn-sm admin-btn-secondary" data-busy="Loading…">Refresh (last 28 days)</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$ga4): ?>
    <p><?= $status['ga4']['configured'] ? 'No data fetched yet.' : 'Not connected. Page tracking itself is set with the GA4 measurement ID in Site configuration; this connection only reads reports.' ?></p>
  <?php else: ?>
    <p class="admin-table-sub">Source: <?= e((string) $cached['ga4']['source']) ?> · fetched <?= e(cms_admin_date((string) $cached['ga4']['fetched_at'])) ?></p>
    <div class="cms-table-scroll"><table class="admin-table">
      <thead><tr><th>Page</th><th>Views</th><th>Sessions</th><th>Engagement</th></tr></thead>
      <tbody><?php foreach (array_slice($ga4, 0, 50) as $r): ?><tr><td><?= $pageLink($r['path']) ?></td><td><?= number_format($r['views']) ?></td><td><?= number_format($r['sessions']) ?></td><td><?= $r['engagement'] ?>%</td></tr><?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="admin-card" id="pagespeed">
  <h2 class="admin-card-title">PageSpeed Insights</h2>
  <?php if ($canRefresh && $status['pagespeed']['configured']): ?>
    <form method="post" action="/admin/cms-integrations" class="cms-filters">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="pagespeed">
      <div class="admin-field">
        <label for="ps-path">Page</label>
        <select id="ps-path" name="path">
          <option value="/">Homepage</option>
          <?php foreach ($pathToContent as $path => $c): if ($path === '/') { continue; } ?><option value="<?= e($path) ?>"><?= e($path) ?></option><?php endforeach; ?>
          <?php foreach (CmsPath::legacyPages() as $path): if (isset($pathToContent[$path]) || $path === '/') { continue; } ?><option value="<?= e($path) ?>"><?= e($path) ?> (hand-written)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="admin-field">
        <label for="ps-strategy">Device</label>
        <select id="ps-strategy" name="strategy"><option value="mobile">Mobile</option><option value="desktop">Desktop</option></select>
      </div>
      <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary" data-busy="Testing… (up to a minute)">Run test</button></div>
    </form>
  <?php endif; ?>
  <?php if (!$speed): ?>
    <p><?= $status['pagespeed']['configured'] ? 'No tests run yet.' : 'Not connected.' ?></p>
  <?php else: ?>
    <div class="cms-table-scroll"><table class="admin-table">
      <thead><tr><th>Page</th><th>Device</th><th>Score</th><th>Lab: LCP / CLS / TBT</th><th>Real users: LCP / INP / CLS</th><th>Tested</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($speed, 0, 30) as $s): $d = $s['data']; ?>
        <tr>
          <td><code><?= e((string) parse_url((string) $d['url'], PHP_URL_PATH)) ?></code></td>
          <td><?= e(ucfirst((string) $d['strategy'])) ?></td>
          <td><?php if ($d['score'] !== null): ?><span class="cms-score <?= $d['score'] >= 90 ? 'cms-score--good' : ($d['score'] >= 50 ? 'cms-score--ok' : 'cms-score--bad') ?>"><?= (int) $d['score'] ?></span><?php else: ?>—<?php endif; ?></td>
          <td><?= e(implode(' / ', array_map(static fn ($v) => $v ?: '—', [$d['lab']['LCP'], $d['lab']['CLS'], $d['lab']['TBT']]))) ?></td>
          <td><?= e(implode(' / ', array_map(static fn ($v) => $v ? ucfirst(strtolower(str_replace('_', ' ', $v))) : 'No data', [$d['field']['LCP'], $d['field']['INP'], $d['field']['CLS']]))) ?></td>
          <td><?= e(cms_admin_date((string) $s['fetched_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
