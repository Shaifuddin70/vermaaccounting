<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.seo');

$sessionKey = 'cms_technical_old';
$fields = ['robots_txt', 'sitemap_pages', 'sitemap_articles', 'sitemap_cities', 'sitemap_legacy', 'sitemap_exclude'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $input = array_intersect_key($_POST, array_flip($fields));
    $current = CmsSiteConfig::get();
    $robots = str_replace("\r\n", "\n", (string) ($input['robots_txt'] ?? $current['robots_txt']));
    $warnings = $robots !== $current['robots_txt'] ? CmsSitemap::robotsWarnings($robots) : [];
    if ($warnings && empty($_POST['confirmed'])) {
        $_SESSION[$sessionKey] = $input + ['warnings' => $warnings];
        cms_admin_back('/admin/cms-technical#robots', null, 'Please review the robots.txt warnings, then save again to confirm.');
    }
    $errors = CmsSiteConfig::save($input, Auth::can('cms.code'), 'Sitemap & robots.txt');
    if ($errors) {
        $_SESSION[$sessionKey] = $input;
        cms_admin_back('/admin/cms-technical', null, implode(' ', $errors));
    }
    ActivityLog::record('cms.technical_saved', 'cms_site_config', null, ['robots_changed' => $robots !== $current['robots_txt']]);
    cms_admin_back('/admin/cms-technical', 'Saved. The sitemap and robots.txt update immediately.');
}

$config = CmsSiteConfig::get();
$old = $_SESSION[$sessionKey] ?? null;
unset($_SESSION[$sessionKey]);
$pendingWarnings = [];
if (is_array($old)) {
    $pendingWarnings = (array) ($old['warnings'] ?? []);
    unset($old['warnings']);
    $config = array_merge($config, $old);
}
$base = CmsSiteConfig::baseUrl();
$entries = CmsSitemap::entries();
$validation = ['index' => CmsSitemap::validate(CmsSitemap::indexXml($entries))];
foreach (array_keys(CmsSitemap::PARTS) as $part) {
    if ($entries[$part] !== []) {
        $validation[$part] = CmsSitemap::validate((string) CmsSitemap::partXml($part, $entries));
    }
}
$inSitemap = [];
foreach ($entries as $urls) {
    foreach ($urls as $u) {
        $inSitemap[$u['path']] = true;
    }
}
$homeId = (int) $config['home_content_id'];
$left = [];
foreach ((new CmsContentRepository())->published(null, 5000) as $c) {
    $path = (int) $c['id'] === $homeId ? '/' : (string) $c['path'];
    if (!isset($inSitemap[$path])) {
        $left[] = $c + ['shown_path' => $path];
    }
}
$robotsDraft = (string) $config['robots_txt'];
$robotsWarnings = $pendingWarnings ?: CmsSitemap::robotsWarnings($robotsDraft);
$testPath = trim((string) ($_GET['robots_test'] ?? ''));
$testBlocked = null;
if ($testPath !== '') {
    $parsed = CmsPath::parseTarget($testPath, $base);
    $testPath = $parsed['ok'] && $parsed['internal'] ? (strtok($parsed['value'], '?') ?: '/') : '';
    $testBlocked = $testPath !== '' ? CmsSeo::blockedByRobotsTxt($testPath, $robotsDraft) : null;
}
$csrf = Auth::csrfToken();
$total = array_sum(array_map('count', $entries));

$pageTitle = 'Sitemap & robots.txt';
$activeNav = 'cms-technical';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Sitemap &amp; robots.txt</h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/sitemap.xml" target="_blank" rel="noopener">Open sitemap.xml</a>
    <a class="admin-btn admin-btn-secondary" href="/robots.txt" target="_blank" rel="noopener">Open robots.txt</a>
  </div>
</div>

