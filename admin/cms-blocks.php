<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.code');

$sessionKey = 'cms_block_draft';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$self = static fn (array $q = []): string => '/admin/cms-blocks' . ($q ? '?' . http_build_query($q) : '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $input = array_intersect_key($_POST, array_flip(['name', 'token', 'description', 'code', 'status']));
        $result = CmsBlocks::save($id ?: null, $input, trim((string) ($_POST['comment'] ?? '')));
        if ($result['errors']) {
            $_SESSION[$sessionKey] = ['id' => $id, 'input' => $input, 'comment' => (string) ($_POST['comment'] ?? '')];
            cms_admin_back($id ? $self(['id' => $id]) : $self(['new' => 1]), null, implode(' ', $result['errors']));
        }
        cms_admin_back($self(['id' => $result['id']]), 'Block saved. Every page using it shows the new version now.');
    }
    if ($action === 'restore') {
        $rev = CmsBlocks::revision((int) ($_POST['revision_id'] ?? 0));
        $block = $id ? CmsBlocks::find($id) : null;
        if ($rev === null || $block === null || (int) $rev['block_id'] !== $id) {
            cms_admin_back($self(['id' => $id]), null, 'That revision was not found.');
        }
        $result = CmsBlocks::save($id, ['name' => $rev['name'], 'code' => $rev['code'], 'status' => $rev['status'], 'description' => $block['description']], 'Restored revision #' . (int) $rev['id']);
        if ($result['errors']) {
            cms_admin_back($self(['id' => $id]), null, implode(' ', $result['errors']));
        }
        cms_admin_back($self(['id' => $id]), 'Restored revision #' . (int) $rev['id'] . '.');
    }
    if ($action === 'delete') {
        $block = CmsBlocks::find($id);
        if ($block !== null && CmsBlocks::delete($id)) {
            cms_admin_back($self(), 'Deleted the block ' . CmsBlocks::tokenTag((string) $block['token']) . '. Pages that still contain the token show nothing in its place.');
        }
        cms_admin_back($self(), null, 'That block was not found.');
    }
    cms_admin_back($self());
}

$csrf = Auth::csrfToken();
$draft = $_SESSION[$sessionKey] ?? null;
unset($_SESSION[$sessionKey]);
if (!is_array($draft) || (int) ($draft['id'] ?? 0) !== $id) {
    $draft = null;
}

$isNew = $id === 0 && isset($_GET['new']);
$block = $id > 0 ? CmsBlocks::find($id) : null;
if ($id > 0 && $block === null) {
    cms_admin_back($self(), null, 'That block was not found.');
}
$form = $block ?? ['name' => '', 'token' => '', 'description' => '', 'code' => '', 'status' => 'active'];
if ($draft !== null) {
    $form = array_merge($form, array_map('strval', (array) $draft['input']));
    if ($block !== null) {
        $form['token'] = $block['token'];
    }
}

$compare = null;
$compareRows = [];
if ($block !== null && isset($_GET['compare'])) {
    $compare = CmsBlocks::revision((int) $_GET['compare']);
    if ($compare === null || (int) $compare['block_id'] !== $id) {
        cms_admin_back($self(['id' => $id]), null, 'That revision was not found.');
    }
    $compareRows = CmsDiff::sideBySide(CmsDiff::lines(explode("\n", (string) $compare['code']), explode("\n", (string) $block['code'])), 3);
}

$copyIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

$pageTitle = $block ? 'Block: ' . $block['name'] : ($isNew ? 'New block' : 'Blocks');
$activeNav = 'cms-blocks';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
  <div class="admin-header-actions">
    <?php if ($block || $isNew): ?>
      <a class="admin-btn admin-btn-secondary" href="<?= e($self()) ?>">All blocks</a>
    <?php else: ?>
      <a class="admin-btn" href="<?= e($self(['new' => 1])) ?>">New block</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$block && !$isNew): ?>
