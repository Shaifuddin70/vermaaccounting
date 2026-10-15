<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$folders = new CmsFolderRepository();
$contents = new CmsContentRepository();
$canEdit = Auth::can('cms.edit');
$canSeo = Auth::can('cms.seo');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    if (!$canEdit) {
        cms_admin_back('/admin/cms-folders', null, 'You do not have permission to change folders.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save_folder') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $parent = (int) ($_POST['parent_id'] ?? 0) ?: null;
        $prefixRaw = trim((string) ($_POST['url_prefix'] ?? ''));
        $prefix = $prefixRaw === '' || $prefixRaw === '/' ? '' : CmsPath::normalize($prefixRaw);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Enter a folder name.';
        }
        if ($prefix !== '') {
            foreach (CmsPath::validate($prefix) as $problem) {
                $errors[] = 'URL prefix: ' . $problem;
            }
        }
        if ($id > 0 && $folders->wouldCreateCycle($id, $parent)) {
            $errors[] = 'A folder cannot be moved inside itself.';
        }
        $type = in_array($_POST['default_type'] ?? '', ['page', 'article', 'city'], true) ? (string) $_POST['default_type'] : 'page';
        $landingId = (int) ($_POST['landing_content_id'] ?? 0) ?: null;
        if ($landingId !== null && $contents->find($landingId) === null) {
            $errors[] = 'The selected landing page no longer exists.';
        }
        $data = [
            'name' => mb_substr($name, 0, 191),
            'slug' => CmsPath::slugify($name),
            'parent_id' => $parent,
            'url_prefix' => $prefix,
            'default_type' => $type,
            'landing_content_id' => $landingId,
            'status' => ($_POST['status'] ?? '') === 'archived' ? 'archived' : 'active',
        ];
        if ($canSeo) {
            $defaults = [];
            foreach (['robots_index' => ['index', 'noindex'], 'robots_follow' => ['follow', 'nofollow'], 'max_image_preview' => ['none', 'standard', 'large']] as $key => $allowed) {
                $value = (string) ($_POST['seo_' . $key] ?? '');
                if (in_array($value, $allowed, true)) {
                    $defaults[$key] = $value;
                }
            }
            foreach (['max_snippet', 'max_video_preview'] as $key) {
                $value = trim((string) ($_POST['seo_' . $key] ?? ''));
                if ($value !== '' && preg_match('/^-?\d+$/', $value)) {
                    $defaults[$key] = (int) $value;
                }
            }
            $data['seo_defaults'] = $defaults;
        }
        if ($errors) {
            $_SESSION['cms_folder_old'] = $_POST;
            cms_admin_back('/admin/cms-folders?edit=' . ($id ?: 'new'), null, implode(' ', $errors));
        }
        if ($id > 0) {
            $folders->update($id, $data);
            ActivityLog::record('cms.folder_updated', 'cms_folder', $id, ['name' => $name]);
        } else {
            $id = $folders->create($data);
            ActivityLog::record('cms.folder_created', 'cms_folder', $id, ['name' => $name]);
        }
        CmsCache::flush();
        cms_admin_back('/admin/cms-folders', 'Folder saved. Existing page URLs are unchanged; the prefix applies to new content.');
    }

    if ($action === 'reorder') {
        $ids = array_values(array_unique(array_map('intval', (array) json_decode((string) ($_POST['order'] ?? '[]'), true))));
        $all = [];
        foreach ($folders->all() as $f) {
            $all[$f['id']] = $f;
        }
        $parents = array_unique(array_map(fn ($fid) => isset($all[$fid]) ? (int) $all[$fid]['parent_id'] : -1, $ids));
        if ($ids === [] || count($parents) !== 1 || in_array(-1, $parents, true)) {
            json_response(['ok' => false, 'error' => 'Folders can only be reordered within the same parent.'], 400);
        }
        $folders->reorder(array_map(fn ($fid) => ['id' => $fid, 'parent_id' => $all[$fid]['parent_id']], $ids));
        json_response(['ok' => true]);
    }

    if ($action === 'move') {
        $folder = $folders->find($id);
        if ($folder) {
            $siblings = array_values(array_filter($folders->all(), fn ($f) => $f['parent_id'] === $folder['parent_id']));
            $index = array_search($id, array_column($siblings, 'id'), true);
            $swap = ($_POST['dir'] ?? '') === 'up' ? $index - 1 : $index + 1;
            if ($index !== false && isset($siblings[$swap])) {
                [$siblings[$index], $siblings[$swap]] = [$siblings[$swap], $siblings[$index]];
                $folders->reorder(array_map(fn ($f) => ['id' => $f['id'], 'parent_id' => $f['parent_id']], $siblings));
            }
        }
        cms_admin_back('/admin/cms-folders');
    }

    if ($action === 'delete_folder') {
        $folder = $folders->find($id);
        if ($folder) {
            $folders->delete($id);
            ActivityLog::record('cms.folder_deleted', 'cms_folder', $id, ['name' => $folder['name']]);
        }
        cms_admin_back('/admin/cms-folders', 'Folder deleted. Its content and subfolders moved up one level; no URLs changed.');
    }

    if ($action === 'add_term') {
        $kind = ($_POST['kind'] ?? '') === 'tag' ? 'tag' : 'category';
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || CmsPath::slugify($name) === '') {
            cms_admin_back('/admin/cms-folders#terms', null, 'Enter a name.');
        }
        $contents->ensureTerm($kind, $name);
        cms_admin_back('/admin/cms-folders#terms', ucfirst($kind) . ' added.');
    }

    if ($action === 'update_term') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $contents->updateTerm($id, $name, trim((string) ($_POST['description'] ?? '')));
            CmsCache::flush();
        }
        cms_admin_back('/admin/cms-folders#terms', 'Saved.');
    }

    if ($action === 'delete_term') {
        $contents->deleteTerm($id);
        CmsCache::flush();
        cms_admin_back('/admin/cms-folders#terms', 'Deleted.');
    }

    cms_admin_back('/admin/cms-folders', null, 'Unknown action.');
}

