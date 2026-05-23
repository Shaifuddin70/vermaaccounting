<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
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
$pageTitle = $form['title'];
include __DIR__ . '/components/header.php';
?>
<main class="main-content">
  <section class="page-header">
    <div class="container">
      <h1><?= e($form['title']) ?></h1>
      <?php if ($form['description']): ?>
        <p><?= e($form['description']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="contact-form-section">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="custom-form-wrap">
            <?php include __DIR__ . '/components/form-render.php'; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<link rel="stylesheet" href="/css/custom-form.css">
<script>
  window.CUSTOM_FORM_SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/components/js/custom-form.js"></script>
<?php include __DIR__ . '/components/footer.php'; ?>
