<?php
/** @var array $cms */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) $c['title'];
$featured = $cms['industry_cards']['featured'] ?? [];
$more = $cms['industry_cards']['more'] ?? [];
$locations = $cms['industry_cards']['locations'] ?? [];
$hero = $cms['featured'] ?? null;
$heroUrl = is_array($hero) && ($hero['url'] ?? '') !== '' ? (string) $hero['url'] : '/images/industries/hero.jpg';
$phone = class_exists('CmsCompany') ? CmsCompany::phone() : '';
$phoneHref = class_exists('CmsCompany') ? CmsCompany::phoneHref() : 'tel:';
?>
<link rel="stylesheet" href="<?= CmsStylesheets::versionedUrl('/css/industries.css') ?>">
<main class="main-content industries-page" id="main">
  <section class="industries-hero" aria-label="<?= $e($h1) ?>">
    <div class="industries-hero-media" aria-hidden="true">
      <img src="<?= $e($heroUrl) ?>" alt="" width="1920" height="1280" loading="eager" fetchpriority="high" decoding="async">
    </div>
    <div class="industries-hero-shade" aria-hidden="true"></div>
    <div class="container industries-hero-inner">
      <p class="industries-hero-brand">Verma Accounting</p>
      <h1><?= $e($h1) ?></h1>
      <p class="industries-hero-lead">
        Clear books and tax help for trucking, rentals, construction, restaurants, and more across Ottawa and Ontario.
      </p>
      <div class="industries-hero-cta">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <?php if ($phone !== ''): ?>
          <a href="<?= $e($phoneHref) ?>" class="cta-button secondary">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <?= $e($phone) ?>
          </a>
        <?php endif; ?>
      </div>
      <a class="industries-hero-scroll" href="#industry-pages">See industries</a>
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

  <?php if (trim(strip_tags((string) ($cms['body'] ?? ''))) !== ''): ?>
    <section class="common-section">
      <?= $cms['body'] ?>
    </section>
  <?php endif; ?>

  <?php if ($featured !== []): ?>
  <section class="common-section industries-focus" id="industry-pages">
    <div class="container">
      <div class="industries-section-head">
        <h2 class="section-title">Businesses we know well</h2>
        <p class="industries-intro">
          These pages explain how we help each group. Pick yours to learn more, or go straight to
          <a href="/contact">book a call</a>.
        </p>
      </div>
      <div class="row mt-4 justify-content-center industries-card-row">
        <?php foreach ($featured as $row):
          $href = (string) $row['path'];
          $img = CmsRenderer::featuredImage($row);
          $imgUrl = $img['url'] ?? '/images/calculation.jpg';
          $imgAlt = ($img['alt'] ?? '') !== '' ? (string) $img['alt'] : (string) $row['title'];
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services">
              <a class="services-thumbnail" href="<?= $e($href) ?>">
                <img class="thumb" src="<?= $e($imgUrl) ?>" alt="<?= $e($imgAlt) ?>" loading="lazy" decoding="async" width="800" height="450">
                <strong class="services-title"><?= $e((string) $row['title']) ?></strong>
              </a>
              <?php if (trim((string) ($row['excerpt'] ?? '')) !== ''): ?>
                <p class="services-description"><?= $e((string) $row['excerpt']) ?></p>
              <?php endif; ?>
              <p class="industries-card-link"><a href="<?= $e($href) ?>">Read more</a></p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($more !== []): ?>
  <section class="common-section industries-more-block" id="more-industries">
    <div class="container">
      <div class="industries-section-head">
        <h2 class="section-title">Other businesses we help</h2>
        <p class="industries-intro">We also work with these groups.</p>
      </div>
      <div class="row mt-4 justify-content-center industries-card-row">
        <?php foreach ($more as $row):
          $href = (string) $row['path'];
          $img = CmsRenderer::featuredImage($row);
          $imgUrl = $img['url'] ?? '/images/calculation.jpg';
          $imgAlt = ($img['alt'] ?? '') !== '' ? (string) $img['alt'] : (string) $row['title'];
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services">
              <a class="services-thumbnail" href="<?= $e($href) ?>">
                <img class="thumb" src="<?= $e($imgUrl) ?>" alt="<?= $e($imgAlt) ?>" loading="lazy" decoding="async" width="800" height="450">
                <strong class="services-title"><?= $e((string) $row['title']) ?></strong>
              </a>
              <?php if (trim((string) ($row['excerpt'] ?? '')) !== ''): ?>
                <p class="services-description"><?= $e((string) $row['excerpt']) ?></p>
              <?php endif; ?>
              <p class="industries-card-link"><a href="<?= $e($href) ?>">Read more</a></p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($locations !== []): ?>
  <section class="common-section" id="ottawa-ontario">
    <div class="container">
      <div class="industries-section-head">
        <h2 class="section-title">Help in Ottawa and Ontario</h2>
        <p class="industries-intro">
          Looking for an accountant near you? These pages cover common local searches.
          You can also browse our <a href="/service-area">service areas</a>.
        </p>
      </div>
      <div class="row mt-4 justify-content-center industries-card-row">
        <?php foreach ($locations as $row):
          $href = (string) $row['path'];
          $img = CmsRenderer::featuredImage($row);
          $imgUrl = $img['url'] ?? '/images/calculation.jpg';
          $imgAlt = ($img['alt'] ?? '') !== '' ? (string) $img['alt'] : (string) $row['title'];
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services">
              <a class="services-thumbnail" href="<?= $e($href) ?>">
                <img class="thumb" src="<?= $e($imgUrl) ?>" alt="<?= $e($imgAlt) ?>" loading="lazy" decoding="async" width="800" height="450">
                <strong class="services-title"><?= $e((string) ($row['h1'] ?: $row['title'])) ?></strong>
              </a>
              <?php if (trim((string) ($row['excerpt'] ?? '')) !== ''): ?>
                <p class="services-description"><?= $e((string) $row['excerpt']) ?></p>
              <?php endif; ?>
              <p class="industries-card-link"><a href="<?= $e($href) ?>">Read more</a></p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="common-section text-center">
    <div class="container">
      <h2 class="section-title">Tell us what you do</h2>
      <p class="industries-intro mx-auto">
        We will tell you what bookkeeping, payroll, and tax help makes sense for your business.
      </p>
      <div class="cta-buttons">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <?php if ($phone !== ''): ?>
          <a href="<?= $e($phoneHref) ?>" class="cta-button orange">
            <i class="fas fa-phone" aria-hidden="true"></i> <?= $e($phone) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
