<?php
/** @var array $cms */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) $c['title'];
$author = trim((string) ($c['author_name'] ?? '')) ?: 'Rishabh Verma';
$featured = $cms['featured'];
$cats = $cms['categories'];
?>
<main class="main-content cms-main" id="main">
  <article class="blog-post">
    <header class="blog-article-hero">
      <div class="container blog-article-hero-inner">
        <div class="blog-article-hero-grid<?= $featured ? '' : ' blog-article-hero-grid--text-only' ?>">
          <div class="blog-article-hero-copy">
            <?php if ($cats !== []): ?>
              <div class="blog-article-tags">
                <?php foreach (array_slice($cats, 0, 3) as $cat): ?>
                  <span class="blog-article-tag"><?= $e((string) $cat['name']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <h1 class="blog-article-title"><?= $e($h1) ?></h1>

            <?php if (trim((string) ($c['excerpt'] ?? '')) !== ''): ?>
              <p class="blog-article-dek"><?= $e((string) $c['excerpt']) ?></p>
            <?php endif; ?>

            <div class="blog-article-byline">
              <div class="blog-article-author">
                <img class="blog-article-avatar" src="/images/rishab-verma-lg.jpg" alt="<?= $e($author) ?>" width="88" height="88" loading="lazy">
                <div class="blog-article-author-text">
                  <span class="blog-article-author-name"><?= $e($author) ?></span>
                  <span class="blog-article-author-role">Verma Accounting</span>
                </div>
              </div>
              <ul class="blog-article-meta" aria-label="Article details">
                <?php if (!empty($c['published_at'])): ?>
                  <li><time datetime="<?= $e(substr((string) $c['published_at'], 0, 10)) ?>"><?= $e(CmsRenderer::formatDate((string) $c['published_at'])) ?></time></li>
                <?php endif; ?>
                <?php if (!empty($c['published_at']) && !empty($c['updated_at']) && substr((string) $c['updated_at'], 0, 10) > substr((string) $c['published_at'], 0, 10)): ?>
                  <li>Updated <time datetime="<?= $e(substr((string) $c['updated_at'], 0, 10)) ?>"><?= $e(CmsRenderer::formatDate((string) $c['updated_at'])) ?></time></li>
                <?php endif; ?>
                <li><?= $e(CmsRenderer::readingTime((string) $c['body_html'])) ?></li>
              </ul>
            </div>
          </div>

          <?php if ($featured): ?>
            <?php $__figureClass = 'blog-article-hero-media'; $__eager = true; include __DIR__ . '/_featured.php'; ?>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="blog-post-body-section">
      <div class="container blog-post-shell">
        <div class="blog-post-layout">
          <div class="blog-post-content">
            <div class="blog-post-html cms-body">
              <?= $cms['body'] ?>
            </div>
            <?php if ($cms['tags'] !== []): ?>
              <p class="cms-tags">Tags:
                <?php foreach ($cms['tags'] as $tag): ?>
                  <span class="blog-article-tag"><?= $e((string) $tag['name']) ?></span>
                <?php endforeach; ?>
              </p>
            <?php endif; ?>
          </div>
          <aside class="blog-post-aside">
            <div class="blog-aside-card blog-aside-card--cta">
              <span class="blog-aside-eyebrow">Next step</span>
              <h2>Need help with your books or taxes?</h2>
              <p>Verma Accounting supports individuals and businesses across Ontario with clear, CRA-ready guidance.</p>
              <a href="/contact" class="cta-button primary">Book a consultation</a>
              <a href="<?= e(company_phone_href()) ?>" class="cta-button orange"><i class="fas fa-phone" aria-hidden="true"></i> <?= e(company_phone()) ?></a>
            </div>
            <div class="blog-aside-card">
              <span class="blog-aside-eyebrow">Explore</span>
              <h2>More reading</h2>
              <ul class="blog-aside-links">
                <li><a href="/blog">All blog posts</a></li>
                <li><a href="/resources">Tax resources</a></li>
                <li><a href="/services">Our services</a></li>
                <li><a href="/contact">Contact us</a></li>
              </ul>
            </div>
          </aside>
        </div>

        <?php if ($cms['related'] !== []): ?>
          <section class="blog-related" aria-labelledby="blog-related-title">
            <h2 id="blog-related-title">Related articles</h2>
            <div class="blog-grid">
              <?php foreach ($cms['related'] as $__item): ?>
                <?php $__headingTag = 'h3'; include __DIR__ . '/_card.php'; ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </article>
  <?php include __DIR__ . '/_faqs.php'; ?>
</main>
