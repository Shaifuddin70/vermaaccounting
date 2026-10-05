<?php
declare(strict_types=1);
/**
 * Instructions, reference and screenshot inputs for one payment method.
 * Uses the Yes/No follow-up wrappers so custom-form.js shows them only for the
 * selected method and validates them the same way.
 *
 * @var array $field
 * @var array $method
 * @var string $parentId HTML-escaped parent field id
 */
$when = e($method['value']);
?>
<?php if ($method['instructions'] !== ''): ?>
  <div class="yes-no-reason-wrap payment-instructions"
    data-yes-no-reason-for="<?= $parentId ?>"
    data-reason-when="<?= $when ?>"
    data-reason-required="0"
    data-reason-type="note"
    hidden>
    <i class="fas fa-circle-info" aria-hidden="true"></i>
    <p><?= nl2br(e($method['instructions'])) ?></p>
  </div>
<?php endif; ?>

<?php if ($method['reference'] !== 'off'):
  $ref = payment_method_reference_field($field, $method);
  $refId = e($ref['id']);
?>
  <div class="yes-no-reason-wrap payment-input"
    data-yes-no-reason-for="<?= $parentId ?>"
    data-reason-when="<?= $when ?>"
    data-reason-required="<?= $ref['required'] ? '1' : '0' ?>"
    data-reason-type="text"
    hidden>
    <label for="cf-<?= $refId ?>"><?= e($ref['label']) ?><?php if ($ref['required']): ?> <span class="required">*</span><?php endif; ?></label>
    <input type="text"
      id="cf-<?= $refId ?>"
      name="<?= e($ref['name']) ?>"
      maxlength="200"
      autocomplete="off"
      placeholder="<?= e($ref['placeholder']) ?>">
  </div>
<?php endif; ?>

<?php if ($method['screenshot'] !== 'off'):
  $shot = payment_method_screenshot_field($field, $method);
  $shotId = e($shot['id']);
?>
  <div class="yes-no-reason-wrap payment-input"
    data-yes-no-reason-for="<?= $parentId ?>"
    data-reason-when="<?= $when ?>"
    data-reason-required="<?= $shot['required'] ? '1' : '0' ?>"
    data-reason-type="file"
    hidden>
    <label for="cf-<?= $shotId ?>"><?= e($shot['label']) ?><?php if ($shot['required']): ?> <span class="required">*</span><?php endif; ?></label>
    <div class="custom-form-file-field"
      data-file-field="1"
      data-field-id="<?= $shotId ?>"
      data-field-name="<?= e($shot['name']) ?>"
      data-field-type="file"
      data-max-files="<?= (int) $shot['maxFiles'] ?>"
      data-required="0"
      data-accept="<?= e($shot['accept']) ?>">
      <input type="file"
        id="cf-<?= $shotId ?>"
        class="custom-form-file-input"
        accept="<?= e($shot['accept']) ?>"
        multiple>
      <div class="custom-form-file-queue" id="cf-queue-<?= $shotId ?>" aria-live="polite"></div>
      <div class="custom-form-file-tokens" id="cf-tokens-<?= $shotId ?>"></div>
    </div>
    <small class="custom-form-help">Screenshot or PDF receipt, up to <?= (int) $shot['maxFiles'] ?> files.</small>
  </div>
<?php endif; ?>
