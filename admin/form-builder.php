<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$formId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form = $formId ? $repo->find($formId) : null;

if ($formId && !$form) {
    header('Location: /admin/forms.php');
    exit;
}

$schema = $form ? $repo->decodeSchema($form) : normalize_form_schema(['fields' => []]);
$initial = [
    'id' => $form ? (int) $form['id'] : null,
    'slug' => $form['slug'] ?? '',
    'title' => $form['title'] ?? 'Untitled form',
    'description' => $form['description'] ?? '',
    'status' => $form['status'] ?? 'draft',
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
      <a href="/admin/submissions.php?form_id=<?= (int) $form['id'] ?>" class="admin-btn admin-btn-secondary">Responses</a>
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
    <div class="admin-card">
      <h2 style="margin:0 0 1rem;font-size:1rem;">Form settings</h2>
      <div class="admin-field">
        <label for="form-title">Title</label>
        <input type="text" id="form-title" value="<?= e($initial['title']) ?>">
      </div>
      <div class="admin-field">
        <label for="form-slug">URL slug</label>
        <input type="text" id="form-slug" value="<?= e($initial['slug']) ?>" placeholder="client-intake">
        <small style="color:#64748b;">Public URL: /form/<span id="slug-preview"><?= e($initial['slug'] ?: 'your-slug') ?></span></small>
      </div>
      <div class="admin-field">
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
      <div class="admin-field">
        <label for="success-message">Success message</label>
        <input type="text" id="success-message" value="<?= e($schema['settings']['successMessage'] ?? '') ?>">
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

    <?php if ($form): ?>
    <div class="admin-card">
      <h2 style="margin:0 0 0.75rem;font-size:1rem;">Embed on your site</h2>
      <p style="font-size:0.875rem;color:#64748b;">Link to this form or embed in an iframe:</p>
      <div class="embed-code" id="embed-link">https://vermaaccounting.ca/form/<?= e($form['slug']) ?></div>
      <div class="embed-code" style="margin-top:0.5rem;" id="embed-iframe">&lt;iframe src="https://vermaaccounting.ca/form/<?= e($form['slug']) ?>" width="100%" height="800" frameborder="0"&gt;&lt;/iframe&gt;</div>
    </div>
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
<script src="/admin/js/form-builder.js"></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
