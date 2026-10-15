<?php
/** @var array $cms */
$__img = $cms['featured'] ?? null;
if (!$__img) {
    return;
}
$__eager = $__eager ?? false;
?>
<figure class="cms-featured<?= isset($__figureClass) ? ' ' . CmsRenderer::e($__figureClass) : '' ?>">
  <img src="<?= CmsRenderer::e($__img['url']) ?>"
       alt="<?= CmsRenderer::e($__img['alt']) ?>"
       <?php if ($__img['srcset'] !== ''): ?>srcset="<?= CmsRenderer::e($__img['srcset']) ?>" sizes="(min-width: 992px) 50vw, 100vw"<?php endif; ?>
       <?php if ($__img['width'] > 0): ?>width="<?= (int) $__img['width'] ?>" height="<?= (int) $__img['height'] ?>"<?php else: ?>width="960" height="640"<?php endif; ?>
       <?= $__eager ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>>
  <?php if (($__img['caption'] ?? '') !== '' || ($__img['credit'] ?? '') !== ''): ?>
    <figcaption><?= CmsRenderer::e($__img['caption']) ?><?php if ($__img['credit'] !== ''): ?> <span class="cms-credit"><?= CmsRenderer::e($__img['credit']) ?></span><?php endif; ?></figcaption>
  <?php endif; ?>
</figure>
