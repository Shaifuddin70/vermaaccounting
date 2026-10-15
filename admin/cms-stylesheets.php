<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.code');

$sessionKey = 'cms_stylesheet_draft';
$name = (string) ($_GET['file'] ?? $_POST['file'] ?? '');
$self = static fn (array $q = []): string => '/admin/cms-stylesheets' . ($q ? '?' . http_build_query($q) : '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'create') {
    cms_admin_require_post();
    $result = CmsStylesheets::create((string) ($_POST['new_name'] ?? ''), (string) ($_POST['css'] ?? ''), !empty($_POST['load']));
    if ($result['errors']) {
        $_SESSION['cms_stylesheet_new'] = ['new_name' => (string) ($_POST['new_name'] ?? ''), 'load' => !empty($_POST['load'])];
        cms_admin_back($self() . '#new', null, implode(' ', $result['errors']));
    }
    cms_admin_back($self(['file' => $result['name']]), 'Created ' . CmsStylesheets::DIR . '/' . $result['name'] . (!empty($_POST['load']) ? '. It loads on every page, after the main stylesheets.' : '. It is not loaded anywhere yet.'));
}

if ($name !== '' && CmsStylesheets::path($name) === null) {
    cms_admin_back($self(), null, 'That stylesheet does not exist.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'loading') {
        $load = !empty($_POST['load']);
        if (!CmsStylesheets::setLoad($name, $load)) {
            cms_admin_back($self(['file' => $name]), null, 'Loading can only be changed for stylesheets created here.');
        }
        cms_admin_back($self(['file' => $name]), $load ? $name . ' now loads on every page.' : $name . ' is no longer loaded automatically.');
    }
    if ($action === 'delete') {
        $errors = CmsStylesheets::delete($name);
        if ($errors) {
            cms_admin_back($self(['file' => $name]), null, implode(' ', $errors));
        }
        cms_admin_back($self(), 'Deleted ' . $name . '.');
    }
    if ($action === 'save') {
        $css = (string) ($_POST['css'] ?? '');
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $errors = CmsStylesheets::save($name, $css, (string) ($_POST['hash'] ?? ''), $comment);
        if ($errors) {
            $_SESSION[$sessionKey] = ['file' => $name, 'css' => $css, 'comment' => $comment, 'errors' => $errors];
            cms_admin_back($self(['file' => $name]), null, 'The stylesheet was not saved. Fix the problems listed below and save again.');
        }
        cms_admin_back($self(['file' => $name]), 'Saved ' . $name . '. Visitors get the new version on their next page load.');
    }
    if ($action === 'restore') {
        $rev = CmsStylesheets::revision((int) ($_POST['revision_id'] ?? 0));
        if ($rev === null || $rev['file'] !== $name) {
            cms_admin_back($self(['file' => $name]), null, 'That revision was not found.');
        }
        $errors = CmsStylesheets::save($name, (string) $rev['css'], '', 'Restored revision #' . (int) $rev['id']);
        if ($errors) {
            cms_admin_back($self(['file' => $name, 'compare' => (int) $rev['id']]), null, implode(' ', $errors));
        }
        cms_admin_back($self(['file' => $name]), 'Restored revision #' . (int) $rev['id'] . '. The version it replaced is kept in the history.');
    }
    cms_admin_back($self(['file' => $name]));
}

