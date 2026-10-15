<?php
/** @var array $cms */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) $c['title'];
$listing = $cms['listing'];
$base = $cms['path'];
$pageUrl = static fn (int $p): string => $p <= 1 ? $base : $base . '?page=' . $p;
?>
<main class="main-content cms-main" id="main">
  <section class="blog-index-hero">
    <div class="container blog-index-hero-inner">
      <div class="blog-index-hero-top">
        <div class="blog-index-hero-copy">
          <span class="blog-article-tag">Insights</span>
          <h1 class="blog-index-title"><?= $e($h1) ?><?= $listing['page'] > 1 ? ' – Page ' . (int) $listing['page'] : '' ?></h1>
          <div class="blog-index-dek cms-body"><?= $cms['body'] ?></div>
          <div class="blog-index-hero-actions">
            <a href="/contact" class="cta-button primary">Book a consultation</a>
            <a href="/resources" class="cta-button secondary">Tax resources</a>
          </div>
          <?php if ($listing['total'] > 0): ?>
            <p class="blog-index-count"><?= (int) $listing['total'] ?> article<?= $listing['total'] === 1 ? '' : 's' ?> published</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="blog-index-section">
    <div class="container blog-index-shell">
      <?php if ($listing['items'] === []): ?>
        <div class="blog-index-empty">
          <h2>Coming soon</h2>
          <p>New articles are on the way. Meanwhile, explore our <a href="/resources">resources</a> or <a href="/contact">book a consultation</a>.</p>
        </div>
      <?php else: ?>
        <div class="blog-grid">
          <?php foreach ($listing['items'] as $__item): ?>
            <?php $__headingTag = 'h2'; include __DIR__ . '/_card.php'; ?>
          <?php endforeach; ?>
        </div>

        <?php if ($listing['total_pages'] > 1): ?>
          <nav class="blog-pagination" aria-label="Article pages">
            <?php if ($listing['page'] > 1): ?>
              <a href="<?= $e($pageUrl($listing['page'] - 1)) ?>" class="cta-button secondary" rel="prev">← Previous</a>
            <?php endif; ?>
            <span class="blog-pagination-pages">
              <?php for ($p = 1; $p <= $listing['total_pages']; $p++): ?>
                <?php if ($p === $listing['page']): ?>
                  <span class="blog-pagination-current" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                  <a href="<?= $e($pageUrl($p)) ?>" class="blog-pagination-num"><?= $p ?></a>
                <?php endif; ?>
              <?php endfor; ?>
            </span>
            <?php if ($listing['page'] < $listing['total_pages']): ?>
              <a href="<?= $e($pageUrl($listing['page'] + 1)) ?>" class="cta-button secondary" rel="next">Next →</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
