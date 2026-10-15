<?php
/** @var array $__item an article row */
$__url = CmsRenderer::e((string) $__item['path']);
$__img = CmsRenderer::featuredImage($__item);
$__cats = array_values(array_filter((new CmsContentRepository())->contentTerms((int) $__item['id']), static fn ($t) => $t['kind'] === 'category'));
$__headingTag = $__headingTag ?? 'h2';
?>
<article class="blog-card">
  <a href="<?= $__url ?>" class="blog-card-media" tabindex="-1" aria-hidden="true">
    <?php if ($__img): ?>
      <img src="<?= CmsRenderer::e($__img['url']) ?>" alt="" loading="lazy" decoding="async"
           <?php if ($__img['srcset'] !== ''): ?>srcset="<?= CmsRenderer::e($__img['srcset']) ?>" sizes="(min-width: 992px) 33vw, 100vw"<?php endif; ?>
           width="640" height="360">
    <?php else: ?>
      <div class="blog-card-media-placeholder"></div>
    <?php endif; ?>
  </a>
  <div class="blog-card-body">
    <div class="blog-card-meta">
      <?php if (!empty($__item['published_at'])): ?>
        <time datetime="<?= CmsRenderer::e(substr((string) $__item['published_at'], 0, 10)) ?>"><?= CmsRenderer::e(CmsRenderer::formatDate((string) $__item['published_at'])) ?></time>
      <?php endif; ?>
      <span><?= CmsRenderer::e(CmsRenderer::readingTime((string) ($__item['body_html'] ?? ''))) ?></span>
    </div>
    <?php if ($__cats !== []): ?>
      <div class="blog-card-cats"><?= CmsRenderer::e(implode(' · ', array_map(static fn ($t) => $t['name'], array_slice($__cats, 0, 2)))) ?></div>
    <?php endif; ?>
    <<?= $__headingTag ?> class="blog-card-title"><a href="<?= $__url ?>"><?= CmsRenderer::e((string) $__item['title']) ?></a></<?= $__headingTag ?>>
    <?php if (trim((string) ($__item['excerpt'] ?? '')) !== ''): ?>
      <p class="blog-card-excerpt"><?= CmsRenderer::e((string) $__item['excerpt']) ?></p>
    <?php endif; ?>
    <a href="<?= $__url ?>" class="blog-card-link">Read article<span class="visually-hidden">: <?= CmsRenderer::e((string) $__item['title']) ?></span> →</a>
  </div>
</article>
