<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.redirects');

$repo = new CmsRedirectRepository();
$canExternal = Auth::can('cms.code');

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirects-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['source', 'target', 'code', 'note', 'origin', 'hits', 'last_hit_at']);
    foreach ($repo->all() as $r) {
        fputcsv($out, [$r['source_path'], $r['target'], $r['status_code'], $r['note'], $r['origin'], $r['hits'], $r['last_hit_at']]);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $code = (int) ($_POST['status_code'] ?? 301);
        $check = $repo->check($id, (string) ($_POST['source'] ?? ''), (string) ($_POST['target'] ?? ''), $code, $canExternal);
        $confirmed = !empty($_POST['confirmed']);
        if ($check['errors'] || ($check['warnings'] && !$confirmed)) {
            $_SESSION['cms_redirect_old'] = array_intersect_key($_POST, array_flip(['id', 'source', 'target', 'status_code', 'note'])) + ['warnings' => $check['warnings'], 'errors' => $check['errors']];
            cms_admin_back('/admin/cms-redirects?edit=' . ($id ?: 'new'), null, $check['errors'] ? implode(' ', $check['errors']) : 'Please review the warnings below, then save again to confirm.');
        }
        $saveId = $id ?? ($check['overwrite'] ? (int) $check['overwrite']['id'] : null);
        $savedId = $repo->save($saveId, $check['source'], $check['target'], $code, 'manual', trim((string) ($_POST['note'] ?? '')));
        CmsCache::flush();
        ActivityLog::record('cms.redirect_saved', 'cms_redirect', $savedId, ['path' => $check['source'], 'target' => $check['target'], 'code' => $code]);
        cms_admin_back('/admin/cms-redirects', 'Redirect saved: ' . $check['source'] . ($code === 410 ? ' → 410 Gone' : ' → ' . $check['target']) . '.');
    }

    if ($action === 'delete') {
        $r = $repo->find((int) ($_POST['id'] ?? 0));
        if ($r) {
            $repo->delete((int) $r['id']);
            ActivityLog::record('cms.redirect_deleted', 'cms_redirect', (int) $r['id'], ['path' => $r['source_path']]);
        }
        $return = (string) ($_POST['return'] ?? '');
        cms_admin_back(str_starts_with($return, '/admin/cms-redirects') ? $return : '/admin/cms-redirects', 'Redirect removed.');
    }

    if ($action === 'flatten') {
        $n = $repo->flattenChains();
        ActivityLog::record('cms.redirect_chains_fixed', 'cms_redirect', null, ['count' => $n]);
        cms_admin_back('/admin/cms-redirects', $n . ' chained redirect(s) now point straight to their final destination.');
    }

    if ($action === 'import') {
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            cms_admin_back('/admin/cms-redirects', null, 'Choose a CSV file to import.');
        }
        if ((int) $file['size'] > 2 * 1024 * 1024) {
            cms_admin_back('/admin/cms-redirects', null, 'The CSV file is larger than 2 MB.');
        }
        $result = $repo->importCsv((string) $file['tmp_name'], !empty($_POST['overwrite']), $canExternal);
        CmsCache::flush();
        ActivityLog::record('cms.redirects_imported', 'cms_redirect', null, ['saved' => $result['saved'], 'skipped' => count($result['skipped'])]);
        $_SESSION['cms_redirect_import_skipped'] = array_slice($result['skipped'], 0, 100);
        cms_admin_back('/admin/cms-redirects', $result['saved'] . ' redirect(s) imported' . ($result['skipped'] ? ', ' . count($result['skipped']) . ' skipped (see below).' : '.'));
    }

    cms_admin_back('/admin/cms-redirects', null, 'Unknown action.');
}

$q = trim((string) ($_GET['q'] ?? ''));
$origin = (string) ($_GET['origin'] ?? '');
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = $repo->count($q, $origin);
$pagination = pagination_meta($total, $page, $perPage);
$rows = $repo->search($q, $perPage, ($pagination['page'] - 1) * $perPage, $origin);
$problems = $repo->problems();
$problemById = [];
foreach ($problems as $p) {
    $problemById[$p['id']] = $p;
}

$old = $_SESSION['cms_redirect_old'] ?? null;
unset($_SESSION['cms_redirect_old']);
$skipped = $_SESSION['cms_redirect_import_skipped'] ?? [];
unset($_SESSION['cms_redirect_import_skipped']);
$editParam = (string) ($_GET['edit'] ?? '');
$editing = null;
if ($editParam === 'new') {
    $editing = ['id' => 0, 'source_path' => (string) ($_GET['source'] ?? ''), 'target' => '', 'status_code' => 301, 'note' => ''];
} elseif ($editParam !== '') {
    $editing = $repo->find((int) $editParam);
}
if ($editing && is_array($old)) {
    $editing = ['id' => (int) ($old['id'] ?? 0), 'source_path' => (string) ($old['source'] ?? ''), 'target' => (string) ($old['target'] ?? ''), 'status_code' => (int) ($old['status_code'] ?? 301), 'note' => (string) ($old['note'] ?? ''), 'warnings' => $old['warnings'] ?? [], 'errors' => $old['errors'] ?? []];
}

