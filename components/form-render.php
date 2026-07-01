<?php
/** @var array $schema */
/** @var array $form */
$taxYearCfg = $schema['settings']['taxYear'] ?? [];
$taxYearOptions = form_tax_year_options($schema);
$taxYearOn = form_tax_year_enabled($schema) && $taxYearOptions !== [];
$dataMatchOn = form_data_match_enabled($schema);
$dataMatchIds = form_data_match_field_ids($schema);
$dataMatchCfg = $schema['settings']['dataMatch'] ?? [];
?>
<div class="custom-form-app" id="custom-form-app"
  data-tax-year="<?= $taxYearOn ? '1' : '0' ?>"
  data-data-match="<?= $dataMatchOn ? '1' : '0' ?>">
<?php if ($taxYearOn): ?>
  <div class="tax-year-step" id="tax-year-step">
    <h2 class="tax-year-step-title"><?= e($taxYearCfg['label']) ?></h2>
    <p class="tax-year-step-prompt"><?= e($taxYearCfg['prompt']) ?></p>
    <div class="tax-year-dropdown-wrap">
      <label for="tax-year-select" class="tax-year-select-label">Tax year</label>
      <select id="tax-year-select" class="tax-year-select" aria-label="<?= e($taxYearCfg['label']) ?>">
        <option value="">Select a year…</option>
        <?php foreach ($taxYearOptions as $year): ?>
          <option value="<?= (int) $year ?>"><?= (int) $year ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="cta-button primary tax-year-continue-btn" id="tax-year-continue-btn">
        Continue
      </button>
    </div>
  </div>
<?php endif; ?>

<form class="custom-form" id="custom-form" enctype="multipart/form-data" novalidate<?= $taxYearOn ? ' hidden' : '' ?>>
  <input type="hidden" name="form_slug" value="<?= e($form['slug']) ?>">
  <input type="hidden" name="upload_session" id="upload-session" value="">
  <?php if ($taxYearOn): ?>
    <input type="hidden" name="tax_year" id="tax-year-input" value="">
    <div class="tax-year-selected-bar" id="tax-year-selected-bar" hidden>
      <span>Filing for tax year: <strong id="tax-year-selected-label"></strong></span>
      <button type="button" class="tax-year-change-btn" id="tax-year-change-btn">Change year</button>
    </div>
  <?php endif; ?>

  <div class="custom-form-grid">
  <?php foreach ($schema['fields'] as $field):
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
          $reasonWhen = $field['reasonWhen'] ?? '';
          $reasonName = $field['name'] . '_reason';
          $reasonRequired = !empty($field['reasonRequired']);
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
          <?php if ($reasonWhen !== ''): ?>
            <div class="yes-no-reason-wrap"
              data-yes-no-reason-for="<?= $id ?>"
              data-reason-when="<?= e($reasonWhen) ?>"
              data-reason-required="<?= $reasonRequired ? '1' : '0' ?>"
              hidden>
              <label for="cf-<?= $id ?>-reason"><?= e($field['reasonLabel'] ?? 'Please explain your answer') ?></label>
              <textarea id="cf-<?= $id ?>-reason"
                name="<?= e($reasonName) ?>"
                rows="3"
                placeholder="<?= e($field['reasonPlaceholder'] ?? '') ?>"></textarea>
            </div>
          <?php endif; ?>

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
          $accept = $field['accept'] ?? ($type === 'image' ? 'image/*' : '');
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
            <?php if ($maxFiles > 1): ?>
              <small class="custom-form-help">You can upload up to <?= (int) $maxFiles ?> files. Upload starts as soon as you select each file.</small>
            <?php else: ?>
              <small class="custom-form-help">Upload starts as soon as you select a file.</small>
            <?php endif; ?>
          </div>

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
  </div>

  <div class="custom-form-actions">
    <button type="submit" class="cta-button primary" id="custom-form-submit">
      <svg class="custom-form-submit-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
      <span><?= e($schema['settings']['submitLabel'] ?? 'Submit') ?></span>
    </button>
    <p class="custom-form-secure-note">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Your information is encrypted and kept confidential.
    </p>
  </div>
  <p id="custom-form-status" class="custom-form-status" role="status" aria-live="polite"></p>
</form>

<div id="custom-form-success" class="custom-form-success" hidden aria-live="polite">
  <div class="custom-form-success-icon" aria-hidden="true">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
  </div>
  <h2 class="custom-form-success-title">Submission received</h2>
  <p id="custom-form-success-message" class="custom-form-success-message"></p>
  <button type="button" class="cta-button secondary custom-form-success-reset" id="custom-form-success-reset">
    Submit another response
  </button>
</div>

<?php if ($dataMatchOn): ?>
  <div id="prefill-modal" class="prefill-modal" hidden aria-modal="true" role="dialog" aria-labelledby="prefill-modal-title">
    <div class="prefill-modal-backdrop" data-prefill-close></div>
    <div class="prefill-modal-panel">
      <h3 id="prefill-modal-title" class="prefill-modal-title"><?= e($dataMatchCfg['title'] ?? '') ?></h3>
      <p class="prefill-modal-message"><?= e($dataMatchCfg['message'] ?? '') ?></p>
      <p id="prefill-modal-meta" class="prefill-modal-meta"></p>
      <div class="prefill-modal-actions">
        <button type="button" class="cta-button primary" id="prefill-confirm-btn">
          <?= e($dataMatchCfg['confirmLabel'] ?? 'Yes, fill the form') ?>
        </button>
        <button type="button" class="prefill-decline-btn" id="prefill-decline-btn">
          <?= e($dataMatchCfg['declineLabel'] ?? 'No, start fresh') ?>
        </button>
      </div>
    </div>
  </div>
<?php endif; ?>
</div>
