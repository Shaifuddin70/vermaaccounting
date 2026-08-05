<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

if ($slug === '') {
    http_response_code(404);
    echo 'Form not found.';
    exit;
}

$repo = new FormRepository();
$form = $repo->findBySlug($slug, true);

if (!$form) {
    http_response_code(404);
    echo 'Form not found or not published.';
    exit;
}

$schema = $repo->decodeSchema($form);

if ($embed) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($form['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/custom-form.css?v=17">
</head>
<body class="form-embed-body">
  <div class="form-embed-header">
    <a href="<?= e(app_base_url() ?: 'https://vermaaccounting.ca') ?>" class="form-embed-logo" aria-label="Verma Accounting home">
      <?= brand_logo_img_html('form-embed-logo-img', 180, 44) ?>
    </a>
    <h1><?= e($form['title']) ?></h1>
    <?php if ($form['description']): ?>
      <p><?= e($form['description']) ?></p>
    <?php endif; ?>
  </div>
  <div class="custom-form-wrap">
    <?php include __DIR__ . '/components/form-render.php'; ?>
  </div>
  <script>window.CUSTOM_FORM_SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;</script>
  <script src="/components/js/custom-form.js?v=19"></script>
</body>
</html>
    <?php
    exit;
}

$pageTitle = $form['title'];
$fieldCount = 0;
foreach ($schema['fields'] as $f) {
    if (!in_array($f['type'], ['heading', 'paragraph'], true)) {
        $fieldCount++;
    }
}
include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="/css/custom-form.css?v=17">
<main class="main-content vf-page">
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
              <?php if ($form['description']): ?>
                <p class="vf-card-desc"><?= e($form['description']) ?></p>
              <?php endif; ?>
            </div>
          </header>
          <div class="vf-card-body">
            <?php include __DIR__ . '/components/form-render.php'; ?>
          </div>
        </div>
      </div>

      <aside class="vf-aside">
        <div class="vf-aside-card">
          <div class="vf-aside-badge">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <div>
              <strong>Secure &amp; confidential</strong>
              <span>Your data is encrypted in transit and only seen by our team.</span>
            </div>
          </div>
        </div>

        <div class="vf-aside-card">
          <h2 class="vf-aside-title">What happens next</h2>
          <ol class="vf-steps">
            <li class="vf-step">
              <span class="vf-step-num">1</span>
              <span class="vf-step-text"><strong>Complete this form</strong><br>Fill in <?= $fieldCount > 0 ? $fieldCount . ' field' . ($fieldCount === 1 ? '' : 's') : 'the details' ?> and submit.</span>
            </li>
            <li class="vf-step">
              <span class="vf-step-num">2</span>
              <span class="vf-step-text"><strong>We review it</strong><br>Our team checks your submission for completeness.</span>
            </li>
            <li class="vf-step">
              <span class="vf-step-num">3</span>
              <span class="vf-step-text"><strong>We follow up</strong><br>We&rsquo;ll reach out with the next steps.</span>
            </li>
          </ol>
        </div>

        <div class="vf-aside-help">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>Required fields are marked with <span class="vf-req-mark">*</span></span>
        </div>
      </aside>
    </div>
  </div>
</main>

<script>
  window.CUSTOM_FORM_SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/components/js/custom-form.js?v=19"></script>
<?php include __DIR__ . '/components/footer.php'; ?>
