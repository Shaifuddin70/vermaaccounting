<?php
declare(strict_types=1);
/** @var array $field */
/** @var bool $dataMatchOn */
/** @var list<string> $dataMatchIds */

$type = $field['type'];
$name = e($field['name']);
$id = e($field['id']);
$required = !empty($field['required']);
$condJson = !empty($field['conditions']) ? e(json_encode($field['conditions'])) : '';
$layout = form_field_uses_half_column($type, $field) ? 'custom-form-field--half' : 'custom-form-field--full';
$wrapAttrs = 'class="custom-form-field ' . $layout . '" data-field-id="' . $id . '" data-field-name="' . $name . '"';
if ($dataMatchOn && in_array($field['id'], $dataMatchIds, true)) {
    $wrapAttrs .= ' data-match-key="1"';
}
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

        <?php elseif ($type === 'radio'): ?>
          <div class="custom-form-options">
            <?php foreach ($field['options'] ?? [] as $opt): ?>
              <label class="custom-form-option">
                <input type="radio" name="<?= $name ?>" value="<?= e($opt['value']) ?>"
                  <?= $required ? 'required' : '' ?>>
                <?= e($opt['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($type === 'yes_no'):
          $followUps = yes_no_follow_ups($field);
        ?>
          <div class="yes-no-toggle" data-yes-no-field="<?= $id ?>" role="group" aria-label="<?= e($field['label']) ?>">
            <?php foreach ($field['options'] ?? [] as $i => $opt):
              $optId = 'cf-' . $id . '-' . e($opt['value']);
            ?>
              <div class="yes-no-toggle-option">
                <input type="radio"
                  class="yes-no-toggle-input"
                  name="<?= $name ?>"
                  id="<?= $optId ?>"
                  value="<?= e($opt['value']) ?>"
                  <?= ($required && $i === 0) ? 'required' : '' ?>>
                <label class="yes-no-toggle-btn" for="<?= $optId ?>"><?= e($opt['label']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <?php foreach ($followUps as $followUp):
            $fuId = e((string) $followUp['id']);
            $reasonType = normalize_yes_no_reason_type($followUp['type'] ?? 'textarea');
            $reasonInputType = in_array($reasonType, ['email', 'tel', 'number', 'date'], true) ? $reasonType : 'text';
            $reasonName = yes_no_follow_up_storage_key((string) $field['name'], $followUp);
            $controlId = 'cf-' . $id . '-fu-' . $fuId;
          ?>
            <div class="yes-no-reason-wrap"
              data-yes-no-reason-for="<?= $id ?>"
              data-reason-when="<?= e($followUp['when']) ?>"
              data-reason-required="<?= !empty($followUp['required']) ? '1' : '0' ?>"
              data-reason-type="<?= e($reasonType) ?>"
              hidden>
              <label for="<?= $controlId ?>"><?= e($followUp['label']) ?></label>
              <?php if ($reasonType === 'textarea'): ?>
                <textarea id="<?= $controlId ?>"
                  name="<?= e($reasonName) ?>"
                  rows="3"
                  placeholder="<?= e($followUp['placeholder'] ?? '') ?>"></textarea>
              <?php else: ?>
                <input type="<?= e($reasonInputType) ?>"
                  id="<?= $controlId ?>"
                  name="<?= e($reasonName) ?>"
                  placeholder="<?= e($followUp['placeholder'] ?? '') ?>"
                  <?php if ($reasonType === 'number'): ?>inputmode="decimal"<?php endif; ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

        <?php elseif ($type === 'checkbox'): ?>
          <div class="custom-form-options">
            <?php foreach ($field['options'] ?? [] as $opt): ?>
              <label class="custom-form-option">
                <input type="checkbox" name="<?= $name ?>[]" value="<?= e($opt['value']) ?>">
                <?= e($opt['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($type === 'partners'):
          $partnerInput = normalize_partner_input_type($field);
          $fieldPartners = partners_for_field($field);
        ?>
          <?php if ($partnerInput === 'select'): ?>
            <select id="cf-<?= $id ?>" name="<?= $name ?>" <?= $required ? 'required' : '' ?>>
              <option value="">Select…</option>
              <?php foreach ($fieldPartners as $partner):
                $code = trim((string) ($partner['reference_code'] ?? ''));
                $label = $partner['name'] . ($code !== '' ? ' (' . $code . ')' : '');
              ?>
                <option value="<?= (int) $partner['id'] ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($fieldPartners === []): ?>
              <small class="custom-form-help">No partners configured for this field.</small>
            <?php endif; ?>
          <?php else: ?>
            <input type="<?= $partnerInput === 'number' ? 'number' : 'text' ?>"
              id="cf-<?= $id ?>" name="<?= $name ?>"
              placeholder="<?= e($field['placeholder'] ?? 'Enter partner reference code') ?>"
              <?= $required ? 'required' : '' ?>
              <?= $partnerInput === 'number' ? 'inputmode="numeric" pattern="[0-9]*"' : '' ?>>
          <?php endif; ?>

        <?php elseif ($type === 'file' || $type === 'image'):
          $maxFiles = max(1, min(10, (int) ($field['maxFiles'] ?? 5)));
          $accept = trim((string) ($field['accept'] ?? ''));
          // File fields should accept documents even if accept was left as image/*
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
            <?php if ($type === 'file'): ?>
              <small class="custom-form-help">
                <?= $maxFiles > 1 ? 'You can upload up to ' . (int) $maxFiles . ' files. ' : '' ?>
                Images, PDF, ZIP, and documents accepted. Upload starts as soon as you select each file.
              </small>
            <?php elseif ($maxFiles > 1): ?>
              <small class="custom-form-help">You can upload up to <?= (int) $maxFiles ?> images. Upload starts as soon as you select each file.</small>
            <?php else: ?>
              <small class="custom-form-help">Upload starts as soon as you select a file.</small>
            <?php endif; ?>
          </div>

        <?php elseif ($type === 'tel'): ?>
          <?php
            $phone = normalize_phone_field_settings($field);
            $phoneCountries = phone_countries();
            $phonePlaceholder = ($field['placeholder'] ?? '') !== ''
                ? (string) $field['placeholder']
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
              maxlength="<?= (int) strlen($phone['format']) ?>"
              <?= $required ? 'required' : '' ?>>
            <input type="hidden"
              name="<?= $name ?>"
              data-phone-combined
              value=""
              data-phone-default-country="<?= e($phone['country']) ?>"
              data-phone-default-dial="<?= e($phone['dial']) ?>"
              data-phone-default-format="<?= e($phone['format']) ?>">
          </div>

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
                    : ($type === 'number' && $numberFormat === ''
                        ? 'number'
                        : 'text'));
          ?>
          <input type="<?= e($inputType) ?>"
            id="cf-<?= $id ?>" name="<?= $name ?>"
            placeholder="<?= e(($field['placeholder'] ?? '') !== '' ? (string) $field['placeholder'] : $numberFormat) ?>"
            <?= $required ? 'required' : '' ?>
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

        <?php if (!empty($field['helpText'])): ?>
          <small class="custom-form-help"><?= e($field['helpText']) ?></small>
        <?php endif; ?>
      <?php endif; ?>

    </div>
