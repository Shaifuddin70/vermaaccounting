<?php
/** @var array $schema */
/** @var array $data */
/** @var array $filesByField */
?>
<div class="submission-edit-fields">
  <?php foreach ($schema['fields'] as $field):
    $type = $field['type'];
    $name = $field['name'];
    $id = $field['id'];
    $required = !empty($field['required']);
    $value = $data[$name] ?? '';
    $fieldFiles = $filesByField[$id] ?? [];

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
  ?>
    <div class="submission-edit-field">
      <label for="sub-<?= e($id) ?>">
        <?= e($field['label']) ?>
        <?php if ($required && !in_array($type, ['file', 'image'], true)): ?>
          <span class="required">*</span>
        <?php endif; ?>
      </label>

      <?php if ($type === 'textarea'): ?>
        <textarea id="sub-<?= e($id) ?>" name="<?= e($name) ?>" rows="4"><?= e((string) $value) ?></textarea>

      <?php elseif ($type === 'select'): ?>
        <select id="sub-<?= e($id) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
          <option value="">Select…</option>
          <?php foreach ($field['options'] ?? [] as $opt): ?>
            <option value="<?= e($opt['value']) ?>" <?= (string) $value === (string) $opt['value'] ? 'selected' : '' ?>>
              <?= e($opt['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>

      <?php elseif ($type === 'radio'): ?>
        <div class="submission-edit-options">
          <?php foreach ($field['options'] ?? [] as $opt): ?>
            <label class="submission-edit-option">
              <input type="radio" name="<?= e($name) ?>" value="<?= e($opt['value']) ?>"
                <?= (string) $value === (string) $opt['value'] ? 'checked' : '' ?>
                <?= $required ? 'required' : '' ?>>
              <?= e($opt['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>

      <?php elseif ($type === 'yes_no'):
        $reasonWhen = $field['reasonWhen'] ?? '';
        $reasonName = $name . '_reason';
        $reasonVal = $data[$reasonName] ?? '';
        $showReason = $reasonWhen !== '' && (string) $value === $reasonWhen;
      ?>
        <div class="submission-edit-options">
          <?php foreach ($field['options'] ?? [] as $opt): ?>
            <label class="submission-edit-option">
              <input type="radio" name="<?= e($name) ?>" value="<?= e($opt['value']) ?>"
                <?= (string) $value === (string) $opt['value'] ? 'checked' : '' ?>
                <?= $required ? 'required' : '' ?>>
              <?= e($opt['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if ($reasonWhen !== ''): ?>
          <div class="submission-edit-reason" <?= $showReason ? '' : 'hidden' ?> data-reason-when="<?= e($reasonWhen) ?>" data-field-name="<?= e($name) ?>">
            <label for="sub-<?= e($id) ?>-reason"><?= e($field['reasonLabel'] ?? 'Please explain') ?></label>
            <textarea id="sub-<?= e($id) ?>-reason" name="<?= e($reasonName) ?>" rows="3"><?= e((string) $reasonVal) ?></textarea>
          </div>
        <?php endif; ?>

      <?php elseif ($type === 'checkbox'):
        $checked = is_array($value) ? $value : [];
      ?>
        <div class="submission-edit-options">
          <?php foreach ($field['options'] ?? [] as $opt): ?>
            <label class="submission-edit-option">
              <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($opt['value']) ?>"
                <?= in_array((string) $opt['value'], array_map('strval', $checked), true) ? 'checked' : '' ?>>
              <?= e($opt['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>

      <?php elseif ($type === 'file' || $type === 'image'): ?>
        <?php if ($fieldFiles): ?>
          <div class="submission-files submission-files--compact">
            <?php foreach ($fieldFiles as $file):
              $fileUrl = '/admin/view-file.php?file_id=' . (int) $file['id'];
            ?>
              <?php if (is_image_mime($file['mime'] ?? null)): ?>
                <a href="<?= e($fileUrl) ?>" class="submission-image-link" data-fancybox="submission-edit-<?= (int) ($submissionId ?? 0) ?>" data-caption="<?= e($file['original_name']) ?>">
                  <img src="<?= e($fileUrl) ?>" alt="<?= e($file['original_name']) ?>" class="submission-image-preview submission-image-preview--sm">
                </a>
              <?php endif; ?>
              <span><?= e($file['original_name']) ?></span>
            <?php endforeach; ?>
          </div>
          <p class="submission-edit-hint">Upload a new file to replace the current one.</p>
        <?php endif; ?>
        <input type="file" id="sub-<?= e($id) ?>" name="<?= e($name) ?>"
          accept="<?= e($field['accept'] ?? ($type === 'image' ? 'image/*' : '')) ?>">

      <?php else: ?>
        <input type="<?= e($type === 'email' ? 'email' : ($type === 'number' ? 'number' : ($type === 'date' ? 'date' : ($type === 'tel' ? 'tel' : 'text')))) ?>"
          id="sub-<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
          <?= $required ? 'required' : '' ?>>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<script>
(function () {
  document.querySelectorAll('.submission-edit-reason[data-reason-when]').forEach(function (wrap) {
    var when = wrap.getAttribute('data-reason-when');
    var fieldName = wrap.getAttribute('data-field-name');
    if (!fieldName) return;
    function sync() {
      var checked = document.querySelector('input[name="' + fieldName + '"]:checked');
      wrap.hidden = !checked || checked.value !== when;
    }
    document.querySelectorAll('input[name="' + fieldName + '"]').forEach(function (r) {
      r.addEventListener('change', sync);
    });
    sync();
  });
})();
</script>
