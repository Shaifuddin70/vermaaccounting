<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$formId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form = $formId ? $repo->find($formId) : null;

if ($formId && !$form) {
    header('Location: /admin/forms');
    exit;
}

$schema = $form ? $repo->decodeSchema($form) : normalize_form_schema(['fields' => []]);
$initial = [
    'id' => $form ? (int) $form['id'] : null,
    'slug' => $form['slug'] ?? '',
    'title' => $form['title'] ?? 'Untitled form',
    'description' => $form['description'] ?? '',
    'status' => $form['status'] ?? 'draft',
    'is_site_cta' => $form ? !empty($form['is_site_cta']) : false,
    'cta_label' => $form['cta_label'] ?? '',
    'schema' => $schema,
];

$ty = $schema['settings']['taxYear'] ?? [];
$tyYearsList = implode(', ', $ty['years'] ?? []);
$dm = $schema['settings']['dataMatch'] ?? [];

$siteHost = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'vermaaccounting.ca');

$pageTitle = $form ? 'Edit form' : 'New form';
$activeNav = 'forms';
$csrf = Auth::csrfToken();
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
  <div class="admin-header-actions">
    <?php if ($form): ?>
      <a href="/admin/submissions?form_id=<?= (int) $form['id'] ?>" class="admin-btn admin-btn-secondary">Responses</a>
      <?php if ($form['status'] === 'published'): ?>
        <a href="/form/<?= e($form['slug']) ?>" class="admin-btn admin-btn-secondary" target="_blank" rel="noopener">Preview</a>
      <?php endif; ?>
    <?php endif; ?>
    <button type="button" id="save-form-btn" class="admin-btn">Save form</button>
  </div>
</div>

<div id="save-status" class="admin-alert" style="display:none;"></div>

