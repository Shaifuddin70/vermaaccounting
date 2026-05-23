<?php
/** @var array $schema */
/** @var array $form */
?>
<form class="custom-form" id="custom-form" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="form_slug" value="<?= e($form['slug']) ?>">

  <?php foreach ($schema['fields'] as $field):
    $type = $field['type'];
    $name = e($field['name']);
    $id = e($field['id']);
    $required = !empty($field['required']);
    $condJson = !empty($field['conditions']) ? e(json_encode($field['conditions'])) : '';
    $wrapAttrs = 'class="custom-form-field" data-field-id="' . $id . '"';
    if ($condJson) {
        $wrapAttrs .= ' data-conditions="' . $condJson . '" style="display:none;"';
    }
  ?>
    <div <?= $wrapAttrs ?>>

      <?php if ($type === 'heading'): ?>
        <h3 class="custom-form-heading"><?= e($field['label']) ?></h3>

      <?php elseif ($type === 'paragraph'): ?>
        <p class="custom-form-paragraph"><?= nl2br(e($field['label'])) ?></p>

      <?php else: ?>
        <label for="cf-<?= $id ?>">
          <?= e($field['label']) ?>
          <?php if ($required): ?><span class="required">*</span><?php endif; ?>
        </label>

        <?php if ($type === 'textarea'): ?>
          <textarea id="cf-<?= $id ?>" name="<?= $name ?>" rows="4"
            placeholder="<?= e($field['placeholder'] ?? '') ?>"
            <?= $required ? 'required' : '' ?>></textarea>

        <?php elseif ($type === 'select'): ?>
          <select id="cf-<?= $id ?>" name="<?= $name ?>" <?= $required ? 'required' : '' ?>>
            <option value="">Select…</option>
            <?php foreach ($field['options'] ?? [] as $opt): ?>
              <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($type === 'radio' || $type === 'yes_no'): ?>
          <div class="custom-form-options">
            <?php foreach ($field['options'] ?? [] as $opt): ?>
              <label class="custom-form-option">
                <input type="radio" name="<?= $name ?>" value="<?= e($opt['value']) ?>"
                  <?= $required ? 'required' : '' ?>>
                <?= e($opt['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($type === 'checkbox'): ?>
          <div class="custom-form-options">
            <?php foreach ($field['options'] ?? [] as $opt): ?>
              <label class="custom-form-option">
                <input type="checkbox" name="<?= $name ?>[]" value="<?= e($opt['value']) ?>">
                <?= e($opt['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($type === 'file' || $type === 'image'): ?>
          <input type="file" id="cf-<?= $id ?>" name="<?= $name ?>"
            accept="<?= e($field['accept'] ?? ($type === 'image' ? 'image/*' : '')) ?>"
            <?= $required ? 'required' : '' ?>>

        <?php else: ?>
          <input type="<?= e($type === 'tel' ? 'tel' : ($type === 'email' ? 'email' : ($type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text')))) ?>"
            id="cf-<?= $id ?>" name="<?= $name ?>"
            placeholder="<?= e($field['placeholder'] ?? '') ?>"
            <?= $required ? 'required' : '' ?>>
        <?php endif; ?>

        <?php if (!empty($field['helpText'])): ?>
          <small class="custom-form-help"><?= e($field['helpText']) ?></small>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  <?php endforeach; ?>

  <button type="submit" class="cta-button primary" id="custom-form-submit">
    <?= e($schema['settings']['submitLabel'] ?? 'Submit') ?>
  </button>
  <p id="custom-form-status" class="custom-form-status" role="status"></p>
</form>
