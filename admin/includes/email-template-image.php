<?php
declare(strict_types=1);

$templateImageFixedName = isset($templateImageFixedName) ? trim((string) $templateImageFixedName) : '';
$templateImageNameInputId = (string) ($templateImageNameInputId ?? '');
$templateImageBodyId = (string) ($templateImageBodyId ?? 'email-body');
$templateImageCsrf = (string) ($templateImageCsrf ?? Auth::csrfToken());
$templateImageUploadUrl = (string) ($templateImageUploadUrl ?? '/admin/email-editor-api');

$initialName = $templateImageFixedName !== ''
    ? $templateImageFixedName
    : trim((string) ($templateImageInitialName ?? ''));
$slug = canada_holiday_email_image_slug($initialName !== '' ? $initialName : 'holiday');
$existingFile = $initialName !== '' ? canada_holiday_email_image_find_filename($slug) : null;
$previewUrl = $initialName !== '' ? canada_holiday_email_image_url_for_name($initialName) : '';
$hasImage = $existingFile !== null;
$expectedName = $slug . '.jpg';
?>
<div
  class="admin-field email-template-image"
  data-email-template-image
  data-fixed-name="<?= e($templateImageFixedName) ?>"
  data-name-input-id="<?= e($templateImageNameInputId) ?>"
  data-body-id="<?= e($templateImageBodyId) ?>"
  data-upload-url="<?= e($templateImageUploadUrl) ?>"
  data-csrf="<?= e($templateImageCsrf) ?>">
  <label>Template image</label>
  <div class="email-template-image-box">
    <div class="email-template-image-preview<?= $hasImage ? ' has-image' : '' ?>" data-preview>
      <?php if ($hasImage): ?>
        <img src="<?= e($previewUrl) ?>" alt="Template image preview" data-preview-img>
      <?php else: ?>
        <div class="email-template-image-placeholder" data-placeholder>
          <span>No image yet</span>
          <small data-filename><?= e($expectedName) ?></small>
        </div>
      <?php endif; ?>
    </div>
    <div class="email-template-image-meta">
      <p class="email-template-image-file">
        Saved as <code data-expected-file><?= e($existingFile ?? $expectedName) ?></code>
      </p>
      <p class="admin-field-hint" style="margin:0;">
        Named automatically from the email name (e.g. Civic Holiday → <code>civic-holiday.jpg</code>).
        Upload replaces that file and updates the banner in the email below.
      </p>
      <div class="email-template-image-actions">
        <label class="admin-btn admin-btn-secondary email-template-image-btn">
          <span data-upload-label><?= $hasImage ? 'Replace image' : 'Upload image' ?></span>
          <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-file-input hidden>
        </label>
        <span class="admin-field-hint" data-status aria-live="polite"></span>
      </div>
    </div>
  </div>
</div>
