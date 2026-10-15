<?php
/** @var array $cms {title, html, status} */
?>
<main class="main-content cms-main cms-not-found-main" id="main">
  <section class="page-header cms-page-header">
    <div class="container">
      <h1><?= CmsRenderer::e((string) $cms['title']) ?></h1>
    </div>
  </section>
  <section class="common-section">
    <div class="container cms-body">
      <?= $cms['html'] ?>
    </div>
  </section>
</main>