<?php $blocks = CmsBlocks::all(); ?>
<div class="admin-card">
  <p class="admin-field-hint">A block is a piece of HTML (with any CSS or JavaScript) saved once and reused anywhere. Type its token, such as <code>{{block:footer-cta}}</code>, in a page, article or city page body, in the site header or footer, or inside another block, and the page shows the block's code in its place. Editing the block updates every page that uses it.</p>
  <?php if ($blocks === []): ?>
    <p class="cms-mt">No blocks yet. <a href="<?= e($self(['new' => 1])) ?>">Create the first one</a>.</p>
  <?php else: ?>
    <div class="cms-table-scroll cms-mt">
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Token</th><th>Status</th><th>Used on</th><th>Last changed</th></tr></thead>
        <tbody>
        <?php foreach ($blocks as $b): ?>
          <?php $tag = CmsBlocks::tokenTag((string) $b['token']); ?>
          <tr>
            <td><a href="<?= e($self(['id' => (int) $b['id']])) ?>"><strong><?= e((string) $b['name']) ?></strong></a>
              <?php if ($b['description'] !== ''): ?><div class="admin-field-hint"><?= e((string) $b['description']) ?></div><?php endif; ?></td>
            <td><code><?= e($tag) ?></code> <button type="button" class="admin-copy-btn" data-copy="<?= e($tag) ?>" title="Copy token" aria-label="Copy token <?= e($tag) ?>"><?= $copyIcon ?></button></td>
            <td><?= $b['status'] === 'active' ? '<span class="admin-badge admin-badge-success">Active</span>' : '<span class="admin-badge admin-badge-muted">Disabled</span>' ?></td>
            <td><?= count(CmsBlocks::usage((string) $b['token'])) ?></td>
            <td><?= e(cms_admin_date((string) $b['updated_at'])) ?><?= $b['updated_by_name'] !== '' ? ' · ' . e((string) $b['updated_by_name']) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php elseif ($compare !== null): ?>
<div class="admin-card">
  <h2 class="admin-card-title">Revision #<?= (int) $compare['id'] ?> compared with the current block</h2>
  <p class="admin-field-hint"><?= e((string) $compare['comment']) ?> · <?= e((string) ($compare['user_name'] ?: 'System')) ?> · <?= e(cms_admin_date((string) $compare['created_at'])) ?></p>
  <?php if ($compareRows === [] && $compare['name'] === $block['name'] && $compare['status'] === $block['status']): ?>
    <p>This revision is identical to the current block.</p>
  <?php else: ?>
    <?php if ($compare['name'] !== $block['name'] || $compare['status'] !== $block['status']): ?>
      <p>Name and status in this revision: <strong><?= e((string) $compare['name']) ?></strong>, <?= e((string) $compare['status']) ?>.</p>
    <?php endif; ?>
    <?php if ($compareRows !== []): ?>
    <div class="cms-table-scroll">
      <table class="cms-diff-table">
        <thead><tr><th>Revision #<?= (int) $compare['id'] ?></th><th>Current</th></tr></thead>
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
  <?php endif; ?>
  <form method="post" action="/admin/cms-blocks" class="cms-form-actions">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="restore">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="revision_id" value="<?= (int) $compare['id'] ?>">
    <button type="submit" class="admin-btn" data-confirm="Restore revision #<?= (int) $compare['id'] ?>? Every page using this block changes immediately. The current version is kept in the history.">Restore this revision</button>
    <a class="admin-btn admin-btn-secondary" href="<?= e($self(['id' => $id])) ?>">Back to block</a>
  </form>
</div>

