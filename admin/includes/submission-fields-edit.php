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
        $followUps = yes_no_follow_ups($field);
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
        <?php foreach ($followUps as $followUp):
          $pseudo = yes_no_follow_up_as_field($field, $followUp);
          $reasonType = (string) $pseudo['type'];
          $reasonName = (string) $pseudo['name'];
          $reasonVal = $data[$reasonName] ?? '';
          $showReason = (string) $value === (string) $followUp['when'];
          $controlId = 'sub-' . e($id) . '-fu-' . e((string) $followUp['id']);
        ?>
          <div class="submission-edit-reason" <?= $showReason ? '' : 'hidden' ?>
            data-reason-when="<?= e($followUp['when']) ?>"
            data-field-name="<?= e($name) ?>">
            <label for="<?= $controlId ?>"><?= e($pseudo['label']) ?></label>
            <?php if ($reasonType === 'textarea'): ?>
              <textarea id="<?= $controlId ?>" name="<?= e($reasonName) ?>" rows="3"><?= e(is_array($reasonVal) ? implode(', ', $reasonVal) : (string) $reasonVal) ?></textarea>
            <?php elseif ($reasonType === 'select'): ?>
              <select id="<?= $controlId ?>" name="<?= e($reasonName) ?>">
                <option value="">Select…</option>
                <?php foreach ($pseudo['options'] ?? [] as $opt): ?>
                  <option value="<?= e((string) $opt['value']) ?>" <?= (string) $reasonVal === (string) $opt['value'] ? 'selected' : '' ?>><?= e((string) $opt['label']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($reasonType === 'radio'): ?>
              <div class="submission-edit-options">
                <?php foreach ($pseudo['options'] ?? [] as $opt): ?>
                  <label class="submission-edit-option">
                    <input type="radio" name="<?= e($reasonName) ?>" value="<?= e((string) $opt['value']) ?>"
                      <?= (string) $reasonVal === (string) $opt['value'] ? 'checked' : '' ?>>
                    <?= e((string) $opt['label']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php elseif ($reasonType === 'checkbox'):
              $checked = is_array($reasonVal) ? $reasonVal : [];
            ?>
              <div class="submission-edit-options">
                <?php foreach ($pseudo['options'] ?? [] as $opt): ?>
                  <label class="submission-edit-option">
                    <input type="checkbox" name="<?= e($reasonName) ?>[]" value="<?= e((string) $opt['value']) ?>"
                      <?= in_array((string) $opt['value'], array_map('strval', $checked), true) ? 'checked' : '' ?>>
                    <?= e((string) $opt['label']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php elseif (in_array($reasonType, ['file', 'image'], true)): ?>
              <p class="submission-edit-hint">File follow-ups can be re-uploaded from the public form. Current value: <?= e(is_array($reasonVal) ? implode(', ', $reasonVal) : (string) ($reasonVal ?: '—')) ?></p>
            <?php else:
              $reasonInputType = in_array($reasonType, ['email', 'tel', 'number', 'date'], true) ? $reasonType : 'text';
            ?>
              <input type="<?= e($reasonInputType) ?>"
                id="<?= $controlId ?>"
                name="<?= e($reasonName) ?>"
                value="<?= e(is_array($reasonVal) ? implode(', ', $reasonVal) : (string) $reasonVal) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

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
        <?php if ($fieldFiles):
          $editImages = array_values(array_filter($fieldFiles, static fn(array $f): bool => is_image_mime($f['mime'] ?? null)));
          $editOthers = array_values(array_filter($fieldFiles, static fn(array $f): bool => !is_image_mime($f['mime'] ?? null)));
          $editFancy = 'submission-edit-' . (int) ($submissionId ?? 0) . '-' . (string) $id;
        ?>
          <div class="submission-files submission-files--compact">
            <?php if ($editImages): ?>
              <div class="submission-gallery submission-gallery--compact">
                <?php foreach ($editImages as $file):
                  $fileUrl = '/admin/view-file?file_id=' . (int) $file['id'];
                ?>
                  <a href="<?= e($fileUrl) ?>" class="submission-image-link" data-fancybox="<?= e($editFancy) ?>" data-caption="<?= e($file['original_name']) ?>">
                    <img src="<?= e($fileUrl) ?>" alt="<?= e($file['original_name']) ?>" class="submission-image-preview submission-image-preview--sm">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php foreach ($editOthers as $file): ?>
              <span class="submission-edit-file-name"><?= e($file['original_name']) ?></span>
            <?php endforeach; ?>
          </div>
          <p class="submission-edit-hint">Upload a new file to replace the current one.</p>
        <?php endif; ?>
        <input type="file" id="sub-<?= e($id) ?>" name="<?= e($name) ?>"
          accept="<?= e($field['accept'] ?? ($type === 'image' ? 'image/*' : form_file_accept_default())) ?>">

      <?php else: ?>
        <?php
          $numberFormat = $type === 'number'
              ? normalize_number_format((string) ($field['numberFormat'] ?? ''))
              : '';
          $minAge = $type === 'date' ? normalize_min_age($field['minAge'] ?? 0) : 0;
          $dateMax = $type === 'date' ? date_max_for_min_age($minAge) : null;
          $inputType = $type === 'email'
              ? 'email'
              : ($type === 'date'
                  ? 'date'
                  : ($type === 'tel'
                      ? 'tel'
                      : ($type === 'number' && $numberFormat === ''
                          ? 'number'
                          : 'text')));
        ?>
        <input type="<?= e($inputType) ?>"
          id="sub-<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
          <?= $required ? 'required' : '' ?>
          <?php if ($dateMax !== null): ?>
            max="<?= e($dateMax) ?>"
            data-min-age="<?= (int) $minAge ?>"
          <?php endif; ?>
          <?php if ($numberFormat !== ''): ?>
            inputmode="numeric"
            data-number-format="<?= e($numberFormat) ?>"
            maxlength="<?= (int) strlen($numberFormat) ?>"
          <?php endif; ?>>
          <?php if ($dateMax !== null): ?>
            <small class="admin-field-hint">Minimum age: <?= (int) $minAge ?></small>
          <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<script>
(function () {
  function applyNumberFormatMask(raw, format) {
    var digits = String(raw || '').replace(/\D+/g, '');
    var slots = (format.match(/#/g) || []).length;
    var limited = slots > 0 ? digits.slice(0, slots) : digits;
    var out = '';
    var di = 0;
    for (var i = 0; i < format.length; i++) {
      var ch = format[i];
      if (ch === '#') {
        if (di >= limited.length) break;
        out += limited[di++];
      } else if (di < limited.length) {
        out += ch;
      } else {
        break;
      }
    }
    return out;
  }

  document.querySelectorAll('input[data-number-format]').forEach(function (input) {
    var format = input.getAttribute('data-number-format') || '';
    if (!format) return;
    var reformat = function () {
      input.value = applyNumberFormatMask(input.value, format);
    };
    input.addEventListener('input', reformat);
    input.addEventListener('blur', reformat);
  });

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
