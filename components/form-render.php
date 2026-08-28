<?php
/** @var array $schema */
/** @var array $form */
$taxYearCfg = $schema['settings']['taxYear'] ?? [];
$taxYearOptions = form_tax_year_options($schema);
$taxYearOn = form_tax_year_enabled($schema) && $taxYearOptions !== [];
$dataMatchOn = form_data_match_enabled($schema);
$dataMatchIds = form_data_match_field_ids($schema);
$dataMatchCfg = $schema['settings']['dataMatch'] ?? [];
$documentSubmissionMode = !empty($documentSubmissionMode);
?>
<div class="custom-form-app" id="custom-form-app"
  data-tax-year="<?= $taxYearOn ? '1' : '0' ?>"
  data-data-match="<?= $dataMatchOn ? '1' : '0' ?>"
  data-document-submission="<?= $documentSubmissionMode ? '1' : '0' ?>">
<?php if ($documentSubmissionMode): ?>
  <div class="doc-service-step" id="doc-service-step">
    <h2 class="doc-service-step-title">Which tax service is this for?</h2>
    <p class="doc-service-step-prompt">Choose one to continue with your document upload.</p>
    <div class="doc-service-options" role="group" aria-label="Tax service type">
      <button type="button" class="doc-service-option" data-service-type="personal">
        <span class="doc-service-option-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
        <span class="doc-service-option-label">Personal tax</span>
        <span class="doc-service-option-desc">T1, personal slips, and individual filings</span>
      </button>
      <button type="button" class="doc-service-option" data-service-type="business">
        <span class="doc-service-option-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
        <span class="doc-service-option-label">Business tax</span>
        <span class="doc-service-option-desc">Corporate, GST/HST, payroll, and business records</span>
      </button>
    </div>
  </div>
<?php endif; ?>
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

<form class="custom-form" id="custom-form" enctype="multipart/form-data" novalidate<?= ($taxYearOn || $documentSubmissionMode) ? ' hidden' : '' ?>>
  <input type="hidden" name="form_slug" value="<?= e($form['slug']) ?>">
  <input type="hidden" name="upload_session" id="upload-session" value="">
  <?php if ($documentSubmissionMode): ?>
    <input type="hidden" name="tax_service_type" id="tax-service-type-input" value="">
    <div class="doc-service-selected-bar" id="doc-service-selected-bar" hidden>
      <span>Service type: <strong id="doc-service-selected-label"></strong></span>
      <button type="button" class="doc-service-change-btn" id="doc-service-change-btn">Change</button>
    </div>
  <?php endif; ?>
  <?php if ($taxYearOn): ?>
    <input type="hidden" name="tax_year" id="tax-year-input" value="">
    <div class="tax-year-selected-bar" id="tax-year-selected-bar" hidden>
      <span>Filing for tax year: <strong id="tax-year-selected-label"></strong></span>
      <button type="button" class="tax-year-change-btn" id="tax-year-change-btn">Change year</button>
    </div>
  <?php endif; ?>

  <?php
    $formPages = form_schema_pages($schema);
    $multiPage = count($formPages) > 1;
  ?>
  <?php if ($multiPage): ?>
    <div class="custom-form-progress" id="form-page-progress" aria-live="polite">
      <div class="custom-form-progress-meta">
        <span class="custom-form-progress-step" id="form-page-step-label">Step 1 of <?= count($formPages) ?></span>
        <span class="custom-form-progress-title" id="form-page-title-label"><?= e($formPages[0]['title']) ?></span>
      </div>
      <div class="custom-form-progress-track" aria-hidden="true">
        <div class="custom-form-progress-fill" id="form-page-progress-fill" style="width:<?= round(100 / count($formPages), 2) ?>%;"></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="custom-form-pages" id="form-pages" data-multipage="<?= $multiPage ? '1' : '0' ?>">
    <?php foreach ($formPages as $pageIndex => $page): ?>
      <section
        class="custom-form-page"
        data-page-index="<?= (int) $pageIndex ?>"
        data-page-title="<?= e($page['title']) ?>"
        <?= $pageIndex > 0 ? 'hidden' : '' ?>>
        <div class="custom-form-grid">
          <?php foreach ($page['fields'] as $field): ?>
            <?php include __DIR__ . '/form-field.php'; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="custom-form-actions<?= $multiPage ? ' custom-form-actions--paged' : '' ?>">
    <div class="custom-form-nav">
      <?php if ($multiPage): ?>
        <button type="button" class="cta-button secondary" id="form-page-prev" hidden>Back</button>
        <button type="button" class="cta-button primary" id="form-page-next">Next</button>
      <?php endif; ?>
      <button type="submit" class="cta-button primary" id="custom-form-submit"<?= $multiPage ? ' hidden' : '' ?>>
        <svg class="custom-form-submit-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
        <span><?= e($schema['settings']['submitLabel'] ?? 'Submit') ?></span>
      </button>
    </div>
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
