<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$media = new CmsMediaRepository();
$isAjax = ($_GET['format'] ?? $_POST['format'] ?? '') === 'json';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Auth::verifyCsrf((string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        $isAjax ? json_response(['error' => 'Session expired. Reload the page.'], 403) : cms_admin_back('/admin/cms-media', null, 'Session expired. Please try again.');
    }
    if (!Auth::can('cms.edit')) {
        $isAjax ? json_response(['error' => 'You do not have permission to change media.'], 403) : cms_admin_back('/admin/cms-media', null, 'You do not have permission to change media.');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'upload') {
        $files = $_FILES['file'] ?? null;
        if (!is_array($files)) {
            $isAjax ? json_response(['error' => 'No file received.'], 400) : cms_admin_back('/admin/cms-media', null, 'Choose an image to upload.');
        }
        $list = [];
        if (is_array($files['name'])) {
            foreach (array_keys($files['name']) as $i) {
                $list[] = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            }
        } else {
            $list[] = $files;
        }
        $done = [];
        $errors = [];
        foreach ($list as $file) {
            try {
                $done[] = $media->upload($file, trim((string) ($_POST['alt'] ?? '')));
            } catch (Throwable $e) {
                $errors[] = ($file['name'] ?? 'file') . ': ' . $e->getMessage();
            }
        }
        if ($done) {
            ActivityLog::record('cms.media_uploaded', 'cms_media', $done[0]['id'], ['count' => count($done)]);
        }
        if ($isAjax) {
            if (!$done) {
                json_response(['error' => implode(' ', $errors)], 400);
            }
            $first = $done[0];
            json_response(['location' => $first['url'], 'item' => $first, 'errors' => $errors]);
        }
        cms_admin_back('/admin/cms-media', $done ? count($done) . ' image(s) uploaded.' : null, $errors ? implode(' ', $errors) : null);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'update') {
        $media->updateMeta($id, trim((string) ($_POST['alt'] ?? '')), trim((string) ($_POST['caption'] ?? '')), trim((string) ($_POST['credit'] ?? '')));
        CmsCache::flush();
        cms_admin_back('/admin/cms-media?edit=' . $id, 'Image details saved.');
    }
    if ($action === 'delete') {
        $usage = $media->usage($id);
        if ($usage && empty($_POST['force'])) {
            cms_admin_back('/admin/cms-media?edit=' . $id, null, 'This image is used on ' . count($usage) . ' page(s). Remove it from those pages first, or confirm deletion.');
        }
        $media->delete($id);
        CmsCache::flush();
        ActivityLog::record('cms.media_deleted', 'cms_media', $id);
        cms_admin_back('/admin/cms-media', 'Image deleted.');
    }
    cms_admin_back('/admin/cms-media', null, 'Unknown action.');
}

$q = trim((string) ($_GET['q'] ?? ''));
$perPage = $isAjax ? 24 : 48;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = $media->count($q);
$items = $media->search($q, $perPage, ($page - 1) * $perPage);

if ($isAjax) {
    json_response([
        'items' => array_map(static fn ($m) => ['id' => $m['id'], 'url' => $m['url'], 'alt' => $m['alt'], 'width' => $m['width'], 'height' => $m['height'], 'srcset' => $m['srcset'], 'name' => $m['original_name']], $items),
        'total' => $total,
        'page' => $page,
        'pages' => (int) ceil($total / $perPage),
    ]);
}

$editing = isset($_GET['edit']) ? $media->find((int) $_GET['edit']) : null;
$csrf = Auth::csrfToken();
$canEdit = Auth::can('cms.edit');
$pagination = pagination_meta($total, $page, $perPage);
$paginationPath = '/admin/cms-media';
$paginationQuery = array_filter(['q' => $q]);
$paginationLabel = 'images';
$paginationUrl = fn (int $p) => pagination_url('/admin/cms-media', $paginationQuery, $p, $perPage);

$pageTitle = 'Media library';
$activeNav = 'cms-media';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Media library</h1>
</div>

