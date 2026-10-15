<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$repo = new CmsContentRepository();
$caps = cms_admin_caps();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'flush_cache') {
        if (!$caps['edit'] && !$caps['seo']) {
            cms_admin_back('/admin/cms', null, 'You do not have permission to clear the cache.');
        }
        $n = CmsCache::flush();
        ActivityLog::record('cms.cache_flushed', 'cms', null, ['entries' => $n]);
        cms_admin_back('/admin/cms', 'Page cache cleared (' . $n . ' entries).');
    }
    if ($action === 'import_blogs') {
        if (!$caps['publish'] || !$caps['redirects']) {
            cms_admin_back('/admin/cms', null, 'Importing the old blog needs publishing and redirect permissions.');
        }
        @set_time_limit(600);
        try {
            $result = (new CmsMigration())->importBlogs(!empty($_POST['download_images']));
        } catch (Throwable $e) {
            error_log('CMS blog import failed: ' . $e->getMessage());
            cms_admin_back('/admin/cms', null, app_safe_error_message($e, 'The blog import failed.'));
        }
        ActivityLog::record('cms.blog_imported', 'cms', null, ['imported' => $result['imported'], 'skipped' => $result['skipped']]);
        $msg = sprintf('Blog import finished: %d imported, %d already present, %d images downloaded.', $result['imported'], $result['skipped'], $result['images']);
        cms_admin_back('/admin/cms', $msg, $result['errors'] ? implode(' ', array_slice($result['errors'], 0, 5)) : null);
    }
    if ($action === 'process_schedule') {
        $n = cms_process_schedule();
        cms_admin_back('/admin/cms', 'Schedule processed: ' . (is_array($n) ? array_sum($n) : (int) $n) . ' change(s).');
    }
    cms_admin_back('/admin/cms', null, 'Unknown action.');
}

$types = ['page' => 'Pages', 'article' => 'Blog articles', 'city' => 'City pages'];
$counts = [];
foreach (array_keys($types) as $type) {
    $counts[$type] = $repo->statusCounts($type);
}
$pendingBlogs = CmsMigration::pendingBlogCount();
$recent = ActivityLog::recent(15, null, 'cms.');
$homeId = (int) CmsSiteConfig::value('home_content_id', 0);
$home = $homeId > 0 ? $repo->find($homeId) : null;
$cache = CmsCache::stats();
$review = $repo->search(['status' => 'review'], 10);
$scheduled = $repo->search(['status' => 'scheduled', 'sort' => 'publish_at', 'dir' => 'asc'], 10);
$csrf = Auth::csrfToken();
$baseUrl = CmsSiteConfig::baseUrl();

