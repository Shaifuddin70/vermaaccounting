<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$repo = new CmsContentRepository();
$id = (int) ($_GET['id'] ?? 0);
$content = $id > 0 ? $repo->find($id) : null;
if ($id > 0 && $content === null) {
    cms_admin_back('/admin/cms-content', null, 'That content no longer exists.');
}
$type = (string) ($content['type'] ?? ($_GET['type'] ?? 'page'));
if (!isset(CmsContentRepository::TYPES[$type])) {
    $type = 'page';
}
$caps = cms_admin_caps();
$seo = $content ? $repo->seo($id) : CmsContentRepository::defaultSeo();
$city = $content && $type === 'city' ? $repo->city($id) : [];
$terms = $content ? $repo->contentTerms($id) : [];
$form = cms_admin_form_values($content, $seo, $city, $terms, $type);
if (!$content && isset($_GET['folder'])) {
    $form['folder_id'] = (int) $_GET['folder'];
}
if (!$content && $type !== 'page') {
    foreach ((new CmsFolderRepository())->all() as $f) {
        if ($f['default_type'] === $type && (int) $form['folder_id'] === 0) {
            $form['folder_id'] = (int) $f['id'];
            break;
        }
    }
}

$autosaveNotice = null;
$old = $_SESSION['cms_form_old'] ?? null;
unset($_SESSION['cms_form_old']);
if (is_array($old) && (int) ($old['id'] ?? 0) === $id) {
    $form = cms_admin_form_overlay($form, $old);
} elseif ($content && isset($_GET['autosave'])) {
    $rev = $repo->revision((int) $_GET['autosave']);
    if ($rev && (int) $rev['content_id'] === $id && !empty($rev['snapshot']['form'])) {
        $form = cms_admin_form_overlay($form, $rev['snapshot']['form']);
        $autosaveNotice = 'Showing the autosaved version from ' . cms_admin_date($rev['created_at']) . '. Save to keep it, or reload the page to discard it.';
    }
}
$latestAutosave = null;
if ($content && !isset($_GET['autosave'])) {
    foreach ($repo->revisions($id, 5) as $r) {
        if ((int) $r['is_autosave'] === 1 && $r['created_at'] > $content['updated_at']) {
            $latestAutosave = $r;
        }
        break;
    }
}

$folders = new CmsFolderRepository();
$folder = $form['folder_id'] ? $folders->find((int) $form['folder_id']) : null;
$isLive = $content && CmsContentRepository::isLive($content);
$previewContent = $content ? array_merge($content, ['status' => 'published', 'expire_at' => null]) : null;
$effective = $previewContent ? CmsSeo::effective($previewContent, $seo, $folder, []) : null;
$duplicates = $content ? CmsSeo::duplicates($id, (string) ($effective['title'] ?? ''), (string) ($effective['description'] ?? '')) : [];
$cityWarnings = $content && $type === 'city' ? CmsContentService::cityQualityWarnings($id) : [];
$revisions = $content ? $repo->revisions($id, 30) : [];
$urlHistory = $content ? $repo->urlHistory($id) : [];
$categories = $repo->terms('category');
$media = new CmsMediaRepository();
$featuredMedia = $form['featured_media_id'] ? $media->find((int) $form['featured_media_id']) : null;
$ogMedia = $form['og_image_id'] ? $media->find((int) $form['og_image_id']) : null;
$users = (new UserRepository())->all();
$config = CmsSiteConfig::get();
$base = CmsSiteConfig::baseUrl();
$canEditBody = $caps['edit'] && !($form['body_mode'] === 'raw' && !$caps['code']);
$readOnly = !$caps['edit'] || ($content && in_array($content['status'], ['published', 'scheduled'], true) && !$caps['publish']);
$homeId = (int) $config['home_content_id'];
$csrf = Auth::csrfToken();
$e = 'e';

