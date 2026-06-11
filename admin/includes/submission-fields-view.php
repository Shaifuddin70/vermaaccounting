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

    if (in_array($type, ['heading', 'paragraph'], true)): ?>
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
          <?php if ($fieldFiles): ?>
            <div class="submission-files">
              <?php foreach ($fieldFiles as $file):
                $fileUrl = '/admin/view-file?file_id=' . (int) $file['id'];
                $downloadUrl = '/admin/download?file_id=' . (int) $file['id'];
              ?>
                <div class="submission-file-item">
                  <?php if (is_image_mime($file['mime'] ?? null)): ?>
                    <a href="<?= e($fileUrl) ?>" class="submission-image-link" data-fancybox="submission-<?= (int) ($submissionId ?? 0) ?>" data-caption="<?= e($file['original_name']) ?>">
                      <img src="<?= e($fileUrl) ?>" alt="<?= e($file['original_name']) ?>" class="submission-image-preview">
                    </a>
                  <?php endif; ?>
                  <div class="submission-file-meta">
                    <a href="<?= e($downloadUrl) ?>"><?= e($file['original_name']) ?></a>
                    <span class="submission-file-size">(<?= number_format((int) $file['size'] / 1024, 1) ?> KB)</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <span class="submission-empty">—</span>
          <?php endif; ?>
        <?php elseif ($type === 'yes_no'):
          $val = $data[$name] ?? '';
          $reasonKey = $name . '_reason';
        ?>
          <p class="submission-value"><?= e($val !== '' ? ucfirst($val) : '—') ?></p>
          <?php if (!empty($data[$reasonKey])): ?>
            <p class="submission-reason"><strong><?= e($field['reasonLabel'] ?? 'Reason') ?>:</strong> <?= nl2br(e((string) $data[$reasonKey])) ?></p>
          <?php endif; ?>
        <?php elseif ($type === 'checkbox'): ?>
          <p class="submission-value"><?= e(format_submission_value($data[$name] ?? [])) ?: '—' ?></p>
        <?php elseif ($type === 'textarea'): ?>
          <p class="submission-value submission-value--block"><?= nl2br(e(format_submission_value($data[$name] ?? ''))) ?: '—' ?></p>
        <?php else: ?>
          <p class="submission-value"><?= e(format_submission_value($data[$name] ?? '')) ?: '—' ?></p>
        <?php endif; ?>
      </dd>
    </div>
  <?php endforeach; ?>
</dl>