<?php if ($canEdit): ?>
<div class="admin-card">
  <form method="post" action="/admin/cms-media" enctype="multipart/form-data" class="cms-upload-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="upload">
    <div class="admin-field">
      <label for="m-file">Upload images</label>
      <input type="file" id="m-file" name="file[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
      <p class="admin-field-hint">JPG, PNG, GIF or WebP up to <?= CmsMediaRepository::MAX_BYTES / 1024 / 1024 ?> MB. Files are renamed from their filename (use descriptive names) and resized copies are made for faster pages.</p>
    </div>
    <div class="admin-field">
      <label for="m-alt">Alt text (applies to all files in this upload)</label>
      <input type="text" id="m-alt" name="alt" maxlength="500" placeholder="Describe what the image shows">
    </div>
    <button type="submit" class="admin-btn">Upload</button>
  </form>
</div>
<?php endif; ?>

<?php if ($editing): ?>
  <?php $usage = $media->usage($editing['id']); ?>
  <div class="admin-card cms-media-edit">
    <div class="cms-media-edit-grid">
      <img src="<?= e($editing['url']) ?>" alt="<?= e($editing['alt']) ?>" class="cms-media-edit-img">
      <div>
        <h2 class="admin-card-title"><?= e((string) $editing['original_name']) ?></h2>
        <p class="admin-table-sub">
          <?= (int) $editing['width'] ?>×<?= (int) $editing['height'] ?> · <?= number_format($editing['bytes'] / 1024) ?> KB · <?= e((string) $editing['mime']) ?>
          <?php if ($editing['oversized']): ?><span class="admin-badge admin-badge-muted">Large file — consider compressing</span><?php endif; ?>
        </p>
        <p><input type="text" readonly value="<?= e($editing['url']) ?>" class="cms-copy-input" onclick="this.select()"></p>
        <?php if ($canEdit): ?>
          <form method="post" action="/admin/cms-media">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <div class="admin-field"><label for="e-alt">Alt text</label><input type="text" id="e-alt" name="alt" value="<?= e((string) $editing['alt']) ?>" maxlength="500"></div>
            <div class="admin-field"><label for="e-caption">Caption</label><input type="text" id="e-caption" name="caption" value="<?= e((string) $editing['caption']) ?>" maxlength="500"></div>
            <div class="admin-field"><label for="e-credit">Credit</label><input type="text" id="e-credit" name="credit" value="<?= e((string) $editing['credit']) ?>" maxlength="255"></div>
            <button type="submit" class="admin-btn">Save details</button>
          </form>
        <?php endif; ?>
        <h3 class="cms-mt">Used on</h3>
        <?php if ($usage === []): ?>
          <p class="admin-field-hint">Not used on any CMS page.</p>
        <?php else: ?>
          <ul><?php foreach ($usage as $u): ?><li><a href="/admin/cms-edit?id=<?= (int) $u['id'] ?>"><?= e((string) $u['title']) ?></a> <code><?= e((string) $u['path']) ?></code></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($canEdit): ?>
          <form method="post" action="/admin/cms-media" class="cms-mt">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php if ($usage): ?><input type="hidden" name="force" value="1"><?php endif; ?>
            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="<?= $usage ? 'This image is used on ' . count($usage) . ' page(s) and will break there. Delete anyway?' : 'Delete this image?' ?>">Delete image</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="get" action="/admin/cms-media" class="cms-filters">
    <div class="admin-field">
      <label for="m-q">Search</label>
      <input type="search" id="m-q" name="q" value="<?= e($q) ?>" placeholder="File name, alt text or caption">
    </div>
    <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Search</button></div>
  </form>
  <?php if ($items === []): ?>
    <div class="admin-empty-state"><h2 class="admin-empty-state-title">No images yet</h2><p class="admin-empty-state-text">Upload images to use them on pages and articles.</p></div>
  <?php else: ?>
    <div class="cms-media-grid">
      <?php foreach ($items as $m): ?>
        <a class="cms-media-tile<?= $editing && $editing['id'] === $m['id'] ? ' is-active' : '' ?>" href="/admin/cms-media?<?= e(http_build_query(array_filter(['edit' => $m['id'], 'q' => $q, 'page' => $page > 1 ? $page : null]))) ?>">
          <img src="<?= e($m['url']) ?>" alt="" loading="lazy">
          <span class="cms-media-tile-name"><?= e((string) $m['original_name']) ?></span>
          <?php if (trim((string) $m['alt']) === ''): ?><span class="cms-media-flag">No alt</span><?php endif; ?>
          <?php if ($m['oversized']): ?><span class="cms-media-flag cms-media-flag--size">Large</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