$testUrl = trim((string) ($_GET['test'] ?? ''));
$testResult = null;
if ($testUrl !== '') {
    $parsed = CmsPath::parseTarget($testUrl, CmsSiteConfig::baseUrl());
    if ($parsed['ok'] && $parsed['internal']) {
        $path = strtok($parsed['value'], '?') ?: '/';
        $trace = $repo->trace($path);
        $final = strtok((string) $trace['final'], '?') ?: '/';
        $testResult = [
            'hops' => $trace['hops'],
            'loop' => $trace['loop'],
            'resolves' => !str_starts_with((string) $trace['final'], '/') || $final === '/' || CmsRouter::exists($final) || is_file(PROJECT_ROOT . $final),
        ];
    } else {
        $testResult = ['error' => $parsed['error'] ?: 'Enter a URL on this site.'];
    }
}

$csrf = Auth::csrfToken();
$paginationPath = '/admin/cms-redirects';
$paginationQuery = array_filter(['q' => $q, 'origin' => $origin]);
$paginationLabel = 'redirects';
$paginationUrl = fn (int $p) => pagination_url('/admin/cms-redirects', $paginationQuery, $p, $perPage);

$pageTitle = 'Redirects';
$activeNav = 'cms-redirects';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Redirects</h1>
  <div class="admin-header-actions">
    <a class="admin-btn" href="/admin/cms-redirects?edit=new">Add redirect</a>
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-redirects?export=csv">Export CSV</a>
  </div>
</div>

