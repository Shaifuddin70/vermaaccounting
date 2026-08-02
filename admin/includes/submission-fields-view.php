<?php
/** @var array $schema */
/** @var array $data */
/** @var array $filesByField */
?>
<dl class="submission-fields">
  <?php foreach ($schema['fields'] as $field):
    $type = $field['type'];
    $name = $field['name'];
    $id = $field['id'];

    if (in_array($type, ['heading', 'paragraph', 'page_break'], true)): ?>
      <div class="submission-section-break">
        <?php if ($type === 'heading'): ?>
          <h3><?= e($field['label']) ?></h3>
        <?php else: ?>
          <p><?= nl2br(e($field['label'])) ?></p>
        <?php endif; ?>
      </div>
      <?php continue;
    endif;

    $fieldFiles = $filesByField[$id] ?? [];
  ?>
    <div class="submission-field-row">
      <dt><?= e($field['label']) ?></dt>
      <dd>
        <?php if (in_array($type, ['file', 'image'], true)): ?>
          <?php if ($fieldFiles):
            $imageFiles = [];
            $otherFiles = [];
            foreach ($fieldFiles as $file) {
              if (is_image_mime($file['mime'] ?? null)) {
                $imageFiles[] = $file;
              } else {
                $otherFiles[] = $file;
              }
            }
            $fancyboxGroup = 'submission-' . (int) ($submissionId ?? 0) . '-' . (string) $id;
          ?>
            <div class="submission-files">
              <?php if (count($fieldFiles) > 1): ?>
                <div class="submission-files-toolbar">
                  <form method="post" action="/admin/submission-files-zip" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf ?? Auth::csrfToken()) ?>">
                    <input type="hidden" name="form_id" value="<?= (int) ($formId ?? 0) ?>">
                    <input type="hidden" name="submission_id" value="<?= (int) ($submissionId ?? 0) ?>">
                    <input type="hidden" name="field_id" value="<?= e((string) $id) ?>">
                    <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Download all</button>
                  </form>
                </div>
              <?php elseif (count($fieldFiles) === 1): ?>
                <div class="submission-files-toolbar">
                  <a href="/admin/download?file_id=<?= (int) $fieldFiles[0]['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Download</a>
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
          <?php else: ?>
            <span class="submission-empty">—</span>
          <?php endif; ?>
        <?php elseif ($type === 'yes_no'):
          $val = $data[$name] ?? '';
        ?>
          <p class="submission-value"><?= e($val !== '' ? ucfirst((string) $val) : '—') ?></p>
          <?php foreach (yes_no_follow_ups($field) as $followUp):
            $reasonKey = yes_no_follow_up_storage_key($name, $followUp);
            if (!isset($data[$reasonKey])) {
                continue;
            }
            $raw = $data[$reasonKey];
            if (is_array($raw)) {
              $display = implode(', ', array_map('strval', $raw));
            } else {
              $display = trim((string) $raw);
            }
            if ($display === '') {
                continue;
            }
            if (($followUp['type'] ?? '') === 'partners') {
              $display = format_partner_submission_value($raw);
            }
          ?>
            <p class="submission-reason"><strong><?= e($followUp['label']) ?>:</strong> <?= nl2br(e($display)) ?></p>
          <?php endforeach; ?>
        <?php elseif ($type === 'checkbox'): ?>
          <p class="submission-value"><?= e(format_submission_value($data[$name] ?? [])) ?: '—' ?></p>
        <?php elseif ($type === 'partners'): ?>
          <p class="submission-value"><?= e(format_partner_submission_value($data[$name] ?? [])) ?: '—' ?></p>
        <?php elseif ($type === 'textarea'): ?>
          <p class="submission-value submission-value--block"><?= nl2br(e(format_submission_value($data[$name] ?? ''))) ?: '—' ?></p>
        <?php else: ?>
          <p class="submission-value"><?= e(format_submission_value($data[$name] ?? '')) ?: '—' ?></p>
        <?php endif; ?>
      </dd>
    </div>
  <?php endforeach; ?>
</dl>