<?php else: ?>
<?php $usage = $block ? CmsBlocks::usage((string) $block['token']) : []; ?>
<form method="post" action="/admin/cms-blocks" class="admin-card" data-code-form>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="admin-fields-2col">
    <div class="admin-field">
      <label for="b-name">Name <span class="cms-req">*</span></label>
      <input type="text" id="b-name" name="name" maxlength="191" required value="<?= e((string) $form['name']) ?>" placeholder="e.g. Book a consultation banner" data-token-source="#b-token">
    </div>
    <div class="admin-field">
      <label for="b-token">Token</label>
      <?php if ($block): ?>
        <div><code><?= e(CmsBlocks::tokenTag((string) $block['token'])) ?></code>
          <button type="button" class="admin-copy-btn" data-copy="<?= e(CmsBlocks::tokenTag((string) $block['token'])) ?>" title="Copy token" aria-label="Copy token"><?= $copyIcon ?></button></div>
        <p class="admin-field-hint">The token can't be changed once created, so pages that use it keep working.</p>
      <?php else: ?>
        <input type="text" id="b-token" name="token" maxlength="64" pattern="[a-z0-9][a-z0-9_\-]*" value="<?= e((string) $form['token']) ?>" placeholder="book-consultation">
        <p class="admin-field-hint">Lowercase letters, numbers, - and _. Filled in from the name if left empty. Used as <code>{{block:<span data-token-preview><?= e((string) ($form['token'] ?: 'name')) ?></span>}}</code>.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="admin-fields-2col">
    <div class="admin-field">
      <label for="b-description">Description (admin only)</label>
      <input type="text" id="b-description" name="description" maxlength="500" value="<?= e((string) $form['description']) ?>" placeholder="Where it is used and what it shows">
    </div>
    <div class="admin-field">
      <label for="b-status">Status</label>
      <select id="b-status" name="status">
        <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Active — rendered wherever the token appears</option>
        <option value="disabled" <?= $form['status'] === 'disabled' ? 'selected' : '' ?>>Disabled — the token renders nothing</option>
      </select>
    </div>
  </div>
  <div class="admin-field cms-code-field">
    <label for="b-code">Code</label>
    <textarea id="b-code" name="code" class="cms-css-editor cms-block-editor" spellcheck="false" autocomplete="off" autocapitalize="off" data-code-editor<?= $draft !== null ? ' data-code-dirty' : '' ?>><?= e((string) $form['code']) ?></textarea>
    <p class="admin-field-hint">HTML, plus <code>&lt;style&gt;</code> and <code>&lt;script&gt;</code> if needed. It is output exactly as written (PHP is not run). Placeholders such as <code>{{site_name}}</code>, <code>{{year}}</code> and other <code>{{block:…}}</code> tokens work inside it.</p>
  </div>
  <div class="cms-form-actions">
    <div class="admin-field cms-css-comment">
      <label for="b-comment">What did you change? (optional)</label>
      <input type="text" id="b-comment" name="comment" maxlength="255" value="<?= e((string) ($draft['comment'] ?? '')) ?>">
    </div>
    <button type="submit" class="admin-btn" data-busy="Saving…"><?= $block ? 'Save block' : 'Create block' ?></button>
  </div>
</form>

<?php if ($block): ?>
<div class="admin-card">
  <h2 class="admin-card-title">How to use it</h2>
  <ul class="cms-stat-list">
    <li>In a page, article or city page body (visual editor or HTML), the site header or footer, or another block: <code><?= e(CmsBlocks::tokenTag((string) $block['token'])) ?></code>. On its own line, it replaces the whole paragraph.</li>
    <li>In a hand-written PHP page: <code>&lt;?= cms_block('<?= e((string) $block['token']) ?>') ?&gt;</code> <button type="button" class="admin-copy-btn" data-copy="<?= e("<?= cms_block('" . $block['token'] . "') ?>") ?>" title="Copy PHP" aria-label="Copy PHP snippet"><?= $copyIcon ?></button></li>
  </ul>
</div>

<div class="admin-card">
  <h2 class="admin-card-title">Used on (<?= count($usage) ?>)</h2>
  <?php if ($usage === []): ?>
    <p class="admin-field-hint">No CMS content, header, footer or block uses this token yet. Hand-written PHP pages are not scanned.</p>
  <?php else: ?>
    <ul class="cms-stat-list">
      <?php foreach ($usage as $u): ?>
        <li><span class="admin-badge admin-badge-muted"><?= e($u['kind']) ?></span> <a href="<?= e($u['url']) ?>"><?= e($u['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2 class="admin-card-title">History</h2>
  <div class="cms-table-scroll">
    <table class="admin-table">
      <thead><tr><th>#</th><th>When</th><th>Who</th><th>Note</th><th></th></tr></thead>
      <tbody>
      <?php foreach (CmsBlocks::revisions($id) as $i => $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e(cms_admin_date((string) $r['created_at'])) ?></td>
          <td><?= e((string) ($r['user_name'] ?: 'System')) ?></td>
          <td><?= e((string) $r['comment']) ?><?= $i === 0 ? ' <span class="admin-badge admin-badge-success">Current</span>' : '' ?></td>
          <td><?php if ($i > 0): ?><a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e($self(['id' => $id, 'compare' => (int) $r['id']])) ?>">Compare</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" action="/admin/cms-blocks" class="admin-card">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= $id ?>">
  <h2 class="admin-card-title">Delete block</h2>
  <p class="admin-field-hint"><?= $usage !== [] ? 'This block is used in ' . count($usage) . ' place' . (count($usage) === 1 ? '' : 's') . ' above; they will show nothing where the token is. To hide it temporarily, set the status to Disabled instead.' : 'Deleting removes the block and its history.' ?></p>
  <button type="submit" class="admin-btn admin-btn-danger" data-confirm="Delete <?= e(CmsBlocks::tokenTag((string) $block['token'])) ?> and its history?<?= $usage !== [] ? ' It is used in ' . count($usage) . ' place(s).' : '' ?>">Delete block</button>
</form>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
