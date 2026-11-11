<?php
/** @var array $cms */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) ($c['title'] ?? '');
$excerpt = trim((string) ($c['excerpt'] ?? ''));
$featured = $cms['featured'] ?? null;
$heroUrl = is_array($featured) && ($featured['url'] ?? '') !== ''
    ? (string) $featured['url']
    : '/images/industries/hero.jpg';
$heroAlt = is_array($featured) ? (string) ($featured['alt'] ?? '') : '';
$phone = class_exists('CmsCompany') ? CmsCompany::phone() : '';
$phoneHref = class_exists('CmsCompany') ? CmsCompany::phoneHref() : 'tel:';
$body = (string) ($cms['body'] ?? '');
// Strip outer wrapper so the template owns layout chrome.
$body = (string) preg_replace('#^\s*<div[^>]*class="[^"]*industry-cms[^"]*"[^>]*>#i', '', $body, 1);
$body = (string) preg_replace('#</div>\s*$#i', '', $body, 1);
$body = (string) preg_replace('#^\s*<div[^>]*class="[^"]*container[^"]*"[^>]*>#i', '', $body, 1);
$body = (string) preg_replace('#</div>\s*$#i', '', $body, 1);
?>
<link rel="stylesheet" href="<?= CmsStylesheets::versionedUrl('/css/industries.css') ?>">
<main class="main-content industries-page industry-detail-page" id="main">
  <section class="industries-hero industry-detail-hero" aria-label="<?= $e($h1) ?>">
    <div class="industries-hero-media" aria-hidden="true">
      <img src="<?= $e($heroUrl) ?>" alt="<?= $e($heroAlt) ?>" width="1600" height="900" loading="eager" fetchpriority="high" decoding="async">
    </div>
    <div class="industries-hero-shade" aria-hidden="true"></div>
    <div class="container industries-hero-inner">
      <p class="industries-hero-brand">Verma Accounting</p>
      <h1><?= $e($h1) ?></h1>
      <?php if ($excerpt !== ''): ?>
        <p class="industries-hero-lead"><?= $e($excerpt) ?></p>
      <?php endif; ?>
      <div class="industries-hero-cta">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <?php if ($phone !== ''): ?>
          <a href="<?= $e($phoneHref) ?>" class="cta-button secondary">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <?= $e($phone) ?>
          </a>
        <?php endif; ?>
      </div>
      <a class="industries-hero-scroll" href="#industry-content">Read more</a>
    </div>
  </section>

  <section class="cta-appointment-section">
    <div class="container">
      <div class="cta-appointment-content">
        <div class="cta-text">
          <span class="cta-prefix">Looking for help with</span>
          <span id="typing-text">your Business?</span>
        </div>
        <div class="cta-action">
          <a class="appointment-button" href="/contact">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            Book Appointment
          </a>
        </div>
      </div>
    </div>
  </section>

  <section class="common-section industry-detail-content" id="industry-content">
    <div class="container">
      <div class="industry-cms industry-prose">
        <?= $body ?>
      </div>
    </div>
  </section>

  <?php include __DIR__ . '/_faqs.php'; ?>

  <section class="common-section text-center industry-detail-footer-cta">
    <div class="container">
      <h2 class="section-title">Ready to talk it through?</h2>
      <p class="industries-intro mx-auto">Tell us what you do and we will map the bookkeeping, payroll, and tax help that fits.</p>
      <div class="cta-buttons">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <a href="/industries" class="cta-button secondary">All industries</a>
      </div>
    </div>
  </section>
</main>