if ($name !== '' && isset($_GET['download'])) {
    $revId = (int) $_GET['download'];
    $rev = $revId > 0 ? CmsStylesheets::revision($revId) : null;
    if ($revId > 0 && ($rev === null || $rev['file'] !== $name)) {
        cms_admin_back($self(['file' => $name]), null, 'That revision was not found.');
    }
    $body = $rev !== null ? (string) $rev['css'] : (string) CmsStylesheets::read($name);
    $downloadName = $rev !== null ? preg_replace('/\.css$/', '', $name) . '-rev' . $revId . '.css' : $name;
    header('Content-Type: text/css; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}

$files = CmsStylesheets::all();
$csrf = Auth::csrfToken();
$draft = $_SESSION[$sessionKey] ?? null;
unset($_SESSION[$sessionKey]);
if (!is_array($draft) || ($draft['file'] ?? '') !== $name) {
    $draft = null;
}

$current = $name !== '' ? (string) CmsStylesheets::read($name) : '';
$info = null;
foreach ($files as $f) {
    if ($f['name'] === $name) {
        $info = $f;
    }
}
$revisions = $name !== '' ? CmsStylesheets::revisions($name) : [];
$compare = null;
$compareRows = [];
if ($name !== '' && isset($_GET['compare'])) {
    $compare = CmsStylesheets::revision((int) $_GET['compare']);
    if ($compare === null || $compare['file'] !== $name) {
        cms_admin_back($self(['file' => $name]), null, 'That revision was not found.');
    }
    $diff = CmsDiff::lines(explode("\n", (string) $compare['css']), explode("\n", $current));
    $compareRows = CmsDiff::sideBySide($diff, 3);
}

$pageTitle = $name !== '' ? 'Stylesheet: ' . $name : 'Stylesheets';
$activeNav = 'cms-stylesheets';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= $name !== '' ? 'Stylesheet: ' . e($name) : 'Stylesheets' ?></h1>
  <div class="admin-header-actions">
    <?php if ($name !== ''): ?>
      <a class="admin-btn admin-btn-secondary" href="<?= e($info['url'] ?? '') ?>" target="_blank" rel="noopener">View file</a>
      <a class="admin-btn admin-btn-secondary" href="<?= e($self(['file' => $name, 'download' => 0])) ?>">Download</a>
      <a class="admin-btn admin-btn-secondary" href="<?= e($self()) ?>">All stylesheets</a>
    <?php else: ?>
      <a class="admin-btn admin-btn-secondary" href="/admin/cms-site-config#assets">Global CSS &amp; shared styles</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($name === ''): ?>
<div class="admin-card">
  <p class="admin-field-hint">The stylesheets in the <code>/css</code> folder. Edit the site's own files or <a href="#new">add a new one</a>. Every save is validated, keeps a revision you can compare or restore, and is picked up by visitors immediately. For a few small overrides, the <a href="/admin/cms-site-config#assets">Global CSS</a> box in Site configuration is safer because a code deployment never overwrites it.</p>
  <div class="cms-table-scroll">
    <table class="admin-table">
      <thead><tr><th>File</th><th>Loaded on</th><th>Size</th><th>Last changed</th><th>Revisions</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td><a href="<?= e($self(['file' => $f['name']])) ?>"><strong><?= e($f['name']) ?></strong></a>
            <?php if ($f['added']): ?><span class="admin-badge admin-badge-info">Added here</span><?php endif; ?>
            <?php if (!$f['writable']): ?><span class="admin-badge cms-badge-error">Not writable</span><?php endif; ?></td>
          <td><?php if (!$f['added']): ?><span class="admin-field-hint">Linked by the site's code</span>
            <?php elseif ($f['load']): ?><span class="admin-badge admin-badge-success">Every page</span>
            <?php else: ?><span class="admin-badge admin-badge-muted">Not loaded</span><?php endif; ?></td>
          <td><?= e(number_format($f['bytes'] / 1024, 1)) ?> KB</td>
          <td><?= e(cms_admin_date(gmdate('Y-m-d H:i:s', $f['modified']))) ?></td>
          <td><?= count(CmsStylesheets::revisions($f['name'], 500)) ?></td>
          <td><a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e($self(['file' => $f['name']])) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $newDraft = $_SESSION['cms_stylesheet_new'] ?? ['new_name' => '', 'load' => true]; unset($_SESSION['cms_stylesheet_new']); ?>
<form method="post" action="/admin/cms-stylesheets" class="admin-card" id="new">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="create">
  <h2 class="admin-card-title">New stylesheet</h2>
  <div class="admin-fields-2col">
    <div class="admin-field">
      <label for="new-name">File name</label>
      <input type="text" id="new-name" name="new_name" maxlength="64" required pattern="[A-Za-z0-9 _\-]+(\.css)?" value="<?= e((string) $newDraft['new_name']) ?>" placeholder="e.g. blog-extras">
      <p class="admin-field-hint">Saved as <code>/css/<em>name</em>.css</code>. Letters, numbers, - and _.</p>
    </div>
    <div class="admin-field">
      <label>Loading</label>
      <label class="admin-checkbox-label"><input type="checkbox" name="load" value="1" <?= !empty($newDraft['load']) ? 'checked' : '' ?>> <span>Load on every page (hand-written and CMS pages)</span></label>
      <p class="admin-field-hint">It is added after the site's main stylesheets, so its rules can override them. Leave unticked to link it yourself, e.g. in a block or page.</p>
    </div>
  </div>
  <div class="cms-form-actions">
    <button type="submit" class="admin-btn">Create and edit</button>
  </div>
</form>

<?php elseif ($compare !== null): ?>
<div class="admin-card">
  <div class="cms-card-head">
    <h2 class="admin-card-title">Revision #<?= (int) $compare['id'] ?> compared with the current file</h2>
  </div>
  <p class="admin-field-hint"><?= e((string) $compare['comment']) ?> · <?= e((string) ($compare['user_name'] ?: 'System')) ?> · <?= e(cms_admin_date((string) $compare['created_at'])) ?></p>
  <?php if ($compareRows === []): ?>
    <p>This revision is identical to the current file.</p>
  <?php else: ?>
    <div class="cms-table-scroll">
      <table class="cms-diff-table">
        <thead><tr><th>Revision #<?= (int) $compare['id'] ?></th><th>Current file</th></tr></thead>
        <tbody>
        <?php foreach ($compareRows as [$left, $right, $kind]): ?>
          <?php if ($kind === 'gap'): ?>
            <tr class="cms-diff-row--gap"><td colspan="2">⋯ <?= e((string) $left) ?></td></tr>
          <?php else: ?>
            <tr class="cms-diff-row--<?= $kind ?>">
              <td class="<?= $kind === 'change' && $left !== null ? 'cms-diff-del' : '' ?>"><?= $left !== null ? e($left) : '' ?></td>
              <td class="<?= $kind === 'change' && $right !== null ? 'cms-diff-add' : '' ?>"><?= $right !== null ? e($right) : '' ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <form method="post" action="/admin/cms-stylesheets" class="cms-form-actions">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="restore">
    <input type="hidden" name="file" value="<?= e($name) ?>">
    <input type="hidden" name="revision_id" value="<?= (int) $compare['id'] ?>">
    <?php if ($compareRows !== []): ?><button type="submit" class="admin-btn" data-confirm="Replace <?= e($name) ?> with revision #<?= (int) $compare['id'] ?>? The current version is kept in the history.">Restore this revision</button><?php endif; ?>
    <a class="admin-btn admin-btn-secondary" href="<?= e($self(['file' => $name, 'download' => (int) $compare['id']])) ?>">Download this revision</a>
    <a class="admin-btn admin-btn-secondary" href="<?= e($self(['file' => $name])) ?>">Back to editor</a>
  </form>
</div>

<?php else: ?>
<?php if ($info !== null && !$info['writable']): ?>
  <div class="admin-alert admin-alert-error">The server cannot write to <code><?= e($info['url']) ?></code>. Make the file and the <code>css</code> folder writable by PHP before saving.</div>
<?php endif; ?>
<?php if ($draft !== null && !empty($draft['errors'])): ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Not saved — please fix</h2>
    <ul class="cms-warning-list">
      <?php foreach ((array) $draft['errors'] as $err): ?>
        <?php $line = preg_match('/^Line (\d+):/', (string) $err, $lm) ? (int) $lm[1] : 0; ?>
        <li><?php if ($line > 0): ?><a href="#" data-goto-line="<?= $line ?>"><?= e((string) $err) ?></a><?php else: ?><?= e((string) $err) ?><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="admin-field-hint cms-mt">Your edits are still in the editor below.</p>
  </div>
<?php endif; ?>

<form method="post" action="/admin/cms-stylesheets" class="admin-card" data-code-form>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="file" value="<?= e($name) ?>">
  <input type="hidden" name="hash" value="<?= e(CmsStylesheets::hash($current)) ?>">
  <div class="cms-card-head">
    <h2 class="admin-card-title"><?= e($name) ?></h2>
    <span class="admin-field-hint" data-code-status><?= e(number_format(strlen($current) / 1024, 1)) ?> KB · <?= substr_count($current, "\n") + 1 ?> lines</span>
  </div>
  <div class="cms-code-field">
    <textarea name="css" class="cms-css-editor" spellcheck="false" autocomplete="off" autocapitalize="off" data-code-editor<?= $draft !== null ? ' data-code-dirty' : '' ?> aria-label="CSS for <?= e($name) ?>"><?= e($draft['css'] ?? $current) ?></textarea>
  </div>
  <div class="cms-form-actions">
    <div class="admin-field cms-css-comment">
      <label for="css-comment">What did you change? (optional)</label>
      <input type="text" id="css-comment" name="comment" maxlength="255" value="<?= e((string) ($draft['comment'] ?? '')) ?>" placeholder="e.g. Larger footer links">
    </div>
    <button type="submit" class="admin-btn" data-busy="Saving…">Save stylesheet</button>
  </div>
  <p class="admin-field-hint">Tab indents, Ctrl/⌘ + S saves. Saving checks for unbalanced braces, unclosed comments or quotes, and unsafe code. A code deployment that uploads the <code>css</code> folder replaces edits made here, so copy important changes into the codebase too (use Download).</p>
</form>

<div class="admin-card">
  <h2 class="admin-card-title">History</h2>
  <?php if ($revisions === []): ?>
    <p class="admin-field-hint">No revisions yet. The original file is saved as the first revision when you first save a change.</p>
  <?php else: ?>
    <div class="cms-table-scroll">
      <table class="admin-table">
        <thead><tr><th>#</th><th>When</th><th>Who</th><th>Note</th><th>Size</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($revisions as $i => $r): ?>
          <tr>
            <td><?= (int) $r['id'] ?></td>
            <td><?= e(cms_admin_date($r['created_at'])) ?></td>
            <td><?= e($r['user_name'] ?: 'System') ?></td>
            <td><?= e($r['comment']) ?><?php if ($r['hash'] === CmsStylesheets::hash($current)): ?> <span class="admin-badge admin-badge-success">Current</span><?php endif; ?></td>
            <td><?= e(number_format($r['bytes'] / 1024, 1)) ?> KB</td>
            <td>
              <a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e($self(['file' => $name, 'compare' => $r['id']])) ?>">Compare</a>
              <a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e($self(['file' => $name, 'download' => $r['id']])) ?>">Download</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($info !== null && $info['added']): ?>
<div class="admin-card">
  <h2 class="admin-card-title">File settings</h2>
  <form method="post" action="/admin/cms-stylesheets" class="cms-form-actions">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="loading">
    <input type="hidden" name="file" value="<?= e($name) ?>">
    <label class="admin-checkbox-label"><input type="checkbox" name="load" value="1" <?= $info['load'] ? 'checked' : '' ?>> <span>Load on every page, after the main stylesheets</span></label>
    <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Update loading</button>
  </form>
  <?php if (!$info['load']): ?>
    <p class="admin-field-hint">To use it on one page only, link it there, e.g. in a block: <code>&lt;link rel="stylesheet" href="<?= e($info['url']) ?>"&gt;</code></p>
  <?php endif; ?>
  <form method="post" action="/admin/cms-stylesheets" class="cms-mt">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="file" value="<?= e($name) ?>">
    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" data-confirm="Delete <?= e($info['url']) ?> and its history? Pages stop loading it immediately.">Delete this stylesheet</button>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
