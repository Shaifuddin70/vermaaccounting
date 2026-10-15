<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.publish');

$sessionKey = 'cms_navigation_old';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    if (($_POST['action'] ?? '') === 'reset') {
        CmsNavigation::restoreDefaults();
        cms_admin_back('/admin/cms-navigation', 'The default menu is back.');
    }
    $items = json_decode((string) ($_POST['menu_json'] ?? ''), true);
    if (!is_array($items)) {
        cms_admin_back('/admin/cms-navigation', null, 'The menu could not be read. Please try again.');
    }
    $settings = [
        'show_phone' => !empty($_POST['show_phone']),
        'button_label' => (string) ($_POST['button_label'] ?? ''),
        'button_url' => (string) ($_POST['button_url'] ?? ''),
    ];
    $errors = CmsNavigation::save($items, $settings);
    if ($errors) {
        $_SESSION[$sessionKey] = ['items' => $items] + $settings;
        cms_admin_back('/admin/cms-navigation', null, implode(' ', $errors));
    }
    cms_admin_back('/admin/cms-navigation', 'Menu saved. Every page shows the new header now.');
}

$config = CmsNavigation::get();
$old = $_SESSION[$sessionKey] ?? null;
unset($_SESSION[$sessionKey]);
if (is_array($old)) {
    $config = array_merge($config, $old);
}

$repo = new CmsContentRepository();
$pageOptions = [];
foreach (CmsPath::legacyPages() as $path) {
    $pageOptions[$path] = ucwords(str_replace('-', ' ', trim($path, '/'))) ?: 'Home';
}
$cmsPages = [];
foreach (['page', 'article'] as $type) {
    foreach ($repo->search(['type' => $type, 'sort' => 'title', 'dir' => 'asc'], 500) as $row) {
        $cmsPages[(string) $row['path']] = $row;
        $pageOptions[(string) $row['path']] = CmsCityDefaults::label($row);
    }
}
$notLive = $repo->notLivePaths(array_keys($pageOptions));
$csrf = Auth::csrfToken();

$pageNote = static function (string $url) use ($notLive): string {
    $path = CmsNavigation::pathOf($url);
    if ($path !== '' && isset($notLive[$path])) {
        return '<span class="admin-badge cms-nav-note" title="Shown in the menu once the page is published">Not published</span>';
    }
    return '';
};

$childRow = static function (array $c) use ($pageNote): void { ?>
  <li class="cms-nav-node cms-nav-child<?= empty($c['visible']) ? ' is-off' : '' ?>" data-nav-node="child">
    <div class="cms-nav-fields">
      <input type="text" data-f="label" value="<?= e((string) ($c['label'] ?? '')) ?>" placeholder="Link text" maxlength="60" aria-label="Link text">
      <input type="text" data-f="url" value="<?= e((string) ($c['url'] ?? '')) ?>" placeholder="/page-url" list="cms-nav-paths" maxlength="500" aria-label="URL">    </div>
    <div class="cms-nav-tools">
      <?= $pageNote((string) ($c['url'] ?? '')) ?>
      <label class="admin-checkbox-label"><input type="checkbox" data-f="visible" <?= !empty($c['visible']) || !isset($c['visible']) ? 'checked' : '' ?>> <span>Show</span></label>
      <label class="admin-checkbox-label"><input type="checkbox" data-f="new_tab" <?= !empty($c['new_tab']) ? 'checked' : '' ?>> <span>New tab</span></label>
      <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-move="-1" aria-label="Move up" title="Move up">↑</button>
      <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-move="1" aria-label="Move down" title="Move down">↓</button>
      <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-remove aria-label="Remove link" title="Remove">✕</button>
    </div>
  </li>
<?php };

