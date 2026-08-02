<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: /blog');
    exit;
}

$repo = new BlogRepository();
$blog = $repo->findBySlug($slug, true);
if ($blog === null) {
    http_response_code(404);
    $seoTitle = 'Article not found | Verma Accounting';
    $seoDescription = 'The blog post you requested could not be found.';
    include 'components/header.php';
    echo '<main class="main-content"><section class="page-header"><div class="container"><h1>Article not found</h1><p><a href="/blog">Back to the blog</a></p></div></section></main>';
    include 'components/footer.php';
    exit;
}

$seoTitle = (string) (($blog['seo_title'] ?? '') !== '' ? $blog['seo_title'] : $blog['title']);
$seoDescription = (string) (($blog['seo_description'] ?? '') !== '' ? $blog['seo_description'] : ($blog['excerpt'] ?? ''));
$canonicalPath = '/blog/' . $blog['slug'];
$ogImage = (string) ($blog['featured_image'] ?? '');
$contentHtml = blog_prepare_content_html((string) ($blog['content_html'] ?? ''));

include 'components/header.php';

$categories = $blog['categories'] ?? [];
$author = trim((string) ($blog['author_name'] ?? ''));
$featured = trim((string) ($blog['featured_image'] ?? ''));
$authorPhoto = asset('images/rishab-verma-lg.jpg');
?>
<main class="main-content">
  <article class="blog-post">
    <header class="blog-article-hero">
      <div class="container blog-article-hero-inner">
        <nav class="blog-article-breadcrumb" aria-label="Breadcrumb">
          <a href="/">Home</a>
          <span class="blog-article-sep" aria-hidden="true">/</span>
          <a href="/blog">Blog</a>
          <?php if ($categories !== []): ?>
            <span class="blog-article-sep" aria-hidden="true">/</span>
            <span><?= e($categories[0]) ?></span>
          <?php endif; ?>
        </nav>

        <div class="blog-article-hero-grid<?= $featured !== '' ? '' : ' blog-article-hero-grid--text-only' ?>">
          <div class="blog-article-hero-copy">
            <?php if ($categories !== []): ?>
              <div class="blog-article-tags">
                <?php foreach (array_slice($categories, 0, 3) as $cat): ?>
                  <span class="blog-article-tag"><?= e($cat) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <h1 class="blog-article-title"><?= e((string) $blog['title']) ?></h1>

            <?php if (!empty($blog['excerpt'])): ?>
              <p class="blog-article-dek"><?= e((string) $blog['excerpt']) ?></p>
            <?php endif; ?>

            <div class="blog-article-byline">
              <div class="blog-article-author">
                <img
                  class="blog-article-avatar"
                  src="<?= e($authorPhoto) ?>"
                  alt="<?= e($author !== '' ? $author : 'Rishabh Verma') ?>"
                  width="88"
                  height="88"
                  loading="lazy">
                <div class="blog-article-author-text">
                  <?php if ($author !== ''): ?>
                    <span class="blog-article-author-name"><?= e($author) ?></span>
                  <?php else: ?>
                    <span class="blog-article-author-name">Rishabh Verma</span>
                  <?php endif; ?>
                  <span class="blog-article-author-role">Verma Accounting</span>
                </div>
              </div>
              <ul class="blog-article-meta" aria-label="Article details">
                <?php if (!empty($blog['publish_date'])): ?>
                  <li>
                    <time datetime="<?= e((string) $blog['publish_date']) ?>"><?= e(blog_format_date($blog['publish_date'])) ?></time>
                  </li>
                <?php endif; ?>
                <li><?= e(blog_reading_time($blog)) ?></li>
              </ul>
            </div>
          </div>

          <?php if ($featured !== ''): ?>
            <figure class="blog-article-hero-media">
              <img
                src="<?= e($featured) ?>"
                alt="<?= e((string) $blog['title']) ?>"
                width="960"
                height="640"
                fetchpriority="high">
            </figure>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="blog-post-body-section">
      <div class="container blog-post-shell">
        <div class="blog-post-layout">
          <div class="blog-post-content">
            <div class="blog-post-html">
              <?= $contentHtml ?>
            </div>
          </div>
          <aside class="blog-post-aside">
            <div class="blog-aside-card blog-aside-card--cta">
              <span class="blog-aside-eyebrow">Next step</span>
              <h2>Need help with your books or taxes?</h2>
              <p>Verma Accounting supports individuals and businesses across Ontario with clear, CRA-ready guidance.</p>
              <a href="/contact" class="cta-button primary">Book a consultation</a>
              <a href="tel:613-318-6478" class="cta-button orange">
                <i class="fas fa-phone" aria-hidden="true"></i>
                613-318-6478
              </a>
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
      </div>
    </div>
  </article>
</main>
<?php
$articleSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => (string) $blog['title'],
    'description' => $seoDescription,
    'datePublished' => (string) ($blog['publish_date'] ?? ''),
    'dateModified' => (string) ($blog['source_updated_at'] ?? $blog['updated_at'] ?? ''),
    'author' => [
        '@type' => 'Person',
        'name' => $author !== '' ? $author : 'Verma Accounting',
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Verma Accounting & Financial Services',
        'url' => 'https://vermaaccounting.ca/',
    ],
    'mainEntityOfPage' => 'https://vermaaccounting.ca' . $canonicalPath,
];
if ($ogImage !== '') {
    $articleSchema['image'] = [$ogImage];
}
?>
<script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php include 'components/footer.php'; ?>