<?php if ($editing): ?>
  <div class="admin-card">
    <h2 class="admin-card-title"><?= $editing['id'] ? 'Edit redirect' : 'New redirect' ?></h2>
    <?php foreach ($editing['warnings'] ?? [] as $w): ?><div class="admin-alert admin-alert-info"><?= e($w) ?></div><?php endforeach; ?>
    <form method="post" action="/admin/cms-redirects">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <?php if (!empty($editing['warnings']) && empty($editing['errors'])): ?><input type="hidden" name="confirmed" value="1"><?php endif; ?>
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="r-source">From (old URL)</label>
          <input type="text" id="r-source" name="source" value="<?= e((string) $editing['source_path']) ?>" placeholder="/old-page" required>
        </div>
        <div class="admin-field">
          <label for="r-code">Type</label>
          <select id="r-code" name="status_code">
            <?php foreach (CmsRedirectRepository::CODES as $code => $label): ?>
              <option value="<?= $code ?>" <?= (int) $editing['status_code'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field admin-field--full">
          <label for="r-target">To (new URL)</label>
          <input type="text" id="r-target" name="target" value="<?= e((string) $editing['target']) ?>" placeholder="/new-page<?= $canExternal ? ' or https://other-site.com/page' : '' ?>">
          <p class="admin-field-hint">Use a path on this site. <?= $canExternal ? 'External URLs are allowed for your role.' : 'Redirecting to another website needs site code permission.' ?> Use 301 for permanent moves so rankings carry over; 410 tells search engines a page is gone for good.</p>
        </div>
        <div class="admin-field admin-field--full">
          <label for="r-note">Note</label>
          <input type="text" id="r-note" name="note" value="<?= e((string) $editing['note']) ?>" maxlength="255">
        </div>
      </div>
      <div class="cms-form-actions">
        <button type="submit" class="admin-btn"><?= !empty($editing['warnings']) && empty($editing['errors']) ? 'Save anyway' : 'Save redirect' ?></button>
        <a class="admin-btn admin-btn-secondary" href="/admin/cms-redirects">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if ($problems): ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Problems (<?= count($problems) ?>)</h2>
    <table class="admin-table">
      <thead><tr><th>Type</th><th>Path</th><th>Detail</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($problems as $p): ?>
        <tr>
          <td><span class="admin-badge <?= $p['kind'] === 'chain' ? 'admin-badge-muted' : 'admin-badge-info' ?>"><?= e(['loop' => 'Loop', 'chain' => 'Chain', 'broken' => 'Dead end'][$p['kind']]) ?></span></td>
          <td><code><?= e($p['source']) ?></code></td>
          <td><?= e($p['detail']) ?></td>
          <td class="admin-table-actions"><a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-redirects?edit=<?= $p['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (array_filter($problems, fn ($p) => $p['kind'] === 'chain')): ?>
      <form method="post" action="/admin/cms-redirects" class="cms-form-actions">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="flatten">
        <button type="submit" class="admin-btn admin-btn-sm" data-confirm="Point every chained redirect straight at its final destination?">Fix chains</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-fields-2col">
  <div class="admin-card">
    <h2 class="admin-card-title">Test a URL</h2>
    <form method="get" action="/admin/cms-redirects" class="cms-filters">
      <div class="admin-field"><label for="t-url">URL or path</label><input type="text" id="t-url" name="test" value="<?= e($testUrl) ?>" placeholder="/old-page"></div>
      <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Test</button></div>
    </form>
    <?php if ($testResult): ?>
      <?php if (isset($testResult['error'])): ?>
        <p class="admin-alert admin-alert-error"><?= e($testResult['error']) ?></p>
      <?php elseif (count($testResult['hops']) === 1): ?>
        <p>No redirect. <?= $testResult['resolves'] ? 'The URL shows a page.' : 'The URL returns 404.' ?>
          <?php if (!$testResult['resolves']): ?><a href="/admin/cms-redirects?edit=new&amp;source=<?= e(rawurlencode((string) $testResult['hops'][0])) ?>">Add a redirect</a><?php endif; ?></p>
      <?php else: ?>
        <p><?= e(implode(' → ', $testResult['hops'])) ?></p>
        <p><?= $testResult['loop'] ? '<strong>Loop detected.</strong>' : (count($testResult['hops']) > 2 ? 'Chain of ' . (count($testResult['hops']) - 1) . ' redirects. ' : '') . ($testResult['loop'] ? '' : ($testResult['resolves'] ? 'Ends on a working page.' : 'Ends on a 404.')) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="admin-card">
    <h2 class="admin-card-title">Import CSV</h2>
    <form method="post" action="/admin/cms-redirects" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="import">
      <div class="admin-field">
        <input type="file" name="csv" accept=".csv,text/csv" required aria-label="CSV file">
        <p class="admin-field-hint">Columns: <code>source,target,code,note</code> (header row optional; code defaults to 301). Each row is validated; rows with loops or invalid URLs are skipped.</p>
      </div>
      <label class="admin-checkbox-label"><input type="checkbox" name="overwrite" value="1"> <span>Replace existing redirects with the same source</span></label>
      <div class="cms-form-actions"><button type="submit" class="admin-btn admin-btn-secondary">Import</button></div>
    </form>
    <?php if ($skipped): ?>
      <details class="cms-mt" open><summary>Skipped rows (<?= count($skipped) ?>)</summary><ul class="cms-warning-list"><?php foreach ($skipped as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul></details>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <form method="get" action="/admin/cms-redirects" class="cms-filters">
    <div class="admin-field"><label for="r-q">Search</label><input type="search" id="r-q" name="q" value="<?= e($q) ?>" placeholder="Source or destination"></div>
    <div class="admin-field">
      <label for="r-origin">Created by</label>
      <select id="r-origin" name="origin">
        <option value="">Any</option>
        <?php foreach (['manual' => 'Added by hand', 'auto' => 'URL change', 'import' => 'Migration', 'csv' => 'CSV import'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $origin === $v ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Filter</button></div>
  </form>
  <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php if ($rows === []): ?>
    <div class="admin-empty-state"><p class="admin-empty-state-text"><?= $q !== '' || $origin !== '' ? 'No redirects match.' : 'No redirects yet. They are added automatically when you change a published URL.' ?></p></div>
  <?php else: ?>
    <div class="cms-table-scroll">
      <table class="admin-table">
        <thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Created by</th><th class="admin-table-actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= e((string) $r['source_path']) ?></code><?php if (isset($problemById[(int) $r['id']])): ?> <span class="admin-badge admin-badge-info"><?= e(ucfirst($problemById[(int) $r['id']]['kind'])) ?></span><?php endif; ?></td>
            <td><?= (int) $r['status_code'] === 410 ? '<span class="admin-table-sub">Gone</span>' : '<code>' . e((string) $r['target']) . '</code>' ?></td>
            <td><?= (int) $r['status_code'] ?></td>
            <td><?= (int) $r['hits'] ?><?php if ($r['last_hit_at']): ?><div class="admin-table-sub"><?= e(cms_admin_date((string) $r['last_hit_at'])) ?></div><?php endif; ?></td>
            <td><?= e((string) $r['origin']) ?><?php if ($r['note']): ?><div class="admin-table-sub"><?= e((string) $r['note']) ?></div><?php endif; ?></td>
            <td class="admin-table-actions">
              <a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-redirects?edit=<?= (int) $r['id'] ?>">Edit</a>
              <form method="post" action="/admin/cms-redirects" class="cms-inline-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="return" value="<?= e('/admin/cms-redirects?' . http_build_query(array_filter(['q' => $q, 'origin' => $origin, 'page' => $pagination['page'] > 1 ? $pagination['page'] : null]))) ?>">
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Remove the redirect from <?= e((string) $r['source_path']) ?>? Visitors to the old URL may get a 404.">Delete</button>
              </form>
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