<form method="post" action="/admin/cms-technical">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <div class="admin-card">
    <h2 class="admin-card-title">XML sitemap (<?= $total ?> URLs)</h2>
    <p>Only published pages that are indexable and canonical to themselves are listed, with the date they last changed. Submit <code><?= e($base) ?>/sitemap.xml</code> in Search Console once.</p>
    <table class="admin-table">
      <thead><tr><th>Section</th><th>Include</th><th>URLs</th><th>File</th><th>Check</th></tr></thead>
      <tbody>
      <?php foreach (CmsSitemap::PARTS as $part => $label): ?>
        <tr>
          <td><?= e($label) ?></td>
          <td>
            <input type="hidden" name="sitemap_<?= $part ?>" value="0">
            <label class="admin-checkbox-label"><input type="checkbox" name="sitemap_<?= $part ?>" value="1" <?= (int) $config['sitemap_' . $part] === 1 ? 'checked' : '' ?>> <span>Include</span></label>
          </td>
          <td><?= count($entries[$part]) ?></td>
          <td><?php if ($entries[$part]): ?><a href="/sitemap-<?= $part ?>.xml" target="_blank" rel="noopener">sitemap-<?= $part ?>.xml</a><?php else: ?>—<?php endif; ?></td>
          <td><?php if (!isset($validation[$part])): ?>—<?php elseif ($validation[$part] === []): ?><span class="admin-badge admin-badge-success">Valid</span><?php else: ?><span class="admin-badge admin-badge-info"><?= e(implode('; ', array_slice($validation[$part], 0, 3))) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
        <tr><td>Index (sitemap.xml)</td><td></td><td><?php $files = count(array_filter($entries)); ?><?= $files ?> file<?= $files === 1 ? '' : 's' ?></td><td><a href="/sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a></td><td><?= $validation['index'] === [] ? '<span class="admin-badge admin-badge-success">Valid</span>' : '<span class="admin-badge admin-badge-info">' . e(implode('; ', $validation['index'])) . '</span>' ?></td></tr>
      </tbody>
    </table>
    <p class="admin-field-hint">“Hand-written pages” are the original PHP pages; each drops out of this list as soon as a CMS page or redirect takes over its URL.</p>
    <div class="admin-field cms-mt">
      <label for="t-exclude">Always leave out</label>
      <textarea id="t-exclude" name="sitemap_exclude" rows="3" placeholder="/thank-you&#10;/landing/*"><?= e((string) $config['sitemap_exclude']) ?></textarea>
      <p class="admin-field-hint">One path per line. End with <code>*</code> to leave out a whole section.</p>
    </div>
    <?php if ($left): ?>
      <details class="cms-mt"><summary>Published pages not in the sitemap (<?= count($left) ?>)</summary>
        <p class="admin-field-hint">Usually because they are noindex, canonicalised to another URL, excluded above, or have sitemap inclusion turned off in their SEO tab.</p>
        <ul><?php foreach ($left as $c): ?><li><a href="/admin/cms-edit?id=<?= (int) $c['id'] ?>#tab-seo"><code><?= e($c['shown_path']) ?></code></a> — <?= e((string) $c['title']) ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
    <?php foreach ($entries as $part => $urls): if (!$urls) { continue; } ?>
      <details class="cms-mt"><summary><?= e(CmsSitemap::PARTS[$part]) ?> URLs (<?= count($urls) ?>)</summary>
        <div class="cms-table-scroll"><table class="admin-table"><thead><tr><th>URL</th><th>Last changed</th></tr></thead><tbody>
        <?php foreach (array_slice($urls, 0, 500) as $u): ?><tr><td><code><?= e($u['loc']) ?></code></td><td><?= e($u['lastmod'] !== '' ? substr($u['lastmod'], 0, 10) : '—') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </details>
    <?php endforeach; ?>
  </div>

  <div class="admin-card" id="robots">
    <h2 class="admin-card-title">robots.txt</h2>
    <?php foreach ($robotsWarnings as $w): ?><div class="admin-alert admin-alert-info"><?= e($w) ?></div><?php endforeach; ?>
    <?php if ($pendingWarnings): ?><input type="hidden" name="confirmed" value="1"><?php endif; ?>
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="t-robots">Rules</label>
        <textarea id="t-robots" name="robots_txt" rows="14" class="cms-code" spellcheck="false" data-robots-source><?= e($robotsDraft) ?></textarea>
        <p class="admin-field-hint"><code>{{base_url}}</code> is replaced with the public base URL. robots.txt controls crawling, not indexing — use noindex on a page to keep it out of search results.</p>
      </div>
      <div class="admin-field">
        <label>Served as</label>
        <pre class="cms-code-preview" data-robots-preview data-base="<?= e($base) ?>"><?= e(str_replace('{{base_url}}', $base, $robotsDraft)) ?></pre>
      </div>
    </div>
  </div>

  <div class="cms-form-actions">
    <button type="submit" class="admin-btn"><?= $pendingWarnings ? 'Save anyway' : 'Save' ?></button>
  </div>
</form>

<div class="admin-card">
  <h2 class="admin-card-title">Test a URL against the saved robots.txt</h2>
  <form method="get" action="/admin/cms-technical#robots-test" class="cms-filters" id="robots-test">
    <div class="admin-field"><label for="t-test">Path</label><input type="text" id="t-test" name="robots_test" value="<?= e($testPath) ?>" placeholder="/services"></div>
    <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Test</button></div>
  </form>
  <?php if ($testBlocked !== null): ?>
    <p><?= $testBlocked ? '<strong>Blocked</strong> — crawlers that obey robots.txt will not fetch <code>' . e($testPath) . '</code>.' : '<strong>Allowed</strong> — <code>' . e($testPath) . '</code> can be crawled.' ?></p>
  <?php elseif (isset($_GET['robots_test'])): ?>
    <p class="admin-alert admin-alert-error">Enter a path on this site.</p>
  <?php endif; ?>
</div>
<script>
(function () {
  var src = document.querySelector('[data-robots-source]');
  var out = document.querySelector('[data-robots-preview]');
  if (!src || !out) return;
  src.addEventListener('input', function () {
    out.textContent = src.value.split('{{base_url}}').join(out.getAttribute('data-base'));
  });
})();
</script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
