<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$repo = new BlogRepository();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$total = $repo->count('published');
$totalPages = max(1, (int) ceil($total / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$blogs = $repo->published($perPage, $offset);

$featured = null;
$gridBlogs = $blogs;
if ($page === 1 && $blogs !== []) {
    $featured = array_shift($gridBlogs);
}

$seoTitle = 'Accounting & Tax Blog | Verma Accounting Ontario';
$seoDescription = 'Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses from Verma Accounting.';

include 'components/header.php';
?>
<main class="main-content">
  <section class="blog-index-hero">
    <div class="container blog-index-hero-inner">
      <nav class="blog-article-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="blog-article-sep" aria-hidden="true">/</span>
        <span>Blog</span>
      </nav>

      <div class="blog-index-hero-top">
        <div class="blog-index-hero-copy">
          <span class="blog-article-tag">Insights</span>
          <h1 class="blog-index-title">Tax &amp; accounting guides for Ontario</h1>
          <p class="blog-index-dek">
            Practical tips on bookkeeping, payroll, personal and corporate tax, and growing your business — written by the Verma Accounting team.
          </p>
          <div class="blog-index-hero-actions">
            <a href="/contact" class="cta-button primary">Book a consultation</a>
            <a href="/resources" class="cta-button secondary">Tax resources</a>
          </div>
          <?php if ($total > 0): ?>
            <p class="blog-index-count"><?= (int) $total ?> article<?= $total === 1 ? '' : 's' ?> published</p>
          <?php endif; ?>
        </div>

        <?php if ($featured !== null): ?>
          <a href="<?= e(blog_public_url($featured)) ?>" class="blog-index-featured">
            <div class="blog-index-featured-media">
              <?php if (!empty($featured['featured_image'])): ?>
                <img
                  src="<?= e((string) $featured['featured_image']) ?>"
                  alt="<?= e((string) $featured['title']) ?>"
                  width="960"
                  height="640"
                  fetchpriority="high">
              <?php else: ?>
                <div class="blog-card-media-placeholder" aria-hidden="true"></div>
              <?php endif; ?>
              <span class="blog-index-featured-badge">Latest</span>
            </div>
            <div class="blog-index-featured-body">
              <?php if (!empty($featured['categories'])): ?>
                <div class="blog-card-cats"><?= e(implode(' · ', array_slice($featured['categories'], 0, 2))) ?></div>
              <?php endif; ?>
              <h2 class="blog-index-featured-title"><?= e((string) $featured['title']) ?></h2>
              <p class="blog-index-featured-excerpt"><?= e((string) ($featured['excerpt'] ?? '')) ?></p>
              <div class="blog-index-featured-meta">
                <img
                  class="blog-article-avatar blog-article-avatar--sm"
                  src="<?= e(asset('images/rishab-verma-lg.jpg')) ?>"
                  alt=""
                  width="64"
                  height="64"
                  loading="lazy">
                <div>
                  <span class="blog-index-featured-author"><?= e((string) (($featured['author_name'] ?? '') !== '' ? $featured['author_name'] : 'Rishabh Verma')) ?></span>
                  <span class="blog-index-featured-sub">
                    <?php if (!empty($featured['publish_date'])): ?>
                      <time datetime="<?= e((string) $featured['publish_date']) ?>"><?= e(blog_format_date($featured['publish_date'])) ?></time>
                      ·
                    <?php endif; ?>
                    <?= e(blog_reading_time($featured)) ?>
                  </span>
                </div>
              </div>
            </div>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="blog-index-section">
    <div class="container blog-index-shell">
      <?php if ($blogs === []): ?>
        <div class="blog-index-empty">
          <h2>Coming soon</h2>
          <p>New articles are on the way. Meanwhile, explore our <a href="/resources">resources</a> or <a href="/contact">book a consultation</a>.</p>
        </div>
      <?php else: ?>
        <?php if ($gridBlogs !== []): ?>
          <div class="blog-index-section-head">
            <h2><?= $featured !== null ? 'More articles' : 'All articles' ?></h2>
            <p>Browse guides on tax, bookkeeping, payroll, and business setup.</p>
          </div>
          <div class="blog-grid">
            <?php foreach ($gridBlogs as $blog): ?>
              <article class="blog-card">
                <a href="<?= e(blog_public_url($blog)) ?>" class="blog-card-media">
                  <?php if (!empty($blog['featured_image'])): ?>
                    <img src="<?= e((string) $blog['featured_image']) ?>" alt="<?= e((string) $blog['title']) ?>" loading="lazy" width="640" height="360">
                  <?php else: ?>
                    <div class="blog-card-media-placeholder" aria-hidden="true"></div>
                  <?php endif; ?>
                </a>
                <div class="blog-card-body">
                  <div class="blog-card-meta">
                    <?php if (!empty($blog['publish_date'])): ?>
                      <time datetime="<?= e((string) $blog['publish_date']) ?>"><?= e(blog_format_date($blog['publish_date'])) ?></time>
                    <?php endif; ?>
                    <span><?= e(blog_reading_time($blog)) ?></span>
                  </div>
                  <?php if (!empty($blog['categories'])): ?>
                    <div class="blog-card-cats"><?= e(implode(' · ', array_slice($blog['categories'], 0, 2))) ?></div>
                  <?php endif; ?>
                  <h2 class="blog-card-title">
                    <a href="<?= e(blog_public_url($blog)) ?>"><?= e((string) $blog['title']) ?></a>
                  </h2>
                  <p class="blog-card-excerpt"><?= e((string) ($blog['excerpt'] ?? '')) ?></p>
                  <a href="<?= e(blog_public_url($blog)) ?>" class="blog-card-link">Read article →</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php elseif ($featured !== null): ?>
          <div class="blog-index-section-head">
            <h2>Latest from the blog</h2>
            <p>More guides are on the way.</p>
          </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
          <nav class="blog-pagination" aria-label="Blog pages">
            <?php if ($page > 1): ?>
              <a href="/blog?page=<?= $page - 1 ?>" class="cta-button secondary">← Previous</a>
            <?php endif; ?>
            <span class="blog-pagination-status">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a href="/blog?page=<?= $page + 1 ?>" class="cta-button secondary">Next →</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php include 'components/footer.php'; ?>
