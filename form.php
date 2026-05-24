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
  <link rel="stylesheet" href="/css/custom-form.css">
</head>
<body class="form-embed-body">
  <div class="form-embed-header">
    <h1><?= e($form['title']) ?></h1>
    <?php if ($form['description']): ?>
      <p><?= e($form['description']) ?></p>
    <?php endif; ?>
  </div>
  <div class="custom-form-wrap">
    <?php include __DIR__ . '/components/form-render.php'; ?>
  </div>
  <script>window.CUSTOM_FORM_SCHEMA = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;</script>
  <script src="/components/js/custom-form.js"></script>
</body>
</html>
    <?php
    exit;
}

$pageTitle = $form['title'];
include __DIR__ . '/components/header.php';
?>
<main class="main-content main-content--form-page">
  <section class="page-header page-header--compact">
    <div class="container">
      <h1><?= e($form['title']) ?></h1>
      <?php if ($form['description']): ?>
        <p><?= e($form['description']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="contact-form-section contact-form-section--compact">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="custom-form-wrap verma-custom-form-page">
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