$isCity = $type === 'city';
$cityDefault = array_fill_keys(array_keys(CmsCityDefaults::FIELDS), '');
$cityDefaultsJson = '';
if ($isCity) {
    $formCity = [
        'city' => $form['city_city'], 'region' => $form['city_region'], 'country' => $form['city_country'],
        'primary_service' => $form['city_primary_service'], 'phone' => $form['city_phone'], 'email' => $form['city_email'],
    ];
    foreach (array_keys($cityDefault) as $field) {
        $cityDefault[$field] = CmsCityDefaults::fill($field, $formCity);
    }
    $cityDefaultsJson = (string) json_encode(CmsCityDefaults::get() + ['site_name' => (string) CmsSiteConfig::value('site_name')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$fieldPlaceholder = static fn (string $field, string $fallback = ''): string => $isCity && $cityDefault[$field] !== '' ? $cityDefault[$field] : $fallback;

$pageTitle = $content ? 'Edit: ' . CmsCityDefaults::label($content) : 'New ' . strtolower(CmsContentRepository::TYPES[$type]);
$activeNav = cms_admin_type_nav($type);
$loadCmsAdmin = true;
$loadCmsEditor = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <div>
    <a href="/admin/cms-content?type=<?= e($type) ?>" class="cms-back-link">← <?= e(cms_admin_type_plural($type)) ?></a>
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($content): ?>
      <div class="cms-header-meta">
        <?= cms_status_badge((string) $content['status']) ?>
        <?php if ($isLive): ?>
          <a href="<?= e($content['path']) ?>" target="_blank" rel="noopener"><?= e($base . $content['path']) ?></a>
        <?php else: ?>
          <code><?= e($content['path']) ?></code>
        <?php endif; ?>
        <?php if ($id === $homeId): ?><span class="admin-badge admin-badge-info">Homepage</span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($content): ?>
    <div class="admin-header-actions">
      <?php if ($caps['edit']): ?>
        <form method="post" action="/admin/cms-action" class="cms-inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button type="submit" name="action" value="preview" class="admin-btn admin-btn-secondary">Preview link</button>
        </form>
      <?php endif; ?>
      <?php if ($isLive): ?>
        <a href="<?= e($content['path']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-secondary">View live</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($autosaveNotice): ?>
  <div class="admin-alert admin-alert-info"><?= e($autosaveNotice) ?></div>
<?php elseif ($latestAutosave): ?>
  <div class="admin-alert admin-alert-info">
    There are unsaved changes from <?= e(cms_admin_date($latestAutosave['created_at'])) ?>.
    <a href="/admin/cms-edit?id=<?= $id ?>&amp;autosave=<?= (int) $latestAutosave['id'] ?>">Restore the autosaved version</a>
  </div>
<?php endif; ?>
<?php if ($content && $content['preview_token']): ?>
  <div class="admin-alert admin-alert-info cms-preview-link-alert">
    Private preview link (not indexed, anyone with the link can view):
    <input type="text" readonly value="<?= e($base . '/cms/preview?token=' . $content['preview_token']) ?>" class="cms-copy-input" onclick="this.select()">
    <a href="/cms/preview?token=<?= e($content['preview_token']) ?>" target="_blank" rel="noopener">Open</a>
    <form method="post" action="/admin/cms-action" class="cms-inline-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="preview_revoke" class="admin-btn admin-btn-sm admin-btn-secondary">Revoke link</button>
    </form>
  </div>
<?php endif; ?>
<?php if ($readOnly): ?>
  <div class="admin-alert admin-alert-info">
    <?= $caps['edit'] ? 'This content is live or scheduled. You need publishing permission to change it.' : 'You can view this content but not change it.' ?>
  </div>
<?php endif; ?>

<form method="post" action="/admin/cms-save" id="cms-editor-form" class="cms-editor"
      data-id="<?= $id ?>" data-base-url="<?= e($base) ?>" data-title-suffix="<?= e((string) $config['title_suffix']) ?>"
      data-csrf="<?= e($csrf) ?>" data-readonly="<?= $readOnly ? '1' : '0' ?>" data-type="<?= e($type) ?>"
      data-body-mode="<?= e((string) $form['body_mode']) ?>" data-can-code="<?= $caps['code'] ? '1' : '0' ?>"
      <?= $isCity ? 'data-city-defaults="' . e($cityDefaultsJson) . '"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="type" value="<?= e($type) ?>">

  <div class="cms-editor-layout">
    <div class="cms-editor-main">
      <nav class="admin-tabs cms-tabs" role="tablist" aria-label="Editor sections">
        <?php
        $tabs = ['basic' => 'Basic', 'body' => 'Body', 'seo' => 'SEO', 'social' => 'Social', 'media' => 'Media'];
        if ($type === 'city') {
            $tabs['location'] = 'Location';
        }
        $tabs['schema'] = 'Structured data';
        $tabs['faq'] = 'FAQs';
        $tabs['publishing'] = 'Publishing';
        if ($content) {
            $tabs['revisions'] = 'Revisions';
        }
        ?>
        <?php foreach ($tabs as $key => $label): ?>
          <a href="#tab-<?= $key ?>" class="admin-tab cms-tab" role="tab" data-tab="<?= $key ?>" aria-controls="tab-<?= $key ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- Basic -->
      <section class="admin-card cms-panel" id="tab-basic" role="tabpanel" data-panel="basic">
        <h2 class="admin-card-title">Basic information</h2>
        <div class="admin-fields-2col">
          <div class="admin-field admin-field--full">
            <label for="f-title">Title<?= $isCity ? '' : ' <span class="cms-req">*</span>' ?></label>
            <input type="text" id="f-title" name="title" value="<?= e((string) $form['title']) ?>" <?= $isCity ? '' : 'required' ?> maxlength="500" data-counter="title" placeholder="<?= e($fieldPlaceholder('title')) ?>" data-city-default="title">
            <?php if ($isCity): ?>
              <p class="admin-field-hint">Only the city name (Location tab) is required. Any field you leave empty here, in SEO or in the body shows the <?= $caps['publish'] ? '<a href="/admin/cms-city-defaults" target="_blank" rel="noopener">city page default</a>' : 'city page default' ?> for this city; the greyed text is what it will show. Type your own text to replace it on this page.</p>
            <?php else: ?>
              <p class="admin-field-hint">Used for the search title unless you set an SEO title.<?= $type === 'article' ? '' : ' The page shows only what you put in the body, so add the main heading (Heading 1) there.' ?></p>
            <?php endif; ?>
          </div>
          <?php if ($type === 'page'): ?>
            <input type="hidden" name="h1" value="<?= e((string) $form['h1']) ?>">
          <?php else: ?>
          <div class="admin-field">
            <label for="f-h1">Page heading (H1)</label>
            <input type="text" id="f-h1" name="h1" value="<?= e((string) $form['h1']) ?>" maxlength="500" placeholder="<?= e($fieldPlaceholder('h1', 'Same as title')) ?>" data-city-default="h1">
            <?php if ($isCity): ?><p class="admin-field-hint">Shown by the default body as <code>{h1}</code>.</p><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="admin-field">
            <label for="f-internal">Internal name</label>
            <input type="text" id="f-internal" name="internal_name" value="<?= e((string) $form['internal_name']) ?>" maxlength="255" placeholder="Only shown in admin">
          </div>
          <div class="admin-field">
            <label for="f-folder"><?= $type === 'page' ? 'Parent page or folder' : 'Folder' ?></label>
            <select id="f-folder" name="folder_id" data-url-part="folder">
              <?= cms_admin_folder_options((int) $form['folder_id'] ?: null, true, $content ? ((int) ($folders->forLanding($id)['id'] ?? 0) ?: null) : null) ?>
            </select>
            <?php if ($type === 'page'): ?><p class="admin-field-hint">Pick a page to make this one of its sub-pages.</p><?php endif; ?>
          </div>
          <?php if ($type === 'page'): ?>
            <div class="admin-field">
              <label for="f-template">Page layout</label>
              <select id="f-template" name="template">
                <option value="full-width" <?= $form['template'] === 'full-width' || $form['template'] === '' ? 'selected' : '' ?>>Body only</option>
                <option value="article-index" <?= $form['template'] === 'article-index' ? 'selected' : '' ?>>Show the latest blog articles</option>
                <option value="service-areas" <?= $form['template'] === 'service-areas' ? 'selected' : '' ?>>Show the city directory</option>
              </select>
              <p class="admin-field-hint">City directory is used by the Service Areas page at <code>/service-area</code>.</p>
            </div>
          <?php endif; ?>

          <div class="admin-field admin-field--full cms-url-field">
            <label>URL</label>
            <?php $urlLocked = $content !== null; ?>
            <div class="cms-url-preview"><span class="cms-url-base"><?= e($base) ?></span><strong id="cms-url-path"><?= e($form['path'] !== '' ? $form['path'] : '/') ?></strong></div>
            <?php if ($urlLocked): ?>
              <label class="admin-checkbox-label"><input type="checkbox" name="change_url" value="1" id="f-change-url" <?= !empty($form['change_url']) ? 'checked' : '' ?>> <span>Change the URL</span></label>
            <?php else: ?>
              <input type="hidden" name="change_url" value="1">
            <?php endif; ?>
            <div class="cms-url-edit" id="cms-url-edit" <?= $urlLocked && empty($form['change_url']) ? 'hidden' : '' ?>>
              <div class="cms-url-modes">
                <label class="admin-checkbox-label"><input type="radio" name="url_mode" value="auto" <?= ($form['url_mode'] ?? 'auto') !== 'custom' ? 'checked' : '' ?>> <span>Folder + slug</span></label>
                <label class="admin-checkbox-label"><input type="radio" name="url_mode" value="custom" <?= ($form['url_mode'] ?? '') === 'custom' ? 'checked' : '' ?>> <span>Custom path</span></label>
              </div>
              <div class="admin-fields-2col">
                <div class="admin-field" data-url-mode="auto">
                  <label for="f-slug">Slug</label>
                  <input type="text" id="f-slug" name="slug" value="<?= e((string) $form['slug']) ?>" maxlength="191" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="generated-from-title">
                  <p class="admin-field-hint">Lowercase letters, numbers and hyphens.</p>
                </div>
                <div class="admin-field" data-url-mode="custom">
                  <label for="f-custom-path">Custom path</label>
                  <input type="text" id="f-custom-path" name="custom_path" value="<?= e((string) ($form['custom_path'] ?? $form['path'])) ?>" placeholder="/services/ottawa/bookkeeping">
                </div>
              </div>
              <?php if ($urlLocked && $isLive || ($content && !empty($content['published_at']))): ?>
                <label class="admin-checkbox-label"><input type="checkbox" name="create_redirect" value="1" <?= !isset($form['create_redirect']) || $form['create_redirect'] ? 'checked' : '' ?>> <span>Add a 301 redirect from the old URL (recommended)</span></label>
              <?php endif; ?>
              <p class="admin-field-hint" id="cms-url-warning" hidden></p>
            </div>
            <?php if ($urlHistory): ?>
              <details class="cms-url-history">
                <summary>Previous URLs (<?= count($urlHistory) ?>)</summary>
                <ul>
                  <?php foreach ($urlHistory as $h): ?>
                    <li><code><?= e($h['old_path']) ?></code> → <code><?= e($h['new_path']) ?></code> <span class="admin-table-sub"><?= e(cms_admin_date($h['changed_at'])) ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </div>

          <?php if ($type === 'page'): ?>
            <?php
            $ownFolder = $content ? $folders->forLanding($id) : null;
            $hasSubpages = is_array($old) && (int) ($old['id'] ?? 0) === $id && isset($old['has_subpages'])
                ? (string) $old['has_subpages'] === '1'
                : $ownFolder !== null;
            ?>
            <div class="cms-structure admin-field--full">
              <?php if ($caps['publish']): ?>
                <?php
                $navParents = CmsNavigation::parents($content ? (string) $content['path'] : null);
                if (is_array($old) && (int) ($old['id'] ?? 0) === $id && isset($old['nav_placement'])) {
                    $navPlacement = (string) $old['nav_placement'];
                } elseif ($content) {
                    $navPlacement = CmsNavigation::placement((string) $content['path']);
                } else {
                    // A new sub-page starts under its parent page's menu item, when the parent is in the menu.
                    $parentLanding = $folder && !empty($folder['landing_content_id']) ? $repo->find((int) $folder['landing_content_id']) : null;
                    $navPlacement = $parentLanding ? CmsNavigation::topKeyFor((string) $parentLanding['path']) : '';
                }
                ?>
                <div class="admin-field">
                  <label for="f-nav">Show in the header menu</label>
                  <select id="f-nav" name="nav_placement">
                    <option value="">No, keep it out of the menu</option>
                    <option value="top" <?= $navPlacement === 'top' ? 'selected' : '' ?>>Yes, as a top-level item</option>
                    <?php foreach ($navParents as $navKey => $navLabel): ?>
                      <option value="<?= e($navKey) ?>" <?= $navPlacement === $navKey ? 'selected' : '' ?>>Yes, under "<?= e($navLabel) ?>"</option>
                    <?php endforeach; ?>
                  </select>
                  <p class="admin-field-hint">Appears once the page is published. Labels and order: <a href="/admin/cms-navigation">Navigation</a>.</p>
                </div>
              <?php endif; ?>
              <div class="admin-field">
                <span class="cms-label">Sub-pages</span>
                <input type="hidden" name="has_subpages" value="0">
                <label class="admin-checkbox-label"><input type="checkbox" name="has_subpages" value="1" <?= $hasSubpages ? 'checked' : '' ?>> <span>This page has sub-pages (works like a folder)</span></label>
                <p class="admin-field-hint">Sub-pages get URLs under this page, e.g. <code><?= e(($content && $content['path'] !== '/' ? $content['path'] : '/this-page') . '/sub-page') ?></code>.<?php if ($ownFolder && $caps['edit']): ?> <a href="/admin/cms-edit?type=page&amp;folder=<?= (int) $ownFolder['id'] ?>">+ Add a sub-page</a><?php endif; ?></p>
              </div>
            </div>
          <?php endif; ?>

          <div class="admin-field admin-field--full">
            <label for="f-excerpt"><?= $type === 'article' ? 'Excerpt' : 'Summary' ?></label>
            <textarea id="f-excerpt" name="excerpt" rows="2" maxlength="1000" placeholder="<?= e($fieldPlaceholder('excerpt')) ?>" data-city-default="excerpt"><?= e((string) $form['excerpt']) ?></textarea>
            <p class="admin-field-hint"><?= $type === 'article' ? 'Shown under the heading and on listing cards.' : ($isCity ? 'Shown by the default body as <code>{summary}</code>.' : 'Not shown on the page.') ?> Used as the meta description if you leave that empty.</p>
          </div>

          <?php if ($type === 'article'): ?>
            <div class="admin-field">
              <label for="f-author">Author</label>
              <select id="f-author" name="author_user_id">
                <option value="">— Use the name below —</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int) $u['id'] ?>" <?= (int) $form['author_user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) $u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field">
              <label for="f-author-name">Author name</label>
              <input type="text" id="f-author-name" name="author_name" value="<?= e((string) $form['author_name']) ?>" maxlength="191" placeholder="Rishabh Verma">
            </div>
            <div class="admin-field admin-field--full">
              <span class="cms-label">Categories</span>
              <div class="cms-checkbox-grid">
                <?php foreach ($categories as $cat): ?>
                  <label class="admin-checkbox-label"><input type="checkbox" name="term_ids[]" value="<?= (int) $cat['id'] ?>" <?= in_array((int) $cat['id'], $form['term_ids'], true) ? 'checked' : '' ?>> <span><?= e((string) $cat['name']) ?></span></label>
                <?php endforeach; ?>
              </div>
              <input type="text" name="new_categories" value="<?= e((string) ($form['new_categories'] ?? '')) ?>" placeholder="Add new categories, separated by commas" class="cms-mt">
            </div>
            <div class="admin-field admin-field--full">
              <label for="f-tags">Tags</label>
              <input type="text" id="f-tags" name="tags" value="<?= e((string) $form['tags']) ?>" placeholder="payroll, small business, HST">
            </div>
          <?php endif; ?>

          <div class="admin-field">
            <label for="f-language">Language</label>
            <input type="text" id="f-language" name="language" value="<?= e((string) $form['language']) ?>" maxlength="16" pattern="[a-z]{2}(-[A-Z]{2})?">
          </div>
          <div class="admin-field admin-field--full">
            <label for="f-notes">Internal notes</label>
            <textarea id="f-notes" name="internal_notes" rows="2"><?= e((string) $form['internal_notes']) ?></textarea>
          </div>
        </div>
      </section>

      <!-- Body -->
      <section class="admin-card cms-panel" id="tab-body" role="tabpanel" data-panel="body">
        <div class="cms-body-toolbar">
          <h2 class="admin-card-title">Body</h2>
          <div class="cms-body-modes">
            <?php if ($form['body_mode'] !== 'raw'): ?>
              <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" id="cms-toggle-html" <?= $canEditBody ? '' : 'disabled' ?>>Edit HTML</button>
            <?php endif; ?>
            <?php if ($caps['code']): ?>
              <label class="admin-checkbox-label" title="Keeps scripts, styles and any markup exactly as typed. Only for trusted code.">
                <input type="checkbox" name="body_mode" value="raw" id="f-body-raw" <?= $form['body_mode'] === 'raw' ? 'checked' : '' ?>>
                <span>Unfiltered HTML</span>
              </label>
            <?php elseif ($form['body_mode'] === 'raw'): ?>
              <input type="hidden" name="body_mode" value="raw">
            <?php endif; ?>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-insert>Insert image</button>
            <?php $activeBlocks = array_filter(CmsBlocks::all(), static fn ($b) => $b['status'] === 'active'); ?>
            <?php if ($activeBlocks !== []): ?>
              <select class="cms-block-insert" data-block-insert aria-label="Insert a reusable block">
                <option value="">Insert block…</option>
                <?php foreach ($activeBlocks as $b): ?>
                  <option value="<?= e(CmsBlocks::tokenTag((string) $b['token'])) ?>"><?= e((string) $b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!$canEditBody): ?>
          <div class="admin-alert admin-alert-info">This body uses unfiltered HTML. Only users with site code permission can edit it.</div>
          <div class="cms-body-readonly"><pre><?= e((string) $form['body_html']) ?></pre></div>
        <?php else: ?>
          <?php if ($isCity): ?>
            <?php $bodyUsesDefault = CmsCityDefaults::bodyIsEmpty((string) $form['body_html']); ?>
            <div class="admin-alert admin-alert-info cms-default-note" id="cms-city-default-note" <?= $bodyUsesDefault ? '' : 'hidden style="display:none"' ?>>
              <span>This page’s body field is empty, so visitors see the shared city default<?= $cityDefault['body_html'] === '' ? ' (no default body is set yet)' : '' ?>. Title, summary and location fields do not replace the body — type in this editor (or start from the default) and save.</span>
              <?php if (CmsCityDefaults::get()['body_html'] !== ''): ?>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-city-default-body>Start from the default content</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <textarea id="f-body" name="body_html" rows="24" class="cms-body-textarea"><?= e((string) $form['body_html']) ?></textarea>
          <p class="admin-field-hint">
            Use Heading 2 for main sections and Heading 3 for sub-sections; the page title is the only H1.
            Scripts, inline event handlers and unknown embeds are removed when saved<?= $caps['code'] ? ' unless “Unfiltered HTML” is ticked' : '' ?>.
          </p>
          <div class="cms-body-stats" id="cms-body-stats" aria-live="polite"></div>
        <?php endif; ?>
      </section>

      <!-- SEO -->
      <section class="admin-card cms-panel" id="tab-seo" role="tabpanel" data-panel="seo">
        <h2 class="admin-card-title">Search engine settings</h2>
        <?php if (!$caps['seo']): ?>
          <div class="admin-alert admin-alert-info">You need technical SEO permission to change these settings. Defaults from the folder and site are used.</div>
        <?php endif; ?>
        <fieldset <?= $caps['seo'] ? '' : 'disabled' ?>>
          <div class="cms-serp" aria-label="Search result preview">
            <div class="cms-serp-url" id="serp-url"><?= e($base . ($form['path'] ?: '/')) ?></div>
            <div class="cms-serp-title" id="serp-title"><?= e((string) ($effective['title'] ?? $form['title'])) ?></div>
            <div class="cms-serp-desc" id="serp-desc"><?= e((string) ($effective['description'] ?? '')) ?></div>
          </div>
          <div class="admin-fields-2col">
            <div class="admin-field admin-field--full">
              <label for="f-seo-title">SEO title</label>
              <input type="text" id="f-seo-title" name="seo_title" value="<?= e((string) $form['seo_title']) ?>" maxlength="500" placeholder="<?= e($fieldPlaceholder('seo_title', (string) (($form['title'] ?: $cityDefault['title']) . $config['title_suffix']))) ?>" data-counter="seo_title" data-max="<?= CmsSeo::TITLE_MAX ?>" data-city-default="seo_title">
              <p class="admin-field-hint"><span data-count-for="seo_title"></span> Leave empty to use “title<?= e((string) $config['title_suffix']) ?>”.</p>
            </div>
            <div class="admin-field admin-field--full">
              <label for="f-desc">Meta description</label>
              <textarea id="f-desc" name="meta_description" rows="3" data-counter="meta_description" data-min="<?= CmsSeo::DESC_MIN ?>" data-max="<?= CmsSeo::DESC_MAX ?>" placeholder="<?= e($fieldPlaceholder('meta_description')) ?>" data-city-default="meta_description"><?= e((string) $form['meta_description']) ?></textarea>
              <p class="admin-field-hint"><span data-count-for="meta_description"></span> Aim for <?= CmsSeo::DESC_MIN ?>–<?= CmsSeo::DESC_MAX ?> characters that describe this page specifically.</p>
            </div>
            <div class="admin-field">
              <label for="f-robots-index">Indexing</label>
              <select id="f-robots-index" name="robots_index">
                <option value="inherit" <?= $form['robots_index'] === 'inherit' ? 'selected' : '' ?>>Inherit (<?= e($folder && !empty($folder['seo_defaults']['robots_index']) ? $folder['seo_defaults']['robots_index'] . ' from folder' : $config['robots_index'] . ' from site') ?>)</option>
                <option value="index" <?= $form['robots_index'] === 'index' ? 'selected' : '' ?>>index</option>
                <option value="noindex" <?= $form['robots_index'] === 'noindex' ? 'selected' : '' ?>>noindex</option>
              </select>
            </div>
            <div class="admin-field">
              <label for="f-robots-follow">Links</label>
              <select id="f-robots-follow" name="robots_follow">
                <option value="inherit" <?= $form['robots_follow'] === 'inherit' ? 'selected' : '' ?>>Inherit (<?= e($folder && !empty($folder['seo_defaults']['robots_follow']) ? $folder['seo_defaults']['robots_follow'] . ' from folder' : $config['robots_follow'] . ' from site') ?>)</option>
                <option value="follow" <?= $form['robots_follow'] === 'follow' ? 'selected' : '' ?>>follow</option>
                <option value="nofollow" <?= $form['robots_follow'] === 'nofollow' ? 'selected' : '' ?>>nofollow</option>
              </select>
            </div>
            <div class="admin-field">
              <label for="f-canonical-mode">Canonical URL</label>
              <select id="f-canonical-mode" name="canonical_mode">
                <option value="auto" <?= $form['canonical_mode'] !== 'custom' ? 'selected' : '' ?>>This page’s own URL (recommended)</option>
                <option value="custom" <?= $form['canonical_mode'] === 'custom' ? 'selected' : '' ?>>Another URL</option>
              </select>
            </div>
            <div class="admin-field" data-show-when="canonical_mode=custom">
              <label for="f-canonical-url">Canonical points to</label>
              <input type="url" id="f-canonical-url" name="canonical_url" value="<?= e((string) $form['canonical_url']) ?>" placeholder="<?= e($base) ?>/original-page">
              <p class="admin-field-hint">Only when this page duplicates another. It will be left out of the sitemap.</p>
            </div>
            <div class="admin-field admin-field--full">
              <label class="admin-checkbox-label"><input type="checkbox" name="sitemap_include" value="1" <?= (int) $form['sitemap_include'] === 1 ? 'checked' : '' ?>> <span>Include in the XML sitemap (when indexable and self-canonical)</span></label>
            </div>
          </div>
          <details class="cms-advanced">
            <summary>Advanced robots directives</summary>
            <div class="admin-fields-2col">
              <div class="admin-field">
                <label for="f-max-snippet">max-snippet</label>
                <input type="number" id="f-max-snippet" name="max_snippet" value="<?= e((string) $form['max_snippet']) ?>" min="-1" placeholder="<?= e((string) $config['max_snippet']) ?> (site default)">
              </div>
              <div class="admin-field">
                <label for="f-max-image">max-image-preview</label>
                <select id="f-max-image" name="max_image_preview">
                  <option value="">Default (<?= e((string) $config['max_image_preview']) ?>)</option>
                  <?php foreach (['none', 'standard', 'large'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $form['max_image_preview'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="admin-field">
                <label for="f-max-video">max-video-preview</label>
                <input type="number" id="f-max-video" name="max_video_preview" value="<?= e((string) $form['max_video_preview']) ?>" min="-1" placeholder="<?= e((string) $config['max_video_preview']) ?> (site default)">
              </div>
              <div class="admin-field">
                <span class="cms-label">Other directives</span>
                <?php foreach (CmsSeo::EXTRA_DIRECTIVES as $d): ?>
                  <label class="admin-checkbox-label"><input type="checkbox" name="robots_extra[]" value="<?= e($d) ?>" <?= in_array($d, (array) $form['robots_extra'], true) ? 'checked' : '' ?>> <span><?= e($d) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          </details>
        </fieldset>
        <?php if ($effective): ?>
          <div class="cms-effective">
            <h3>Rendered when published</h3>
            <dl>
              <dt>Title</dt><dd><?= e($effective['title']) ?></dd>
              <dt>Description</dt><dd><?= e($effective['description']) ?> <span class="admin-table-sub">(<?= e($effective['description_source']) ?>)</span></dd>
              <dt>Robots</dt><dd><code><?= e($effective['robots']) ?></code> <span class="admin-table-sub">(<?= e($effective['index_source']) ?>)</span></dd>
              <dt>Canonical</dt><dd><code><?= e($effective['canonical']) ?></code></dd>
              <dt>Sitemap</dt><dd><?= $effective['sitemap'] ? 'Included' : 'Not included' ?></dd>
            </dl>
          </div>
        <?php endif; ?>
      </section>

      <!-- Social -->
      <section class="admin-card cms-panel" id="tab-social" role="tabpanel" data-panel="social">
        <h2 class="admin-card-title">Social sharing</h2>
        <fieldset <?= $caps['seo'] ? '' : 'disabled' ?>>
          <div class="cms-social-card">
            <div class="cms-social-image" id="social-image" style="<?= $effective && $effective['social']['image'] !== '' ? 'background-image:url(' . e($effective['social']['image']) . ')' : '' ?>"></div>
            <div class="cms-social-body">
              <div class="cms-social-domain"><?= e((string) parse_url($base, PHP_URL_HOST)) ?></div>
              <div class="cms-social-title" id="social-title"><?= e((string) ($effective['social']['title'] ?? $form['title'])) ?></div>
              <div class="cms-social-desc" id="social-desc"><?= e((string) ($effective['social']['description'] ?? '')) ?></div>
            </div>
          </div>
          <div class="admin-fields-2col">
            <div class="admin-field admin-field--full">
              <label for="f-og-title">Social title</label>
              <input type="text" id="f-og-title" name="og_title" value="<?= e((string) $form['og_title']) ?>" maxlength="500" placeholder="Defaults to the SEO title">
            </div>
            <div class="admin-field admin-field--full">
              <label for="f-og-desc">Social description</label>
              <textarea id="f-og-desc" name="og_description" rows="2" placeholder="Defaults to the meta description"><?= e((string) $form['og_description']) ?></textarea>
            </div>
            <div class="admin-field admin-field--full">
              <span class="cms-label">Social image</span>
              <div class="cms-media-field" data-media-field="og">
                <input type="hidden" name="og_image_id" value="<?= (int) $form['og_image_id'] ?>" data-media-id>
                <div class="cms-media-thumb" data-media-thumb><?php if ($ogMedia): ?><img src="<?= e($ogMedia['url']) ?>" alt=""><?php endif; ?></div>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-pick>Choose from library</button>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-clear>Remove</button>
              </div>
              <input type="text" name="og_image_url" value="<?= e((string) $form['og_image_url']) ?>" placeholder="…or an https:// image URL" class="cms-mt">
              <p class="admin-field-hint">1200×630 works best. Falls back to the featured image, then the site default.</p>
            </div>
            <div class="admin-field">
              <label for="f-og-alt">Image alt text</label>
              <input type="text" id="f-og-alt" name="og_image_alt" value="<?= e((string) $form['og_image_alt']) ?>" maxlength="500">
            </div>
            <div class="admin-field">
              <label for="f-og-type">Open Graph type</label>
              <select id="f-og-type" name="og_type">
                <option value="">Automatic (<?= $type === 'article' ? 'article' : 'website' ?>)</option>
                <?php foreach (['website', 'article', 'profile'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $form['og_type'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-field">
              <label for="f-twitter">X / Twitter card</label>
              <select id="f-twitter" name="twitter_card">
                <option value="">Site default (<?= e((string) $config['twitter_card']) ?>)</option>
                <option value="summary" <?= $form['twitter_card'] === 'summary' ? 'selected' : '' ?>>summary</option>
                <option value="summary_large_image" <?= $form['twitter_card'] === 'summary_large_image' ? 'selected' : '' ?>>summary_large_image</option>
              </select>
            </div>
          </div>
        </fieldset>
      </section>

      <!-- Media -->
      <section class="admin-card cms-panel" id="tab-media" role="tabpanel" data-panel="media">
        <h2 class="admin-card-title">Featured image</h2>
        <div class="cms-media-field" data-media-field="featured">
          <input type="hidden" name="featured_media_id" value="<?= (int) $form['featured_media_id'] ?>" data-media-id>
          <div class="cms-media-thumb cms-media-thumb--lg" data-media-thumb><?php if ($featuredMedia): ?><img src="<?= e($featuredMedia['url']) ?>" alt=""><?php elseif ($form['featured_image_url'] !== ''): ?><img src="<?= e((string) $form['featured_image_url']) ?>" alt=""><?php endif; ?></div>
          <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-pick>Choose from library</button>
          <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-media-clear>Remove</button>
        </div>
        <div class="admin-fields-2col cms-mt">
          <div class="admin-field">
            <label for="f-featured-alt">Alt text</label>
            <input type="text" id="f-featured-alt" name="featured_alt" value="<?= e((string) $form['featured_alt']) ?>" maxlength="500" placeholder="<?= e((string) ($featuredMedia['alt'] ?? 'Describe the image')) ?>">
            <p class="admin-field-hint">Describe what the image shows. Leave empty to use the media library alt text.</p>
          </div>
          <div class="admin-field">
            <label for="f-featured-url">External image URL</label>
            <input type="text" id="f-featured-url" name="featured_image_url" value="<?= e((string) $form['featured_image_url']) ?>" placeholder="Only if not using the library">
          </div>
        </div>
        <p class="admin-field-hint">Library images get responsive sizes, width/height and lazy loading automatically. <a href="/admin/cms-media" target="_blank" rel="noopener">Open the media library</a></p>
      </section>

      <?php if ($type === 'city'): ?>
      <!-- Location -->
      <section class="admin-card cms-panel" id="tab-location" role="tabpanel" data-panel="location">
        <h2 class="admin-card-title">Location details</h2>
        <?php foreach ($cityWarnings as $w): ?>
          <div class="admin-alert <?= str_starts_with($w, 'Uses the default') ? 'admin-alert-info' : 'admin-alert-error' ?>"><?= e($w) ?></div>
        <?php endforeach; ?>
        <div class="admin-fields-2col">
          <div class="admin-field"><label for="f-city">City <span class="cms-req">*</span></label><input type="text" id="f-city" name="city_city" value="<?= e((string) $form['city_city']) ?>" maxlength="191"></div>
          <div class="admin-field"><label for="f-region">Province / region</label><input type="text" id="f-region" name="city_region" value="<?= e((string) $form['city_region']) ?>" maxlength="191"></div>
          <div class="admin-field"><label for="f-country">Country</label><input type="text" id="f-country" name="city_country" value="<?= e((string) $form['city_country']) ?>" maxlength="100"></div>
          <div class="admin-field"><label for="f-service">Primary service</label><input type="text" id="f-service" name="city_primary_service" value="<?= e((string) $form['city_primary_service']) ?>" maxlength="191" placeholder="<?= e((string) CmsCityDefaults::get()['service']) ?>"></div>
          <div class="admin-field"><label for="f-phone">Phone</label><input type="text" id="f-phone" name="city_phone" value="<?= e((string) $form['city_phone']) ?>" maxlength="64"></div>
          <div class="admin-field"><label for="f-email">Email</label><input type="email" id="f-email" name="city_email" value="<?= e((string) $form['city_email']) ?>" maxlength="191"></div>
          <div class="admin-field admin-field--full"><label for="f-address">Address (only a real address you serve from)</label><textarea id="f-address" name="city_address" rows="2"><?= e((string) $form['city_address']) ?></textarea></div>
          <div class="admin-field admin-field--full">
            <span class="cms-label">Opening hours</span>
            <div class="cms-repeat" data-repeat="hours">
              <?php $hoursRows = $form['city_hours'] ?: [['days' => '', 'opens' => '', 'closes' => '']]; ?>
              <?php foreach ($hoursRows as $row): ?>
                <div class="cms-repeat-row">
                  <input type="text" name="city_hours_days[]" value="<?= e((string) $row['days']) ?>" placeholder="Mon–Fri" aria-label="Days">
                  <input type="time" name="city_hours_opens[]" value="<?= e((string) $row['opens']) ?>" aria-label="Opens">
                  <input type="time" name="city_hours_closes[]" value="<?= e((string) $row['closes']) ?>" aria-label="Closes">
                  <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-remove aria-label="Remove row">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-add="hours">+ Add hours</button>
          </div>
          <div class="admin-field admin-field--full"><label for="f-map-url">Directions link</label><input type="url" id="f-map-url" name="city_map_url" value="<?= e((string) $form['city_map_url']) ?>" placeholder="https://maps.google.com/..."></div>
          <div class="admin-field admin-field--full">
            <label class="admin-checkbox-label"><input type="checkbox" name="city_local_business" value="1" <?= (int) $form['city_local_business'] === 1 ? 'checked' : '' ?>> <span>Output LocalBusiness structured data (only if you have a real location or service area here)</span></label>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Structured data -->
      <section class="admin-card cms-panel" id="tab-schema" role="tabpanel" data-panel="schema">
        <h2 class="admin-card-title">Structured data (JSON-LD)</h2>
        <fieldset <?= $caps['seo'] ? '' : 'disabled' ?>>
          <p class="admin-field-hint">Generated automatically from this page’s content. Untick any you don’t want on this page.</p>
          <div class="cms-checkbox-grid">
            <?php foreach (CmsStructuredData::TYPES as $key => $label): ?>
              <label class="admin-checkbox-label"><input type="checkbox" class="cms-schema-toggle" value="<?= e($key) ?>" <?= in_array($key, $form['schema_disabled'], true) ? '' : 'checked' ?>> <span><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div id="cms-schema-disabled">
            <?php foreach ($form['schema_disabled'] as $d): ?><input type="hidden" name="schema_disabled[]" value="<?= e($d) ?>"><?php endforeach; ?>
          </div>
          <div class="admin-field cms-mt">
            <label for="f-schema-custom">Additional JSON-LD</label>
            <textarea id="f-schema-custom" name="schema_custom" rows="8" class="cms-code" spellcheck="false" placeholder='{"@context": "https://schema.org", "@type": "HowTo", ...}'><?= e((string) $form['schema_custom']) ?></textarea>
            <p class="admin-field-hint">One object or a list of objects. Must describe content visible on the page. Reviews and ratings are not allowed.</p>
          </div>
        </fieldset>
        <?php if ($content && $previewContent): ?>
          <?php $ctx = CmsRenderer::context($previewContent); ?>
          <details class="cms-advanced" open>
            <summary>Generated JSON-LD (<?= count($ctx['schema']) ?> blocks)</summary>
            <pre class="cms-code-preview"><?= e(json_encode($ctx['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
          </details>
        <?php endif; ?>
      </section>

      <!-- FAQs -->
      <section class="admin-card cms-panel" id="tab-faq" role="tabpanel" data-panel="faq">
        <h2 class="admin-card-title">Frequently asked questions</h2>
        <p class="admin-field-hint">Shown at the bottom of the page and added as FAQPage structured data.<?php if ($isCity): ?> Leave empty to use the <a href="/admin/cms-city-defaults">default city FAQs</a>.<?php endif; ?></p>
        <div class="cms-repeat" data-repeat="faq">
          <?php $faqRows = $form['faqs'] ?: [['question' => '', 'answer' => '']]; ?>
          <?php foreach ($faqRows as $faq): ?>
            <div class="cms-repeat-row cms-repeat-row--stack">
              <input type="text" name="faq_q[]" value="<?= e((string) $faq['question']) ?>" placeholder="Question" aria-label="Question">
              <textarea name="faq_a[]" rows="2" placeholder="Answer" aria-label="Answer"><?= e((string) $faq['answer']) ?></textarea>
              <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-remove>Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-add="faq">+ Add question</button>
      </section>

      <!-- Publishing -->
      <section class="admin-card cms-panel" id="tab-publishing" role="tabpanel" data-panel="publishing">
        <h2 class="admin-card-title">Publishing</h2>
        <div class="admin-fields-2col">
          <div class="admin-field">
            <label for="f-publish-at">Publish at (<?= e(app_timezone()->getName()) ?>)</label>
            <input type="datetime-local" id="f-publish-at" name="publish_at" value="<?= e((string) $form['publish_at']) ?>">
            <p class="admin-field-hint">Leave empty to publish immediately. A future date schedules the page.</p>
          </div>
          <div class="admin-field">
            <label for="f-expire-at">Unpublish at</label>
            <input type="datetime-local" id="f-expire-at" name="expire_at" value="<?= e((string) $form['expire_at']) ?>">
          </div>
          <div class="admin-field admin-field--full">
            <label for="f-rev-comment">Revision note</label>
            <input type="text" id="f-rev-comment" name="revision_comment" maxlength="255" placeholder="What changed? (optional)">
          </div>
          <?php if ($type === 'city' && str_contains((string) $form['internal_notes'], '[generated]')): ?>
            <div class="admin-field admin-field--full">
              <label class="admin-checkbox-label"><input type="checkbox" name="reviewed" value="1"> <span>I have reviewed this generated page and added genuinely local content.</span></label>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($content): ?>
          <dl class="cms-meta-list">
            <dt>Created</dt><dd><?= e(cms_admin_date($content['created_at'])) ?></dd>
            <dt>Last updated</dt><dd><?= e(cms_admin_date($content['updated_at'])) ?></dd>
            <dt>First published</dt><dd><?= e(cms_admin_date($content['published_at'])) ?></dd>
          </dl>
          <?php if ($caps['publish']): ?>
            <div class="cms-danger-zone">
              <?php if ($caps['code'] && $type === 'page' && $id !== $homeId && $isLive): ?>
                <button type="submit" form="cms-action-form" name="action" value="set_home" class="admin-btn admin-btn-sm admin-btn-secondary" data-confirm="Use this page as the website homepage?">Use as homepage</button>
              <?php endif; ?>
              <button type="submit" form="cms-action-form" name="action" value="delete" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Permanently delete “<?= e((string) $content['title']) ?>” and its revisions? Visitors will get a 404 unless you add a redirect. This cannot be undone.">Delete permanently</button>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <?php if ($content): ?>
      <!-- Revisions -->
      <section class="admin-card cms-panel" id="tab-revisions" role="tabpanel" data-panel="revisions">
        <h2 class="admin-card-title">Revisions</h2>
        <?php if ($revisions === []): ?>
          <p class="admin-field-hint">No revisions yet.</p>
        <?php else: ?>
          <table class="admin-table">
            <thead><tr><th>When</th><th>By</th><th>Note</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($revisions as $i => $rev): ?>
                <tr>
                  <td><?= e(cms_admin_date($rev['created_at'])) ?></td>
                  <td><?= e((string) ($rev['user_name'] ?: '—')) ?></td>
                  <td><?= (int) $rev['is_autosave'] === 1 ? '<span class="admin-badge admin-badge-muted">Autosave</span> ' : '' ?><?= e((string) $rev['comment']) ?></td>
                  <td class="admin-table-actions">
                    <?php if ((int) $rev['is_autosave'] === 1): ?>
                      <a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-edit?id=<?= $id ?>&amp;autosave=<?= (int) $rev['id'] ?>">Load</a>
                    <?php else: ?>
                      <a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-revision?id=<?= (int) $rev['id'] ?>">Compare</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>

    <aside class="cms-editor-side">
      <div class="admin-card cms-side-card">
        <h2 class="admin-card-title">Save</h2>
        <?php if (!$readOnly): ?>
          <div class="cms-save-buttons">
            <button type="submit" name="workflow_action" value="save" class="admin-btn admin-btn-secondary"><?= $content && in_array($content['status'], ['published', 'scheduled'], true) ? 'Save changes' : 'Save draft' ?></button>
            <?php if ($caps['publish']): ?>
              <button type="submit" name="workflow_action" value="publish" class="admin-btn"><?= $isLive ? 'Update live page' : 'Publish' ?></button>
            <?php elseif (!$content || in_array($content['status'], ['draft', 'unpublished'], true)): ?>
              <button type="submit" name="workflow_action" value="review" class="admin-btn">Submit for review</button>
            <?php endif; ?>
            <?php if ($caps['publish'] && $content && $isLive): ?>
              <button type="submit" name="workflow_action" value="unpublish" class="admin-btn admin-btn-secondary" data-confirm="Unpublish this page? Visitors will get a 404 unless you add a redirect.">Unpublish</button>
            <?php endif; ?>
          </div>
          <p class="admin-field-hint" id="cms-autosave-status" aria-live="polite"></p>
        <?php endif; ?>
      </div>

      <div class="admin-card cms-side-card">
        <h2 class="admin-card-title">SEO checks</h2>
        <ul class="cms-checks" id="cms-live-checks"></ul>
        <?php if ($effective): ?>
          <ul class="cms-checks">
            <?php foreach ($effective['warnings'] as $w): ?>
              <li class="cms-check cms-check--<?= e($w['level']) ?>"><?= e($w['text']) ?></li>
            <?php endforeach; ?>
            <?php foreach ($duplicates as $field => $rows): ?>
              <?php foreach ($rows as $d): ?>
                <li class="cms-check cms-check--warning">Same <?= $field === 'title' ? 'SEO title' : 'meta description' ?> as <a href="/admin/cms-edit?id=<?= (int) $d['id'] ?>"><?= e((string) $d['title']) ?></a></li>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</form>

<?php if ($content): ?>
<form method="post" action="/admin/cms-action" id="cms-action-form" hidden>
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="id" value="<?= $id ?>">
</form>
<?php endif; ?>

<template id="tpl-repeat-hours">
  <div class="cms-repeat-row">
    <input type="text" name="city_hours_days[]" placeholder="Mon–Fri" aria-label="Days">
    <input type="time" name="city_hours_opens[]" aria-label="Opens">
    <input type="time" name="city_hours_closes[]" aria-label="Closes">
    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-remove aria-label="Remove row">×</button>
  </div>
</template>
<template id="tpl-repeat-faq">
  <div class="cms-repeat-row cms-repeat-row--stack">
    <input type="text" name="faq_q[]" placeholder="Question" aria-label="Question">
    <textarea name="faq_a[]" rows="2" placeholder="Answer" aria-label="Answer"></textarea>
    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-repeat-remove>Remove</button>
  </div>
</template>

<?php require __DIR__ . '/includes/media-picker.php'; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
