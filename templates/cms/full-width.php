<?php
/** @var array $cms  The body supplies its own sections; page title is rendered as H1 when body has none. */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) ($c['title'] ?? '');
$bodyHasH1 = (bool) preg_match('/<h1\b/i', (string) ($cms['body'] ?? ''));
?>
<main class="main-content" id="main">
  <?php if (!$bodyHasH1 && $h1 !== ''): ?>
    <section class="page-header">
      <div class="container">
        <h1><?= $e($h1) ?></h1>
      </div>
    </section>
  <?php endif; ?>
  <?= $cms['body'] ?>
  <?php include __DIR__ . '/_faqs.php'; ?>
</main>