$pageTitle = 'Website';
$activeNav = 'cms';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Website</h1>
  <?php if ($caps['edit']): ?>
    <div class="admin-header-actions">
      <a class="admin-btn" href="/admin/cms-edit?type=page">New page</a>
      <a class="admin-btn admin-btn-secondary" href="/admin/cms-edit?type=article">New article</a>
      <a class="admin-btn admin-btn-secondary" href="/admin/cms-edit?type=city">New city page</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($pendingBlogs > 0): ?>
  <div class="admin-card cms-callout">
    <h2 class="admin-card-title">Import the old blog</h2>
    <p><?= $pendingBlogs ?> post(s) from the old blog system are not in the CMS yet. Importing creates them as articles at <code>/blog/{slug}</code> (published posts stay published), creates the Blog folder and listing page, and copies categories, tags and featured images. Running it again skips posts that were already imported.</p>
    <?php if ($caps['publish'] && $caps['redirects']): ?>
      <form method="post" action="/admin/cms">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="import_blogs">
        <label class="admin-checkbox-label"><input type="checkbox" name="download_images" value="1" checked> Download featured images into the media library</label>
        <button type="submit" class="admin-btn cms-mt" data-confirm="Import <?= $pendingBlogs ?> blog post(s) into the CMS now?" data-busy="Importing…">Import blog posts</button>
      </form>
    <?php else: ?>
      <p class="admin-field-hint">Ask an administrator with publishing and redirect permissions to run the import.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="cms-stat-grid">
  <?php foreach ($types as $type => $label): $c = $counts[$type]; ?>
    <a class="admin-card cms-stat" href="/admin/cms-content?type=<?= $type ?>">
      <span class="cms-stat-label"><?= e($label) ?></span>
      <span class="cms-stat-value"><?= (int) $c['published'] ?></span>
      <span class="cms-stat-sub">published · <?= (int) $c['draft'] ?> draft · <?= (int) ($c['review'] ?? 0) ?> in review · <?= (int) $c['scheduled'] ?> scheduled</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-fields-2col">
  <div class="admin-card">
    <h2 class="admin-card-title">Waiting for review</h2>
    <?php if ($review === []): ?>
      <p class="admin-field-hint">Nothing is waiting for review.</p>
    <?php else: ?>
      <ul class="cms-link-list">
        <?php foreach ($review as $r): ?>
          <li><a href="/admin/cms-edit?id=<?= (int) $r['id'] ?>"><?= e(CmsCityDefaults::label($r)) ?></a> <code><?= e((string) $r['path']) ?></code></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <h2 class="admin-card-title cms-mt">Scheduled</h2>
    <?php if ($scheduled === []): ?>
      <p class="admin-field-hint">Nothing is scheduled.</p>
    <?php else: ?>
      <ul class="cms-link-list">
        <?php foreach ($scheduled as $r): ?>
          <li><a href="/admin/cms-edit?id=<?= (int) $r['id'] ?>"><?= e(CmsCityDefaults::label($r)) ?></a> — <?= e(cms_admin_date($r['publish_at'])) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title">Site</h2>
    <dl class="cms-dl">
      <dt>Site address</dt><dd><a href="<?= e($baseUrl) ?>/" target="_blank" rel="noopener"><?= e($baseUrl) ?></a></dd>
      <dt>Homepage</dt>
      <dd>
        <?php if ($home): ?>
          <a href="/admin/cms-edit?id=<?= $homeId ?>"><?= e((string) $home['title']) ?></a> (CMS)
        <?php else: ?>
          The hand-written <code>index.php</code>. Open a published page and choose “Use as homepage” on its Publishing tab to replace it.
        <?php endif; ?>
      </dd>
      <dt>Page cache</dt><dd><?= CmsCache::enabled() ? (int) $cache['entries'] . ' cached page(s), ' . number_format($cache['bytes'] / 1024) . ' KB' : 'Disabled' ?></dd>
    </dl>
    <?php if ($caps['edit'] || $caps['seo']): ?>
      <div class="cms-form-actions">
        <form method="post" action="/admin/cms" class="cms-inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="flush_cache">
          <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Clear page cache</button>
        </form>
        <form method="post" action="/admin/cms" class="cms-inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="process_schedule">
          <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Run scheduled publishing now</button>
        </form>
      </div>
    <?php endif; ?>
    <ul class="cms-link-list cms-mt">
      <?php if ($caps['seo']): ?><li><a href="/admin/cms-seo">SEO audit</a></li><li><a href="/admin/cms-technical">Sitemap &amp; robots.txt</a></li><?php endif; ?>
      <?php if ($caps['redirects']): ?><li><a href="/admin/cms-redirects">Redirects</a></li><li><a href="/admin/cms-migration">Migration</a></li><?php endif; ?>
      <?php if ($caps['code']): ?><li><a href="/admin/cms-site-config">Site configuration</a></li><?php endif; ?>
      <?php if ($caps['edit']): ?><li><a href="/admin/cms-city-generate">Generate city page drafts</a></li><?php endif; ?>
    </ul>
  </div>
</div>

<div class="admin-card">
  <h2 class="admin-card-title">Recent website activity</h2>
  <?php if ($recent === []): ?>
    <p class="admin-field-hint">No activity yet.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>When</th><th>Who</th><th>What</th><th>Item</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $row): $meta = json_decode((string) ($row['meta_json'] ?? ''), true) ?: []; ?>
        <tr>
          <td><?= e(cms_admin_date((string) $row['created_at'])) ?></td>
          <td><?= e((string) $row['user_name']) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', substr((string) $row['action'], 4)))) ?></td>
          <td>
            <?php if ($row['subject_type'] === 'cms_content' && $row['subject_id']): ?>
              <a href="/admin/cms-edit?id=<?= (int) $row['subject_id'] ?>"><?= e((string) ($meta['title'] ?? $meta['path'] ?? ('#' . $row['subject_id']))) ?></a>
            <?php else: ?>
              <?= e((string) ($meta['path'] ?? $meta['name'] ?? '')) ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
