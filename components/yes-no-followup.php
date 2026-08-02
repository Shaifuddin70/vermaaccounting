<?php
declare(strict_types=1);
/**
 * Render a Yes/No follow-up control (no outer field wrapper).
 *
 * @var array $parentField
 * @var array $followUp
 * @var string $parentId HTML-escaped parent field id
 */
$pseudo = yes_no_follow_up_as_field($parentField, $followUp);
$type = (string) $pseudo['type'];
$name = e((string) $pseudo['name']);
$id = e((string) $pseudo['id']);
$required = !empty($pseudo['required']);
$label = (string) $pseudo['label'];
?>
<div class="yes-no-reason-wrap"
  data-yes-no-reason-for="<?= $parentId ?>"
  data-reason-when="<?= e((string) $followUp['when']) ?>"
  data-reason-required="<?= $required ? '1' : '0' ?>"
  data-reason-type="<?= e($type) ?>"
  hidden>
  <label for="cf-<?= $id ?>"><?= e($label) ?></label>

  <?php if ($type === 'textarea'): ?>
    <textarea id="cf-<?= $id ?>"
      name="<?= $name ?>"
      rows="3"
      placeholder="<?= e((string) ($pseudo['placeholder'] ?? '')) ?>"></textarea>

  <?php elseif ($type === 'select'): ?>
    <select id="cf-<?= $id ?>" name="<?= $name ?>">
      <option value="">Select…</option>
      <?php foreach ($pseudo['options'] ?? [] as $opt): ?>
        <option value="<?= e((string) $opt['value']) ?>"><?= e((string) $opt['label']) ?></option>
      <?php endforeach; ?>
    </select>

  <?php elseif ($type === 'radio'): ?>
    <div class="custom-form-options">
      <?php foreach ($pseudo['options'] ?? [] as $i => $opt):
        $optId = 'cf-' . $id . '-opt-' . $i;
      ?>
        <label class="custom-form-option">
          <input type="radio" name="<?= $name ?>" id="<?= e($optId) ?>" value="<?= e((string) $opt['value']) ?>">
          <?= e((string) $opt['label']) ?>
        </label>
      <?php endforeach; ?>
    </div>

  <?php elseif ($type === 'checkbox'): ?>
    <div class="custom-form-options">
      <?php foreach ($pseudo['options'] ?? [] as $opt): ?>
        <label class="custom-form-option">
          <input type="checkbox" name="<?= $name ?>[]" value="<?= e((string) $opt['value']) ?>">
          <?= e((string) $opt['label']) ?>
        </label>
      <?php endforeach; ?>
    </div>

  <?php elseif ($type === 'partners'):
    $partnerInput = normalize_partner_input_type($pseudo);
    $fieldPartners = partners_for_field($pseudo);
  ?>
    <?php if ($partnerInput === 'select'): ?>
      <select id="cf-<?= $id ?>" name="<?= $name ?>">
        <option value="">Select…</option>
        <?php foreach ($fieldPartners as $partner):
          $code = trim((string) ($partner['reference_code'] ?? ''));
          $plabel = $partner['name'] . ($code !== '' ? ' (' . $code . ')' : '');
        ?>
          <option value="<?= (int) $partner['id'] ?>"><?= e($plabel) ?></option>
        <?php endforeach; ?>
      </select>
    <?php else: ?>
      <input type="<?= $partnerInput === 'number' ? 'number' : 'text' ?>"
        id="cf-<?= $id ?>" name="<?= $name ?>"
        placeholder="<?= e((string) ($pseudo['placeholder'] ?? 'Enter partner reference code')) ?>"
        <?= $partnerInput === 'number' ? 'inputmode="numeric" pattern="[0-9]*"' : '' ?>>
    <?php endif; ?>

  <?php elseif ($type === 'file' || $type === 'image'):
    $maxFiles = max(1, min(10, (int) ($pseudo['maxFiles'] ?? 5)));
    $accept = trim((string) ($pseudo['accept'] ?? ''));
    if ($accept === '' || ($type === 'file' && preg_match('/^image\/(\*|jpeg|png|gif|webp)(,image\/(jpeg|png|gif|webp))*$/i', $accept))) {
      $accept = $type === 'image' ? 'image/*' : form_file_accept_default();
    }
  ?>
    <div class="custom-form-file-field"
      data-file-field="1"
      data-field-id="<?= $id ?>"
      data-field-name="<?= $name ?>"
      data-field-type="<?= e($type) ?>"
      data-max-files="<?= $maxFiles ?>"
      data-required="<?= $required ? '1' : '0' ?>"
      data-accept="<?= e($accept) ?>">
      <input type="file"
        id="cf-<?= $id ?>"
        class="custom-form-file-input"
        accept="<?= e($accept) ?>"
        <?= $maxFiles > 1 ? 'multiple' : '' ?>>
      <div class="custom-form-file-queue" id="cf-queue-<?= $id ?>" aria-live="polite"></div>
      <div class="custom-form-file-tokens" id="cf-tokens-<?= $id ?>"></div>
    </div>

  <?php elseif ($type === 'tel'):
    $phone = normalize_phone_field_settings($pseudo);
    $phoneCountries = phone_countries();
    $phonePlaceholder = ($pseudo['placeholder'] ?? '') !== ''
        ? (string) $pseudo['placeholder']
        : $phone['format'];
  ?>
    <div class="custom-form-phone" data-phone-field>
      <?php if ($phone['allowSelect']): ?>
        <label class="visually-hidden" for="cf-<?= $id ?>-cc">Country code</label>
        <select id="cf-<?= $id ?>-cc"
          class="custom-form-phone-cc"
          data-phone-cc
          autocomplete="tel-country-code"
          aria-label="Country code">
          <?php foreach ($phoneCountries as $code => $meta): ?>
            <option value="<?= e($code) ?>"
              data-dial="<?= e($meta['dial']) ?>"
              data-format="<?= e($meta['format']) ?>"
              <?= $code === $phone['country'] ? 'selected' : '' ?>>
              <?= e($meta['dial'] . ' ' . $code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <span class="custom-form-phone-cc-static" data-phone-dial="<?= e($phone['dial']) ?>"><?= e($phone['dial']) ?></span>
      <?php endif; ?>
      <input type="tel"
        id="cf-<?= $id ?>"
        class="custom-form-phone-local"
        data-phone-local
        inputmode="numeric"
        autocomplete="tel-national"
        placeholder="<?= e($phonePlaceholder) ?>"
        data-number-format="<?= e($phone['format']) ?>"
        maxlength="<?= (int) strlen($phone['format']) ?>">
      <input type="hidden"
        name="<?= $name ?>"
        data-phone-combined
        value=""
        data-phone-default-country="<?= e($phone['country']) ?>"
        data-phone-default-dial="<?= e($phone['dial']) ?>"
        data-phone-default-format="<?= e($phone['format']) ?>">
    </div>

  <?php else:
    $numberFormat = $type === 'number'
        ? normalize_number_format((string) ($pseudo['numberFormat'] ?? ''))
        : '';
    $minAge = $type === 'date' ? normalize_min_age($pseudo['minAge'] ?? 0) : 0;
    $dateMax = $type === 'date' ? date_max_for_min_age($minAge) : null;
    $inputType = $type === 'email'
        ? 'email'
        : ($type === 'date'
            ? 'date'
            : ($type === 'number' && $numberFormat === ''
                ? 'number'
                : 'text'));
  ?>
    <input type="<?= e($inputType) ?>"
      id="cf-<?= $id ?>"
      name="<?= $name ?>"
      placeholder="<?= e(($pseudo['placeholder'] ?? '') !== '' ? (string) $pseudo['placeholder'] : $numberFormat) ?>"
      <?php if ($dateMax !== null): ?>
        max="<?= e($dateMax) ?>"
        data-min-age="<?= (int) $minAge ?>"
      <?php endif; ?>
      <?php if ($numberFormat !== ''): ?>
        inputmode="numeric"
        autocomplete="off"
        data-number-format="<?= e($numberFormat) ?>"
        maxlength="<?= (int) strlen($numberFormat) ?>"
      <?php elseif ($type === 'number'): ?>
        inputmode="decimal"
      <?php endif; ?>>
    <?php if ($dateMax !== null): ?>
      <small class="custom-form-help">Must be at least <?= (int) $minAge ?> years old.</small>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($pseudo['helpText'])): ?>
    <small class="custom-form-help"><?= e((string) $pseudo['helpText']) ?></small>
  <?php endif; ?>
</div>
