<?php
/** @var array $cms  The body supplies its own <h1> and sections. */
?>
<main class="main-content" id="main">
  <?= $cms['body'] ?>
  <?php include __DIR__ . '/_faqs.php'; ?>
</main>
