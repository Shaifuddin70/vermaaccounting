<?php
declare(strict_types=1);

$emailEditorId = $emailEditorId ?? 'email-body';
$emailEditorName = $emailEditorName ?? 'body';
$emailEditorValue = $emailEditorValue ?? '';
$emailEditorLabel = $emailEditorLabel ?? 'Email message';
$emailEditorRequired = $emailEditorRequired ?? true;
$emailEditorPlaceholder = $emailEditorPlaceholder ?? 'Write your HTML email…';
$emailEditorHint = $emailEditorHint ?? 'Edit the email as HTML. Use Image to upload and insert an img tag. Tokens: {client_name}, {client_email}, {sin}, {company}, {year}';
$emailEditorUploadUrl = $emailEditorUploadUrl ?? '/admin/email-editor-api';
$emailEditorCsrf = $emailEditorCsrf ?? Auth::csrfToken();
$emailEditorSubjectId = (string) ($emailEditorSubjectId ?? '');

$emailEditorSample = is_array($emailEditorSample ?? null)
    ? $emailEditorSample
    : (function_exists('campaign_sample_client') ? campaign_sample_client() : []);
$samplePayload = [
    'client_name' => (string) ($emailEditorSample['client_name'] ?? $emailEditorSample['name'] ?? 'Sample Client'),
    'client_email' => (string) ($emailEditorSample['email'] ?? 'client@example.com'),
    'sin' => (string) ($emailEditorSample['sin'] ?? 'SAMPLE-SIN'),
    'company' => (string) ($emailEditorSample['company'] ?? 'Sample Company'),
    'year' => function_exists('campaign_email_current_year')
        ? campaign_email_current_year()
        : (string) date('Y'),
];
?>
<div class="admin-field email-editor-field">
  <label for="<?= e($emailEditorId) ?>"><?= e($emailEditorLabel) ?><?= $emailEditorRequired ? ' <span class="required">*</span>' : '' ?></label>

  <div
    class="email-editor"
    data-email-editor
    data-input-id="<?= e($emailEditorId) ?>"
    data-subject-id="<?= e($emailEditorSubjectId) ?>"
    data-upload-url="<?= e($emailEditorUploadUrl) ?>"
    data-csrf="<?= e($emailEditorCsrf) ?>"
    data-sample="<?= e(json_encode($samplePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>">
    <div class="email-editor-toolbar" role="toolbar" aria-label="Email editor tools">
      <div class="email-editor-actions">
        <button type="button" class="email-editor-action-btn" data-action="image" title="Upload image">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
          <span>Image</span>
        </button>
        <button type="button" class="email-editor-action-btn" data-action="link" title="Insert link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
          <span>Link</span>
        </button>
        <button type="button" class="email-editor-action-btn" data-action="preview" title="Preview email" aria-pressed="false">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
          <span data-preview-label>Preview</span>
        </button>
      </div>
      <input type="file" class="email-editor-image-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
    </div>

    <div class="email-editor-panels">
      <textarea
        id="<?= e($emailEditorId) ?>"
        name="<?= e($emailEditorName) ?>"
        class="email-editor-html"
        rows="16"
        spellcheck="false"
        placeholder="<?= e($emailEditorPlaceholder) ?>"
        <?= $emailEditorRequired ? 'required' : '' ?>><?= e($emailEditorValue) ?></textarea>
      <div class="email-editor-preview" data-preview-panel hidden>
        <p class="email-editor-preview-subject" data-preview-subject hidden></p>
        <iframe
          class="email-editor-preview-frame"
          title="Email preview"
          sandbox="allow-same-origin"
          data-preview-frame
        ></iframe>
        <p class="admin-field-hint email-editor-preview-empty" data-preview-empty hidden>
          Add an email message to see the preview.
        </p>
      </div>
    </div>
  </div>

  <small class="admin-field-hint"><?= e($emailEditorHint) ?></small>
</div>
