<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

ensure_document_submission_form();

$repo = new FormRepository();
$form = $repo->findBySlug(document_submission_form_slug(), true);

if (!$form) {
    http_response_code(404);
    echo 'Document submission is not available right now.';
    exit;
}

$schema = $repo->decodeSchema($form);
$pageTitle = 'Submit Tax Documents';
$seoTitle = 'Submit Tax Documents | Verma Accounting';
$seoDescription = 'Securely upload personal or business tax documents to Verma Accounting using your customer ID reference number.';

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="/css/custom-form.css?v=19">
<main class="main-content vf-page doc-submit-page">
  <div class="vf-container">
    <div class="vf-layout">
      <div class="vf-main">
        <div class="vf-card">
          <header class="vf-card-header">
            <a href="<?= e(app_base_url() ?: '/') ?>" class="vf-card-header-logo" aria-label="Verma Accounting home">
              <?= brand_logo_img_html('vf-card-logo-img', 160, 36) ?>
            </a>
            <div class="vf-card-header-text">
              <h1 class="vf-card-title"><?= e($form['title']) ?></h1>
              <p class="vf-card-desc">Choose your tax service type, enter your customer ID, and upload your files securely.</p>
            </div>
          </header>
          <div class="vf-card-body">
            <?php
              $documentSubmissionMode = true;
              include __DIR__ . '/components/form-render.php';
            ?>
          </div>
        </div>
      </div>

      <aside class="vf-aside">
        <div class="vf-aside-card">
          <div class="vf-aside-badge">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <div>
              <strong>Secure upload</strong>
              <span>Files are encrypted in transit and only accessible to our team.</span>
            </div>
          </div>
        </div>

        <div class="vf-aside-card">
          <h2 class="vf-aside-title">How it works</h2>
          <ol class="vf-steps">
            <li class="vf-step">
              <span class="vf-step-num">1</span>
              <span class="vf-step-text"><strong>Choose service type</strong><br>Personal tax or business tax.</span>
            </li>
            <li class="vf-step">
              <span class="vf-step-num">2</span>
              <span class="vf-step-text"><strong>Enter client ID or email</strong><br>Use the client ID number or email address on your account.</span>
            </li>
            <li class="vf-step">
              <span class="vf-step-num">3</span>
              <span class="vf-step-text"><strong>Upload files</strong><br>Submit PDFs, photos, or ZIP archives.</span>
            </li>
          </ol>
        </div>

        <div class="vf-aside-help">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>Need your customer ID? <a href="/contact">Contact us</a> and we&rsquo;ll help.</span>
        </div>
      </aside>
    </div>
  </div>
</main>

<script>
  window.CUSTOM_FORM_SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/components/js/custom-form.js?v=19"></script>
<script src="/components/js/document-submission.js?v=2"></script>
<?php include __DIR__ . '/components/footer.php'; ?>
