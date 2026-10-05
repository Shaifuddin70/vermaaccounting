<?php
/**
 * Image gallery + file cards for one submission field.
 *
 * @var list<array<string, mixed>> $fieldFiles
 * @var string $galleryFieldId submission_files.field_id used for "Download all"
 */
$imageFiles = [];
$otherFiles = [];
foreach ($fieldFiles as $file) {
  if (is_image_mime($file['mime'] ?? null)) {
    $imageFiles[] = $file;
  } else {
    $otherFiles[] = $file;
  }
}
$fancyboxGroup = 'submission-' . (int) ($submissionId ?? 0) . '-' . $galleryFieldId;
?>
<div class="submission-files">
  <?php if (count($fieldFiles) > 1): ?>
    <div class="submission-files-toolbar">
      <form method="post" action="/admin/submission-files-zip" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf ?? Auth::csrfToken()) ?>">
        <input type="hidden" name="form_id" value="<?= (int) ($formId ?? 0) ?>">
        <input type="hidden" name="submission_id" value="<?= (int) ($submissionId ?? 0) ?>">
        <input type="hidden" name="field_id" value="<?= e($galleryFieldId) ?>">
        <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Download all</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($imageFiles): ?>
    <div class="submission-gallery" role="list">
      <?php foreach ($imageFiles as $file):
        $fileUrl = '/admin/view-file?file_id=' . (int) $file['id'];
        $downloadUrl = '/admin/download?file_id=' . (int) $file['id'];
      ?>
        <figure class="submission-gallery-item" role="listitem">
          <a
            href="<?= e($fileUrl) ?>"
            class="submission-gallery-thumb"
            data-fancybox="<?= e($fancyboxGroup) ?>"
            data-caption="<?= e($file['original_name']) ?>">
            <img src="<?= e($fileUrl) ?>" alt="<?= e($file['original_name']) ?>" loading="lazy">
          </a>
          <figcaption class="submission-gallery-caption">
            <a href="<?= e($downloadUrl) ?>" class="submission-gallery-name" title="<?= e($file['original_name']) ?>">
              <?= e($file['original_name']) ?>
            </a>
            <span class="submission-file-size"><?= number_format((int) $file['size'] / 1024, 1) ?> KB</span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($otherFiles): ?>
    <ul class="submission-file-list">
      <?php foreach ($otherFiles as $file):
        $downloadUrl = '/admin/download?file_id=' . (int) $file['id'];
        $ext = file_extension_label($file['original_name'] ?? '', $file['mime'] ?? null);
      ?>
        <li class="submission-file-card">
          <span class="submission-file-ext" aria-hidden="true"><?= e($ext) ?></span>
          <div class="submission-file-meta">
            <a href="<?= e($downloadUrl) ?>"><?= e($file['original_name']) ?></a>
            <span class="submission-file-size"><?= number_format((int) $file['size'] / 1024, 1) ?> KB</span>
          </div>
          <a href="<?= e($downloadUrl) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Download</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