$editParam = (string) ($_GET['edit'] ?? '');
$editing = null;
if ($editParam === 'new') {
    $editing = ['id' => 0, 'name' => '', 'parent_id' => null, 'url_prefix' => '', 'default_type' => 'page', 'status' => 'active', 'seo_defaults' => []];
} elseif ($editParam !== '') {
    $editing = $folders->find((int) $editParam);
}
if ($editing && isset($_SESSION['cms_folder_old'])) {
    $old = $_SESSION['cms_folder_old'];
    $editing = array_merge($editing, array_intersect_key($old, array_flip(['name', 'url_prefix', 'default_type', 'status'])));
    $editing['parent_id'] = (int) ($old['parent_id'] ?? 0) ?: null;
}
unset($_SESSION['cms_folder_old']);

$tree = $folders->flatTree(true);
$categories = $contents->terms('category');
$tags = $contents->terms('tag');
$csrf = Auth::csrfToken();

$pageTitle = 'Folders';
$activeNav = 'cms-folders';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Folders &amp; categories</h1>
  <?php if ($canEdit): ?>
    <div class="admin-header-actions"><a class="admin-btn" href="/admin/cms-folders?edit=new">New folder</a></div>
  <?php endif; ?>
</div>

<div class="admin-alert admin-alert-info">
  Folders organise content in the admin and supply a default URL prefix and SEO defaults for new items. Moving or renaming a folder never changes live URLs.
</div>