<div class="fb-page">
  <section class="admin-card fb-essentials" aria-label="Form essentials">
    <div class="fb-essentials-grid">
      <div class="admin-field fb-field-title">
        <label for="form-title">Title</label>
        <input type="text" id="form-title" value="<?= e($initial['title']) ?>">
      </div>
      <div class="admin-field fb-field-slug">
        <label for="form-slug">URL slug</label>
        <input type="text" id="form-slug" value="<?= e($initial['slug']) ?>" placeholder="client-intake">
        <small class="admin-field-hint">/form/<span id="slug-preview"><?= e($initial['slug'] ?: 'your-slug') ?></span></small>
      </div>
      <div class="admin-field fb-field-status">
        <label for="form-status">Status</label>
        <select id="form-status">
          <option value="draft" <?= $initial['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="published" <?= $initial['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
      </div>
    </div>
    <div class="admin-field fb-field-desc">
      <label for="form-description">Description <span class="fb-optional">(optional)</span></label>
      <textarea id="form-description" rows="2" placeholder="Shown at the top of the public form"><?= e($initial['description']) ?></textarea>
    </div>
    <div class="fb-essentials-row2">
      <div class="admin-field">
        <label for="submit-label">Submit button</label>
        <input type="text" id="submit-label" value="<?= e($schema['settings']['submitLabel'] ?? 'Submit') ?>">
      </div>
      <div class="admin-field">
        <label for="success-message">Success message</label>
        <input type="text" id="success-message" value="<?= e($schema['settings']['successMessage'] ?? '') ?>" placeholder="Thank you! Your response has been received.">
      </div>
      <div class="admin-field fb-field-cta">
        <label class="admin-checkbox-label fb-checkbox-compact">
          <input type="checkbox" id="form-site-cta" <?= !empty($initial['is_site_cta']) ? 'checked' : '' ?>>
          <span>Show on website</span>
        </label>
        <small class="admin-field-hint">Homepage hero &amp; header. Must be published.</small>
      </div>
    </div>
    <div class="admin-field fb-cta-label-wrap" id="form-cta-label-wrap">
      <label for="form-cta-label">Website button label <span class="fb-optional">(optional)</span></label>
      <input type="text" id="form-cta-label" value="<?= e($initial['cta_label']) ?>" placeholder="Get Free Consultation">
    </div>
  </section>

  <div class="fb-workspace">
    <section class="admin-card fb-fields-card">
      <div class="fb-panel-head">
        <h2 class="fb-panel-title">Fields</h2>
        <div class="builder-toolbar fb-toolbar">
          <select id="add-field-type" aria-label="Field type to add">
            <?php foreach (field_types() as $type => $label): ?>
              <option value="<?= e($type) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="add-field-btn" class="admin-btn admin-btn-secondary admin-btn-sm">+ Add field</button>
        </div>
      </div>
      <p id="field-list-empty" class="fb-fields-empty" hidden>Add fields below or use the toolbar to get started.</p>
      <div id="field-list" class="builder-field-list"></div>
    </section>

    <aside class="admin-card fb-editor-card" id="field-editor-panel">
      <h2 class="fb-panel-title">Field settings</h2>
      <p id="no-field-selected" class="fb-editor-placeholder">Select a field to edit its label, options, and conditional logic.</p>
      <div id="field-editor" style="display:none;"></div>
    </aside>
  </div>

  <div class="fb-advanced" aria-label="Advanced settings">
    <details class="fb-section" id="fb-section-tax-year" <?= !empty($ty['enabled']) ? 'open' : '' ?>>
      <summary class="fb-section-summary">
        <span class="fb-section-title">Tax year selection</span>
        <span class="fb-section-hint">Require a year before the form</span>
      </summary>
      <div class="fb-section-body">
        <div class="admin-field admin-field--full">
          <label class="admin-checkbox-label">
            <input type="checkbox" id="tax-year-enabled" <?= !empty($ty['enabled']) ? 'checked' : '' ?>>
            <span>Require tax year before showing the form</span>
          </label>
        </div>
        <div class="admin-fields-2col tax-year-settings" id="tax-year-settings">
          <div class="admin-field admin-field--full">
            <label for="tax-year-label">Year step heading</label>
            <input type="text" id="tax-year-label" value="<?= e($ty['label'] ?? 'Which tax year are you filing for?') ?>">
          </div>
          <div class="admin-field admin-field--full">
            <label for="tax-year-prompt">Instructions <span class="fb-optional">(optional)</span></label>
            <input type="text" id="tax-year-prompt" value="<?= e($ty['prompt'] ?? '') ?>"
              placeholder="Select a year to continue to the form for that tax period.">
          </div>
          <div class="admin-field admin-field--full">
            <label for="tax-year-years">Available years</label>
            <input type="text" id="tax-year-years" value="<?= e($tyYearsList) ?>"
              placeholder="e.g. 2025, 2024, 2023">
            <small class="admin-field-hint">Comma-separated. Blank = current year and five prior years.</small>
          </div>
        </div>
      </div>
    </details>

    <details class="fb-section" id="fb-section-autofill" <?= !empty($dm['enabled']) ? 'open' : '' ?>>
      <summary class="fb-section-summary">
        <span class="fb-section-title">Autofill from previous submission</span>
        <span class="fb-section-hint">Match returning visitors by email, phone, etc.</span>
      </summary>
      <div class="fb-section-body">
        <div class="admin-field admin-field--full">
          <label class="admin-checkbox-label">
            <input type="checkbox" id="data-match-enabled" <?= !empty($dm['enabled']) ? 'checked' : '' ?>>
            <span>Enable lookup and autofill popup</span>
          </label>
        </div>
        <div id="data-match-settings" class="data-match-settings">
          <div class="admin-field admin-field--full">
            <label>Match fields <span class="fb-optional">(at least 2)</span></label>
            <div id="data-match-fields" class="data-match-fields"></div>
          </div>
          <div class="admin-fields-2col">
            <div class="admin-field admin-field--full">
              <label for="data-match-title">Popup title</label>
              <input type="text" id="data-match-title" value="<?= e($dm['title'] ?? '') ?>">
            </div>
            <div class="admin-field admin-field--full">
              <label for="data-match-message">Popup message</label>
              <input type="text" id="data-match-message" value="<?= e($dm['message'] ?? '') ?>">
            </div>
            <div class="admin-field">
              <label for="data-match-confirm">Confirm button</label>
              <input type="text" id="data-match-confirm" value="<?= e($dm['confirmLabel'] ?? '') ?>">
            </div>
            <div class="admin-field">
              <label for="data-match-decline">Decline button</label>
              <input type="text" id="data-match-decline" value="<?= e($dm['declineLabel'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
    </details>

    <?php if ($form): ?>
      <details class="fb-section" id="fb-section-embed">
        <summary class="fb-section-summary">
          <span class="fb-section-title">Embed &amp; share</span>
          <span class="fb-section-hint">Links and shortcodes for your site</span>
        </summary>
        <div class="fb-section-body fb-embed-grid">
          <div class="fb-embed-item">
            <div class="fb-embed-label">Direct link</div>
            <div class="fb-embed-row">
              <code class="embed-code" id="embed-link"><?= e($siteHost) ?>/form/<?= e($form['slug']) ?></code>
              <button type="button" class="admin-copy-btn fb-copy-btn" data-copy-path="/form/<?= e($form['slug']) ?>" title="Copy link" aria-label="Copy direct link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>
          </div>
          <div class="fb-embed-item">
            <div class="fb-embed-label">PHP shortcode</div>
            <div class="fb-embed-row">
              <code class="embed-code" id="embed-shortcode"><?= e(form_shortcode_literal($form['slug'])) ?></code>
              <button type="button" class="admin-copy-btn fb-copy-btn" data-copy="<?= e(form_shortcode_literal($form['slug'])) ?>" title="Copy shortcode" aria-label="Copy shortcode">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>
          </div>
          <div class="fb-embed-item">
            <div class="fb-embed-label">Iframe embed</div>
            <div class="fb-embed-row">
              <code class="embed-code" id="embed-iframe">&lt;iframe src="/form/<?= e($form['slug']) ?>?embed=1" width="100%" height="720" frameborder="0" title="<?= e($form['title']) ?>"&gt;&lt;/iframe&gt;</code>
              <button type="button" class="admin-copy-btn fb-copy-btn" data-copy="&lt;iframe src=&quot;/form/<?= e($form['slug']) ?>?embed=1&quot; width=&quot;100%&quot; height=&quot;720&quot; frameborder=&quot;0&quot; title=&quot;<?= e($form['title']) ?>&quot;&gt;&lt;/iframe&gt;" title="Copy iframe" aria-label="Copy iframe code">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>
          </div>
        </div>
      </details>
    <?php else: ?>
      <p class="admin-note fb-save-hint">Save the form once to get embed codes and share links.</p>
    <?php endif; ?>
  </div>
</div>

<script>
  window.FORM_BUILDER_CONFIG = {
    csrfToken: <?= json_encode($csrf) ?>,
    initial: <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>,
    fieldTypes: <?= json_encode(field_types(), JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script src="/admin/js/form-builder.js?v=7"></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