$itemRow = static function (array $item, bool $collapsed = true) use ($pageNote, $childRow): void {
    $children = (array) ($item['children'] ?? []); ?>
  <li class="cms-nav-node cms-nav-item<?= empty($item['visible']) && isset($item['visible']) ? ' is-off' : '' ?><?= $collapsed ? ' is-collapsed' : '' ?>" data-nav-node="item">
    <input type="hidden" data-f="key" value="<?= e((string) ($item['key'] ?? '')) ?>">
    <div class="cms-nav-head">
      <div class="cms-nav-fields">
        <input type="text" data-f="label" value="<?= e((string) ($item['label'] ?? '')) ?>" placeholder="Menu label" maxlength="60" aria-label="Menu label" class="cms-nav-label">
        <input type="text" data-f="url" value="<?= e((string) ($item['url'] ?? '')) ?>" placeholder="/page-url (optional for a dropdown)" list="cms-nav-paths" maxlength="500" aria-label="URL">
      </div>
      <div class="cms-nav-tools">
        <span class="admin-badge cms-nav-kind" data-nav-kind></span>
        <?= $pageNote((string) ($item['url'] ?? '')) ?>
        <label class="admin-checkbox-label"><input type="checkbox" data-f="visible" <?= !empty($item['visible']) || !isset($item['visible']) ? 'checked' : '' ?>> <span>Show</span></label>
        <label class="admin-checkbox-label"><input type="checkbox" data-f="new_tab" <?= !empty($item['new_tab']) ? 'checked' : '' ?>> <span>New tab</span></label>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm cms-nav-toggle" data-nav-toggle aria-expanded="<?= $collapsed ? 'false' : 'true' ?>"></button>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-move="-1" aria-label="Move up" title="Move up">↑</button>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-move="1" aria-label="Move down" title="Move down">↓</button>
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-remove aria-label="Remove menu item" title="Remove">✕</button>
      </div>
    </div>
    <div class="cms-nav-dropdown">
      <ol class="cms-nav-children" data-nav-children>
        <?php foreach ($children as $c) { $childRow((array) $c); } ?>
      </ol>
      <div class="cms-nav-dropdown-foot">
        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" data-nav-add-child>+ Add a link under this item</button>
        <label class="cms-nav-inline">Fill with
          <select data-f="source">
            <option value="">Nothing automatic</option>
            <?php foreach (CmsNavigation::SOURCES as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= ($item['source'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="cms-nav-inline" data-nav-all>"View all" text
          <input type="text" data-f="all_label" value="<?= e((string) ($item['all_label'] ?? '')) ?>" placeholder="View all" maxlength="60">
        </label>
      </div>
    </div>
  </li>
<?php };

$pageTitle = 'Navigation';
$activeNav = 'cms-navigation';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Navigation</h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/" target="_blank" rel="noopener">View site</a>
  </div>
</div>

<div class="admin-card">
  <p class="admin-field-hint">This is the menu at the top of every page. A top-level item with links under it opens a dropdown; its own URL (if any) becomes the "View all" link at the bottom of the dropdown. Untick <strong>Show</strong> to hide an item without deleting it. Links to CMS pages that are not published yet stay out of the menu until the page goes live. You can also add a page to the menu from the page editor (Basics tab → Header menu).</p>
</div>

<form method="post" action="/admin/cms-navigation" data-nav-editor>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="menu_json" value="">

  <div class="admin-card">
    <div class="cms-card-head">
      <h2 class="admin-card-title">Menu items</h2>
      <label class="cms-nav-inline">Add a page
        <select data-nav-add-page>
          <option value="">Choose a page…</option>
          <optgroup label="Website pages">
            <?php foreach ($pageOptions as $path => $label): if (isset($cmsPages[$path])) { continue; } ?>
              <option value="<?= e($path) ?>" data-label="<?= e($label) ?>"><?= e($label) ?> (<?= e($path) ?>)</option>
            <?php endforeach; ?>
          </optgroup>
          <optgroup label="CMS pages">
            <?php foreach ($cmsPages as $path => $row): ?>
              <option value="<?= e($path) ?>" data-label="<?= e($pageOptions[$path]) ?>"><?= e($pageOptions[$path]) ?> (<?= e($path) ?>)<?= isset($notLive[$path]) ? ' · not published' : '' ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </label>
    </div>
    <ol class="cms-nav-list" data-nav-list>
      <?php foreach ((array) $config['items'] as $item) { $itemRow((array) $item); } ?>
    </ol>
    <button type="button" class="admin-btn admin-btn-secondary cms-mt" data-nav-add>+ Add a menu item</button>
    <datalist id="cms-nav-paths">
      <?php foreach ($pageOptions as $path => $label): ?>
        <option value="<?= e($path) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </datalist>
    <template id="tpl-nav-item"><?php $itemRow(['visible' => true], false); ?></template>
    <template id="tpl-nav-child"><?php $childRow(['visible' => true]); ?></template>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title">Header buttons</h2>
    <div class="admin-fields-2col">
      <div class="admin-field admin-field--full">
        <label class="admin-checkbox-label"><input type="checkbox" name="show_phone" value="1" <?= !empty($config['show_phone']) ? 'checked' : '' ?>> <span>Show the company phone number (<?= e(CmsCompany::phone()) ?>)</span></label>
        <p class="admin-field-hint">The number itself is set in <a href="/admin/cms-site-config#tab-company">Site configuration → Company details</a>.</p>
      </div>
      <div class="admin-field">
        <label for="nav-button-label">Button text</label>
        <input type="text" id="nav-button-label" name="button_label" value="<?= e((string) $config['button_label']) ?>" maxlength="40" placeholder="Leave empty to hide">
      </div>
      <div class="admin-field">
        <label for="nav-button-url">Button link</label>
        <input type="text" id="nav-button-url" name="button_url" value="<?= e((string) $config['button_url']) ?>" maxlength="500" list="cms-nav-paths" placeholder="/contact">
      </div>
    </div>
  </div>

  <div class="cms-form-actions">
    <button type="submit" class="admin-btn" data-busy="Saving…">Save menu</button>
    <span class="admin-field-hint" data-nav-dirty hidden>Unsaved changes</span>
  </div>
</form>

<?php if (CmsNavigation::isCustomised()): ?>
  <form method="post" action="/admin/cms-navigation" class="admin-card cms-mt">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="reset">
    <p class="admin-field-hint">Start over with the original menu (Home, Our Services, Submit Document, Resources, About Us).</p>
    <button type="submit" class="admin-btn admin-btn-secondary" data-confirm="Replace your menu with the original one? Your changes to the menu will be lost.">Restore the default menu</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