<?php if ($editing && $canEdit): ?>
  <?php $d = $editing['seo_defaults']; ?>
  <div class="admin-card">
    <h2 class="admin-card-title"><?= $editing['id'] ? 'Edit folder' : 'New folder' ?></h2>
    <form method="post" action="/admin/cms-folders">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="save_folder">
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <div class="admin-fields-2col">
        <div class="admin-field"><label for="fo-name">Name</label><input type="text" id="fo-name" name="name" value="<?= e((string) $editing['name']) ?>" required maxlength="191"></div>
        <div class="admin-field">
          <label for="fo-parent">Parent folder</label>
          <select id="fo-parent" name="parent_id">
            <option value="">— None —</option>
            <?php foreach ($tree as $f): if ($f['id'] === (int) $editing['id']) { continue; } ?>
              <option value="<?= $f['id'] ?>" <?= $f['id'] === (int) $editing['parent_id'] ? 'selected' : '' ?>><?= e(str_repeat('— ', (int) $f['depth']) . $f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="fo-prefix">Default URL prefix</label>
          <input type="text" id="fo-prefix" name="url_prefix" value="<?= e((string) $editing['url_prefix']) ?>" placeholder="/service-area">
          <p class="admin-field-hint">New content in this folder gets URLs like <code><?= e(((string) $editing['url_prefix'] ?: '/prefix')) ?>/page-name</code>. Leave blank for root-level URLs.</p>
        </div>
        <div class="admin-field">
          <label for="fo-type">Default content type</label>
          <select id="fo-type" name="default_type">
            <?php foreach (['page' => 'Page', 'article' => 'Blog article', 'city' => 'City page'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $editing['default_type'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="fo-landing">Landing page</label>
          <select id="fo-landing" name="landing_content_id">
            <option value="">— None —</option>
            <?php foreach ($contents->search(['type' => 'page', 'sort' => 'path', 'dir' => 'asc'], 500) as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (int) ($editing['landing_content_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title'] . ' (' . $p['path'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
          <p class="admin-field-hint">The page that introduces this folder, e.g. the <code>/blog</code> listing page.</p>
        </div>
        <div class="admin-field">
          <label for="fo-status">Status</label>
          <select id="fo-status" name="status">
            <option value="active">Active</option>
            <option value="archived" <?= $editing['status'] === 'archived' ? 'selected' : '' ?>>Archived (hidden from folder menus)</option>
          </select>
        </div>
      </div>
      <?php if ($canSeo): ?>
        <h3 class="cms-mt">SEO defaults for content in this folder</h3>
        <p class="admin-field-hint">Used when a page's own setting is “inherit”. Leave as “site default” to fall back to Site configuration.</p>
        <div class="admin-fields-2col">
          <div class="admin-field">
            <label for="fo-index">Indexing</label>
            <select id="fo-index" name="seo_robots_index">
              <option value="">Site default</option>
              <option value="index" <?= ($d['robots_index'] ?? '') === 'index' ? 'selected' : '' ?>>index</option>
              <option value="noindex" <?= ($d['robots_index'] ?? '') === 'noindex' ? 'selected' : '' ?>>noindex</option>
            </select>
          </div>
          <div class="admin-field">
            <label for="fo-follow">Link following</label>
            <select id="fo-follow" name="seo_robots_follow">
              <option value="">Site default</option>
              <option value="follow" <?= ($d['robots_follow'] ?? '') === 'follow' ? 'selected' : '' ?>>follow</option>
              <option value="nofollow" <?= ($d['robots_follow'] ?? '') === 'nofollow' ? 'selected' : '' ?>>nofollow</option>
            </select>
          </div>
          <div class="admin-field"><label for="fo-snippet">max-snippet</label><input type="text" id="fo-snippet" name="seo_max_snippet" value="<?= e(isset($d['max_snippet']) ? (string) $d['max_snippet'] : '') ?>" placeholder="Site default"></div>
          <div class="admin-field">
            <label for="fo-img">max-image-preview</label>
            <select id="fo-img" name="seo_max_image_preview">
              <option value="">Site default</option>
              <?php foreach (['none', 'standard', 'large'] as $v): ?><option value="<?= $v ?>" <?= ($d['max_image_preview'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field"><label for="fo-video">max-video-preview</label><input type="text" id="fo-video" name="seo_max_video_preview" value="<?= e(isset($d['max_video_preview']) ? (string) $d['max_video_preview'] : '') ?>" placeholder="Site default"></div>
        </div>
      <?php endif; ?>
      <div class="cms-form-actions">
        <button type="submit" class="admin-btn">Save folder</button>
        <a href="/admin/cms-folders" class="admin-btn admin-btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h2 class="admin-card-title">Folders</h2>
  <?php if ($tree === []): ?>
    <div class="admin-empty-state"><p class="admin-empty-state-text">No folders yet. Create folders such as “Services”, “Locations” or “Blog”.</p></div>
  <?php else: ?>
    <?php
    $hasChildren = [];
    foreach ($tree as $f) {
        if ($f['parent_id']) {
            $hasChildren[$f['parent_id']] = true;
        }
    }
    ?>
    <?php if ($canEdit): ?><p class="admin-field-hint">Drag <span aria-hidden="true">⠿</span> to reorder folders within the same parent.</p><?php endif; ?>
    <table class="admin-table" data-folder-tree data-csrf="<?= e($csrf) ?>">
      <thead><tr><th>Folder</th><th>URL prefix</th><th>Default type</th><th>Items</th><th class="admin-table-actions">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($tree as $f): ?>
        <tr class="cms-folder-row" data-id="<?= $f['id'] ?>" data-parent="<?= (int) $f['parent_id'] ?>" data-depth="<?= (int) $f['depth'] ?>">
          <td>
            <?php if ($canEdit): ?><span class="cms-drag-handle" draggable="true" title="Drag to reorder" aria-hidden="true">⠿</span><?php endif; ?>
            <span class="cms-tree-indent" style="--depth: <?= (int) $f['depth'] ?>"></span>
            <?php if (isset($hasChildren[$f['id']])): ?>
              <button type="button" class="cms-folder-toggle" data-folder-toggle aria-expanded="true" aria-label="Collapse <?= e((string) $f['name']) ?>">▾</button>
            <?php else: ?>
              <span class="cms-folder-toggle-spacer"></span>
            <?php endif; ?>
            <a href="/admin/cms-content?folder=<?= $f['id'] ?>"><?= e((string) $f['name']) ?></a>
            <?php if ($f['landing_content_id']): ?><a class="admin-table-sub" href="/admin/cms-edit?id=<?= (int) $f['landing_content_id'] ?>">landing page</a><?php endif; ?>
            <?php if ($f['status'] === 'archived'): ?><span class="admin-badge admin-badge-muted">Archived</span><?php endif; ?>
            <?php if (!empty($f['seo_defaults']['robots_index']) && $f['seo_defaults']['robots_index'] === 'noindex'): ?><span class="admin-badge admin-badge-muted">noindex</span><?php endif; ?>
          </td>
          <td><code><?= e((string) ($f['url_prefix'] ?: '/')) ?></code></td>
          <td><?= e(ucfirst((string) $f['default_type'])) ?></td>
          <td><?= (int) $f['content_count'] ?></td>
          <td class="admin-table-actions">
            <?php if ($canEdit): ?>
              <a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-edit?type=<?= e((string) $f['default_type']) ?>&amp;folder=<?= $f['id'] ?>">Add content</a>
              <a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-folders?edit=<?= $f['id'] ?>">Edit</a>
              <?php foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow): ?>
                <form method="post" action="/admin/cms-folders" class="cms-inline-form">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="move">
                  <input type="hidden" name="id" value="<?= $f['id'] ?>">
                  <input type="hidden" name="dir" value="<?= $dir ?>">
                  <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary" aria-label="Move <?= $dir ?>"><?= $arrow ?></button>
                </form>
              <?php endforeach; ?>
              <form method="post" action="/admin/cms-folders" class="cms-inline-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete_folder">
                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete “<?= e((string) $f['name']) ?>”? Its <?= (int) $f['content_count'] ?> item(s) and subfolders move to the parent folder. No URLs change.">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" id="terms">
  <h2 class="admin-card-title">Blog categories and tags</h2>
  <div class="admin-fields-2col">
    <?php foreach (['category' => $categories, 'tag' => $tags] as $kind => $list): ?>
      <div>
        <h3><?= $kind === 'category' ? 'Categories' : 'Tags' ?></h3>
        <?php if ($list === []): ?><p class="admin-field-hint">None yet.</p><?php endif; ?>
        <ul class="cms-term-list">
          <?php foreach ($list as $t): ?>
            <li>
              <?php if ($canEdit): ?>
                <form method="post" action="/admin/cms-folders" class="cms-term-row">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="update_term">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <input type="text" name="name" value="<?= e((string) $t['name']) ?>" aria-label="Name">
                  <span class="admin-table-sub"><?= (int) $t['uses'] ?> use(s)</span>
                  <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Rename</button>
                  <button type="submit" name="action" value="delete_term" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete “<?= e((string) $t['name']) ?>”? It is removed from <?= (int) $t['uses'] ?> item(s).">Delete</button>
                </form>
              <?php else: ?>
                <?= e((string) $t['name']) ?> <span class="admin-table-sub"><?= (int) $t['uses'] ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($canEdit): ?>
          <form method="post" action="/admin/cms-folders" class="cms-term-row">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add_term">
            <input type="hidden" name="kind" value="<?= $kind ?>">
            <input type="text" name="name" placeholder="New <?= $kind ?>" aria-label="New <?= $kind ?>" required>
            <button type="submit" class="admin-btn admin-btn-sm">Add</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
