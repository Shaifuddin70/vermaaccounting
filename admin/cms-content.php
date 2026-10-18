<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$repo = new CmsContentRepository();
$type = (string) ($_GET['type'] ?? '');
if (!isset(CmsContentRepository::TYPES[$type])) {
    $type = '';
}
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !isset(CmsContentRepository::STATUSES[$status])) {
    $status = '';
}
$folderParam = (string) ($_GET['folder'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$termId = (int) ($_GET['term'] ?? 0);
$sort = (string) ($_GET['sort'] ?? 'updated');
$dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$filters = ['type' => $type, 'status' => $status, 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'term_id' => $termId];
if ($folderParam !== '') {
    $filters['folder_id'] = (int) $folderParam;
}
$total = $repo->count($filters);
$pagination = pagination_meta($total, pagination_page_from_request(), pagination_per_page_from_request());
$items = $repo->search($filters, $pagination['per_page'], $pagination['offset']);
$counts = $repo->statusCounts($type !== '' ? $type : null);

$baseQuery = array_filter(['type' => $type, 'status' => $status, 'folder' => $folderParam, 'q' => $q, 'term' => $termId ?: '', 'sort' => $sort !== 'updated' ? $sort : '', 'dir' => $dir !== 'desc' ? $dir : ''], static fn ($v) => $v !== '' && $v !== null);
$paginationPath = '/admin/cms-content';
$paginationQuery = $baseQuery;
$paginationLabel = 'items';
$paginationUrl = fn (int $p) => pagination_url('/admin/cms-content', $baseQuery, $p, $pagination['per_page']);
$sortUrl = static function (string $col) use ($baseQuery, $sort, $dir): string {
    $q = $baseQuery;
    $q['sort'] = $col;
    $q['dir'] = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    return '/admin/cms-content?' . http_build_query($q);
};
$sortMark = static fn (string $col): string => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';

$folders = [];
$landingFolders = [];
foreach ((new CmsFolderRepository())->all() as $f) {
    $folders[(int) $f['id']] = $f;
    if (!empty($f['landing_content_id'])) {
        $landingFolders[(int) $f['landing_content_id']] = (int) $f['id'];
    }
}
$caps = cms_admin_caps();
$csrf = Auth::csrfToken();
$homeId = (int) CmsSiteConfig::value('home_content_id', 0);
$returnTo = $_SERVER['REQUEST_URI'] ?? '/admin/cms-content';

$pageTitle = cms_admin_type_plural($type);
$activeNav = $type !== '' ? cms_admin_type_nav($type) : 'cms';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
  <?php if ($caps['edit']): ?>
    <div class="admin-header-actions">
      <?php if ($type === 'city'): ?>
        <?php if (Auth::can('cms.publish')): ?><a href="/admin/cms-city-defaults" class="admin-btn admin-btn-secondary">Default content</a><?php endif; ?>
        <a href="/admin/cms-city-generate" class="admin-btn admin-btn-secondary">Add city pages</a>
      <?php endif; ?>
      <?php if ($type === 'article' && Auth::can('cms.edit')): ?>
        <a href="/admin/cms-blog-generate" class="admin-btn admin-btn-secondary">AI blog</a>
      <?php endif; ?>
      <a href="/admin/cms-edit?type=<?= e($type !== '' ? $type : 'page') ?>" class="admin-btn">+ New <?= e(strtolower(CmsContentRepository::TYPES[$type !== '' ? $type : 'page'])) ?></a>
    </div>
  <?php endif; ?>
</div>

<nav class="admin-tabs" aria-label="Filter by status">
  <?php $tabQuery = $baseQuery; unset($tabQuery['status']); ?>
  <a class="admin-tab <?= $status === '' ? 'is-active' : '' ?>" href="/admin/cms-content?<?= e(http_build_query($tabQuery)) ?>">All <span class="admin-tab-count"><?= number_format(array_sum($counts) - $counts['archived']) ?></span></a>
  <?php foreach (CmsContentRepository::STATUSES as $key => $label): ?>
    <?php if ($counts[$key] > 0 || $status === $key): ?>
      <a class="admin-tab <?= $status === $key ? 'is-active' : '' ?>" href="/admin/cms-content?<?= e(http_build_query($tabQuery + ['status' => $key])) ?>"><?= e($label) ?> <span class="admin-tab-count"><?= number_format($counts[$key]) ?></span></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

<div class="admin-card cms-list-card">
  <form method="get" action="/admin/cms-content" class="cms-filters">
    <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="admin-field cms-filters-search">
      <label for="cms-q">Search</label>
      <input type="search" id="cms-q" name="q" value="<?= e($q) ?>" placeholder="Title, internal name or URL">
    </div>
    <div class="admin-field">
      <label for="cms-folder">Folder</label>
      <select id="cms-folder" name="folder">
        <option value="">All folders</option>
        <option value="0" <?= $folderParam === '0' ? 'selected' : '' ?>>No folder</option>
        <?= str_replace('<option value="">— No folder (site root) —</option>', '', cms_admin_folder_options($folderParam !== '' ? (int) $folderParam : null, false)) ?>
      </select>
    </div>
    <?php if ($type === '' || $type === 'article'): ?>
      <div class="admin-field">
        <label for="cms-term">Category / tag</label>
        <select id="cms-term" name="term">
          <option value="">Any</option>
          <?php foreach ($repo->terms() as $term): ?>
            <option value="<?= (int) $term['id'] ?>" <?= $termId === (int) $term['id'] ? 'selected' : '' ?>><?= e(ucfirst($term['kind']) . ': ' . $term['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="cms-filters-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
      <?php if ($q !== '' || $folderParam !== '' || $termId): ?>
        <a href="/admin/cms-content?<?= e(http_build_query(array_filter(['type' => $type, 'status' => $status]))) ?>" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($items === []): ?>
    <div class="admin-empty-state">
      <h2 class="admin-empty-state-title">Nothing here yet</h2>
      <p class="admin-empty-state-text">
        <?php if ($type === 'article' && CmsMigration::pendingBlogCount() > 0): ?>
          There are <?= CmsMigration::pendingBlogCount() ?> posts from the old blog waiting to be imported. Import them from the <a href="/admin/cms">Website overview</a>.
        <?php else: ?>
          Create your first <?= e(strtolower(CmsContentRepository::TYPES[$type !== '' ? $type : 'page'])) ?> to publish it on the website without touching code.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="cms-list-toolbar">
      <?php if ($caps['publish']): ?>
        <form method="post" action="/admin/cms-action" id="cms-bulk-form" class="cms-bulk-bar" data-bulk-form>
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="bulk">
          <input type="hidden" name="return" value="<?= e($returnTo) ?>">
          <span class="cms-bulk-count" data-bulk-count aria-live="polite">None selected</span>
          <select name="bulk_action" aria-label="Bulk action" class="admin-pagination-per-page-select" data-bulk-action>
            <option value="">Bulk action…</option>
            <?php foreach (CmsContentService::BULK_ACTIONS as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="admin-btn admin-btn-sm" data-bulk-apply disabled>Apply</button>
        </form>
      <?php endif; ?>
      <?php $paginationShow = 'per_page_bare'; require __DIR__ . '/includes/pagination.php'; ?>
    </div>
    <div class="admin-table-scroll">
      <table class="admin-table cms-content-table">
        <thead>
          <tr>
            <?php if ($caps['publish']): ?>
              <th class="cms-select-cell"><input type="checkbox" form="cms-bulk-form" data-check-all="ids[]" aria-label="Select all on this page"></th>
            <?php endif; ?>
            <th><a href="<?= e($sortUrl('title')) ?>">Title<?= $sortMark('title') ?></a></th>
            <th><a href="<?= e($sortUrl('path')) ?>">URL<?= $sortMark('path') ?></a></th>
            <?php if ($type === ''): ?><th><a href="<?= e($sortUrl('type')) ?>">Type<?= $sortMark('type') ?></a></th><?php endif; ?>
            <th><a href="<?= e($sortUrl('status')) ?>">Status<?= $sortMark('status') ?></a></th>
            <th>SEO</th>
            <th><a href="<?= e($sortUrl('updated')) ?>">Updated<?= $sortMark('updated') ?></a></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item):
            $id = (int) $item['id'];
            $live = CmsContentRepository::isLive($item);
            $seoFlags = [];
            $cityDefaults = [];
            if ($item['type'] === 'city') {
                $cityDefaults = CmsCityDefaults::defaultedFields($item, $item);
                $item = CmsCityDefaults::apply($item);
                $item['meta_description'] = CmsCityDefaults::applySeo($item, ['meta_description' => $item['meta_description']])['meta_description'];
            }
            if (trim((string) $item['meta_description']) === '') {
                $seoFlags[] = 'No description';
            }
            if (($item['robots_index'] ?? '') === 'noindex') {
                $seoFlags[] = 'Noindex';
            }
            if (($item['canonical_mode'] ?? '') === 'custom') {
                $seoFlags[] = 'Custom canonical';
            }
          ?>
            <tr>
              <?php if ($caps['publish']): ?>
                <td class="cms-select-cell"><input type="checkbox" name="ids[]" value="<?= $id ?>" form="cms-bulk-form" aria-label="Select <?= e((string) $item['title']) ?>"></td>
              <?php endif; ?>
              <td class="cms-title-cell">
                <a href="/admin/cms-edit?id=<?= $id ?>" class="cms-title-link"><?= e((string) $item['title']) ?></a>
                <?php
                $badges = [];
                if ($id === $homeId) {
                    $badges[] = '<span class="admin-badge admin-badge-info">Homepage</span>';
                }
                if (isset($landingFolders[$id])) {
                    $badges[] = '<span class="admin-badge admin-badge-muted" title="Pages can be created under this page">Has sub-pages</span>';
                }
                if ($item['type'] === 'page' && CmsNavigation::placement((string) $item['path']) !== '') {
                    $badges[] = '<span class="admin-badge admin-badge-info" title="Shown in the header menu">In menu</span>';
                }
                if (in_array('body_html', $cityDefaults, true)) {
                    $badges[] = '<span class="admin-badge admin-badge-muted" title="Fields left empty on this page show the city page defaults">Default content</span>';
                }
                ?>
                <?php if ($badges !== []): ?><div class="cms-title-badges"><?= implode('', $badges) ?></div><?php endif; ?>
                <?php if ($item['internal_name'] !== '' && $item['internal_name'] !== $item['title']): ?>
                  <div class="admin-table-sub"><?= e((string) $item['internal_name']) ?></div>
                <?php endif; ?>
                <?php if ($item['folder_id'] && isset($folders[(int) $item['folder_id']])): ?>
                  <div class="admin-table-sub"><?= !empty($folders[(int) $item['folder_id']]['landing_content_id']) ? 'Sub-page of' : 'Folder:' ?> <?= e((string) $folders[(int) $item['folder_id']]['name']) ?></div>
                <?php endif; ?>
              </td>
              <td class="cms-path-cell">
                <?php if ($live): ?>
                  <a href="<?= e($item['path']) ?>" target="_blank" rel="noopener"><?= e((string) $item['path']) ?></a>
                <?php else: ?>
                  <code><?= e((string) $item['path']) ?></code>
                <?php endif; ?>
              </td>
              <?php if ($type === ''): ?><td class="cms-nowrap"><?= e(CmsContentRepository::TYPES[$item['type']] ?? $item['type']) ?></td><?php endif; ?>
              <td>
                <?= cms_status_badge((string) $item['status']) ?>
                <?php if ($item['status'] === 'scheduled' && $item['publish_at']): ?>
                  <div class="admin-table-sub"><?= e(cms_admin_date($item['publish_at'])) ?></div>
                <?php elseif ($item['expire_at'] && $live): ?>
                  <div class="admin-table-sub">Expires <?= e(cms_admin_date($item['expire_at'])) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($seoFlags === []): ?>
                  <span class="admin-badge admin-badge-success">OK</span>
                <?php else: ?>
                  <?php foreach ($seoFlags as $flag): ?><span class="admin-badge admin-badge-muted"><?= e($flag) ?></span> <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="cms-date-cell" title="<?= e(cms_admin_date($item['updated_at'])) ?>">
                <?php if ($item['updated_at']): $updated = (new DateTimeImmutable((string) $item['updated_at'], new DateTimeZone('UTC')))->setTimezone(app_timezone()); ?>
                  <?= e($updated->format('M j, Y')) ?>
                  <div class="admin-table-sub"><?= e($updated->format('g:i A')) ?></div>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="cms-actions-cell">
                <div class="admin-table-actions">
                  <a href="/admin/cms-edit?id=<?= $id ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                  <?php if ($caps['publish']): ?>
                    <form method="post" action="/admin/cms-action" class="cms-inline-form">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                      <?php if ($item['status'] === 'published'): ?>
                        <button type="submit" name="action" value="unpublish" class="admin-btn admin-btn-sm admin-btn-secondary" data-confirm="Unpublish “<?= e((string) $item['title']) ?>”? Visitors will get a 404 unless you add a redirect.">Unpublish</button>
                      <?php elseif ($item['status'] === 'archived'): ?>
                        <button type="submit" name="action" value="restore" class="admin-btn admin-btn-sm admin-btn-secondary">Restore</button>
                      <?php else: ?>
                        <button type="submit" name="action" value="publish" class="admin-btn admin-btn-sm">Publish</button>
                      <?php endif; ?>
                      <?php if ($item['status'] !== 'archived'): ?>
                        <button type="submit" name="action" value="archive" class="admin-btn admin-btn-sm admin-btn-secondary" data-confirm="Archive “<?= e((string) $item['title']) ?>”? It will be taken offline.">Archive</button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
