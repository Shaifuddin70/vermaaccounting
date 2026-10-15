<?php
/** @var array $cms */
$__faqs = $cms['faqs'] ?? [];
if ($__faqs === []) {
    return;
}
?>
<section class="faq-section common-section cms-faqs" aria-labelledby="cms-faq-title">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title" id="cms-faq-title">Frequently Asked Questions</h2>
    </div>
    <?php foreach ($__faqs as $__faq): ?>
      <div class="faq-item">
        <button type="button" class="faq-question" onclick="toggleFAQ(this)" aria-expanded="false">
          <span><?= CmsRenderer::e((string) $__faq['question']) ?></span>
          <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="faq-answer">
          <p><?= nl2br(CmsRenderer::e((string) $__faq['answer'])) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
