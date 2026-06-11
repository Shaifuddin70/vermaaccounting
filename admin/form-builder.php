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

$pageTitle = $form ? 'Edit form' : 'New form';
$activeNav = 'builder';
$csrf = Auth::csrfToken();
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
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

<div class="admin-grid-2">
  <div>
    <div class="builder-settings-pair">
    <div class="admin-card builder-settings-card">
      <h2 style="margin:0 0 1rem;font-size:1rem;">Form settings</h2>
      <div class="admin-fields-2col">
        <div class="admin-field admin-field--full">
          <label for="form-title">Title</label>
          <input type="text" id="form-title" value="<?= e($initial['title']) ?>">
        </div>
        <div class="admin-field admin-field--full">
          <label for="form-slug">URL slug</label>
          <input type="text" id="form-slug" value="<?= e($initial['slug']) ?>" placeholder="client-intake">
          <small style="color:#64748b;">Public URL: /form/<span id="slug-preview"><?= e($initial['slug'] ?: 'your-slug') ?></span></small>
        </div>
        <div class="admin-field admin-field--full">
          <label for="form-description">Description (optional)</label>
          <textarea id="form-description" rows="2"><?= e($initial['description']) ?></textarea>
        </div>
        <div class="admin-field">
          <label for="form-status">Status</label>
          <select id="form-status">
            <option value="draft" <?= $initial['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $initial['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
        <div class="admin-field">
          <label for="submit-label">Submit button text</label>
          <input type="text" id="submit-label" value="<?= e($schema['settings']['submitLabel'] ?? 'Submit') ?>">
        </div>
        <div class="admin-field admin-field--full">
          <label for="success-message">Success message</label>
          <input type="text" id="success-message" value="<?= e($schema['settings']['successMessage'] ?? '') ?>">
        </div>
        <div class="admin-field admin-field--full">
          <label class="admin-checkbox-label">
            <input type="checkbox" id="form-site-cta" <?= !empty($initial['is_site_cta']) ? 'checked' : '' ?>>
            Link on website (homepage hero &amp; header menu)
          </label>
          <small class="admin-field-hint">Only one form at a time. Must be <strong>Published</strong> to appear on the site.</small>
        </div>
        <div class="admin-field admin-field--full" id="form-cta-label-wrap">
          <label for="form-cta-label">Button label (optional)</label>
          <input type="text" id="form-cta-label" value="<?= e($initial['cta_label']) ?>"
            placeholder="Get Free Consultation">
          <small class="admin-field-hint">Used in the hero and beside About Us in the menu. Leave blank for the default text.</small>
        </div>
      </div>
    </div>

    <?php
      $ty = $schema['settings']['taxYear'] ?? [];
      $tyYearsList = implode(', ', $ty['years'] ?? []);
    ?>
    <div class="admin-card builder-settings-card">
      <h2 style="margin:0 0 0.75rem;font-size:1rem;">Tax year selection</h2>
      <p style="font-size:0.875rem;color:#64748b;margin:0 0 1rem;">
        When enabled, visitors choose a tax year first; responses are stored and filtered by that year.
      </p>
      <div class="admin-fields-2col">
        <div class="admin-field admin-field--full">
          <label class="admin-checkbox-label">
            <input type="checkbox" id="tax-year-enabled" <?= !empty($ty['enabled']) ? 'checked' : '' ?>>
            Require tax year before showing the form
          </label>
        </div>
        <div class="admin-field admin-field--full tax-year-settings" id="tax-year-settings">
          <label for="tax-year-label">Year step heading</label>
          <input type="text" id="tax-year-label" value="<?= e($ty['label'] ?? 'Which tax year are you filing for?') ?>">
        </div>
        <div class="admin-field admin-field--full tax-year-settings">
          <label for="tax-year-prompt">Instructions (optional)</label>
          <input type="text" id="tax-year-prompt" value="<?= e($ty['prompt'] ?? '') ?>"
            placeholder="Select a year to continue to the form for that tax period.">
        </div>
        <div class="admin-field admin-field--full tax-year-settings">
          <label for="tax-year-years">Available years</label>
          <input type="text" id="tax-year-years" value="<?= e($tyYearsList) ?>"
            placeholder="e.g. 2025, 2024, 2023, 2022">
          <small class="admin-field-hint">Comma-separated. Leave blank to use the current year and the five prior years.</small>
        </div>
      </div>
    </div>
    </div><!-- .builder-settings-pair -->

    <?php
      $dm = $schema['settings']['dataMatch'] ?? [];
      $dmFieldIds = $dm['fieldIds'] ?? [];
    ?>
    <div class="admin-card">
      <h2 style="margin:0 0 0.75rem;font-size:1rem;">Autofill from previous submission</h2>
      <p style="font-size:0.875rem;color:#64748b;margin:0 0 1rem;">
        Choose 2 or more fields (e.g. email + phone). When a visitor enters values that match a past submission, they can fill the form with that saved data.
      </p>
      <div class="admin-field admin-field--full">
        <label class="admin-checkbox-label">
          <input type="checkbox" id="data-match-enabled" <?= !empty($dm['enabled']) ? 'checked' : '' ?>>
          Enable lookup and autofill popup
        </label>
      </div>
      <div id="data-match-settings" class="data-match-settings">
        <div class="admin-field admin-field--full">
          <label>Match fields <span style="font-weight:400;color:#64748b;">(select at least 2)</span></label>
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

    <div class="admin-card">
      <div class="admin-header" style="margin-bottom:0.75rem;">
        <h2 style="margin:0;font-size:1rem;">Fields</h2>
      </div>
      <div class="builder-toolbar">
        <select id="add-field-type">
          <?php foreach (field_types() as $type => $label): ?>
            <option value="<?= e($type) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="add-field-btn" class="admin-btn admin-btn-secondary">+ Add field</button>
      </div>
      <div id="field-list" class="builder-field-list"></div>
    </div>
  </div>

  <div>
    <div class="admin-card" id="field-editor-panel">
      <h2 style="margin:0 0 1rem;font-size:1rem;">Field settings</h2>
      <p id="no-field-selected" style="color:#64748b;">Select a field to edit its properties and conditional logic.</p>
      <div id="field-editor" style="display:none;"></div>
    </div>

    <div class="admin-card">
      <h2 style="margin:0 0 0.75rem;font-size:1rem;">Embed on your site</h2>
      <?php if ($form): ?>
      <p style="font-size:0.875rem;color:#64748b;margin:0 0 0.5rem;">Direct link:</p>
      <div class="embed-code" id="embed-link"><?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'vermaaccounting.ca')) ?>/form/<?= e($form['slug']) ?></div>
      <p style="font-size:0.875rem;color:#64748b;margin:1rem 0 0.5rem;">PHP shortcode (paste in any .php page):</p>
      <div class="embed-code" id="embed-shortcode"><?= e(form_shortcode_literal($form['slug'])) ?></div>
      <p style="font-size:0.875rem;color:#64748b;margin:1rem 0 0.5rem;">Or output in PHP:</p>
      <div class="embed-code">&lt;?= process_form_shortcodes('<?= e(form_shortcode_literal($form['slug'])) ?>') ?&gt;</div>
      <p style="font-size:0.875rem;color:#64748b;margin:1rem 0 0.5rem;">Iframe embed:</p>
      <div class="embed-code" id="embed-iframe">&lt;iframe src="/form/<?= e($form['slug']) ?>?embed=1" width="100%" height="720" frameborder="0" title="<?= e($form['title']) ?>"&gt;&lt;/iframe&gt;</div>
      <?php else: ?>
      <p style="font-size:0.875rem;color:#64748b;">Save the form first to get embed codes and shortcodes.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  window.FORM_BUILDER_CONFIG = {
    csrfToken: <?= json_encode($csrf) ?>,
    initial: <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>,
    fieldTypes: <?= json_encode(field_types(), JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script src="/admin/js/form-builder.js?v=5"></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
