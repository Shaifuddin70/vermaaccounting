<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$pageTitle = 'Industries We Serve';
$seoTitle = 'Industries We Serve | Ottawa & Ontario Accountants';
$seoDescription = 'See the businesses we help in Ottawa and Ontario: trucking, rentals, construction, restaurants, cleaning, drivers, retail, and more. Bookkeeping, payroll, and tax.';
$breadcrumbTitle = 'Industries We Serve';

$featured = IndustryCatalog::dedicated();
$more = [];
foreach (IndustryCatalog::hubEntries() as $row) {
    if (empty($row['slug'])) {
        $more[] = $row;
    }
}
$locations = IndustryCatalog::locations();

$cardImageAttrs = [
    'class' => 'thumb',
    'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw',
];

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="<?= e(asset_versioned('css/industries.css')) ?>">
<main class="main-content industries-page">
  <section class="industries-hero" aria-label="Industries we serve">
    <div class="industries-hero-media" aria-hidden="true">
      <img
        src="/images/industries/hero.jpg"
        alt=""
        width="1920"
        height="1280"
        loading="eager"
        fetchpriority="high"
        decoding="async"
      >
    </div>
    <div class="industries-hero-shade" aria-hidden="true"></div>
    <div class="container industries-hero-inner">
      <p class="industries-hero-brand">Verma Accounting</p>
      <h1>Industries We Serve</h1>
      <p class="industries-hero-lead">
        Clear books and tax help for trucking, rentals, construction, restaurants, and more across Ottawa and Ontario.
      </p>
      <div class="industries-hero-cta">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <a href="<?= e(company_phone_href()) ?>" class="cta-button secondary">
          <i class="fas fa-phone" aria-hidden="true"></i>
          <?= e(company_phone()) ?>
        </a>
      </div>
      <a class="industries-hero-scroll" href="#industry-pages">See industries</a>
    </div>
  </section>

  <section class="cta-appointment-section">
    <div class="container">
      <div class="cta-appointment-content">
        <div class="cta-text">
          <span class="cta-prefix">Looking for help with</span>
          <span id="typing-text">Your Business?</span>
        </div>
        <div class="cta-action">
          <a class="appointment-button" href="/contact">
            <i class="fas fa-calendar-alt"></i>
            Book Appointment
          </a>
        </div>
      </div>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="img-box scroll-animate-left">
            <img src="/images/calculation.jpg" alt="Accountant reviewing business records" width="640" height="420" loading="lazy">
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">Why industry matters</h2>
            <p>
              A restaurant does not run like a trucking company. A landlord does not get paid
              like a realtor. When we know your line of work, we set up your books the right way
              from the start.
            </p>
            <p>
              That means fewer surprises at tax time, cleaner payroll, and reports you can
              actually use. If you do not see your field below, <a href="/contact">contact us</a>—
              we still help many other small businesses.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

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
          $href = IndustryCatalog::path($row['slug']);
          $img = (string) ($row['image'] ?? '/images/calculation.jpg');
          $alt = (string) ($row['image_alt'] ?? $row['name']);
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services scroll-animate-scale">
              <a class="services-thumbnail" href="<?= e($href) ?>">
                <?= site_image($img, $alt, $cardImageAttrs) ?>
                <strong class="services-title"><?= e($row['name']) ?></strong>
              </a>
              <p class="services-description"><?= e($row['summary']) ?></p>
              <p class="industries-card-link">
                <a href="<?= e($href) ?>">Read more</a>
              </p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($more !== []): ?>
  <section class="common-section industries-more-block" id="more-industries">
    <div class="container">
      <div class="industries-section-head">
        <h2 class="section-title">Other businesses we help</h2>
        <p class="industries-intro">
          We also work with these groups. The links take you to the main service that fits best.
        </p>
      </div>
      <div class="row mt-4 justify-content-center industries-card-row">
        <?php foreach ($more as $row):
          $href = (string) ($row['service'] ?? '/services');
          $img = (string) ($row['image'] ?? '/images/calculation.jpg');
          $alt = (string) ($row['image_alt'] ?? $row['name']);
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services scroll-animate-scale">
              <a class="services-thumbnail" href="<?= e($href) ?>">
                <?= site_image($img, $alt, $cardImageAttrs) ?>
                <strong class="services-title"><?= e($row['name']) ?></strong>
              </a>
              <p class="services-description"><?= e($row['summary']) ?></p>
              <p class="industries-card-link">
                <a href="<?= e($href) ?>">See related service</a>
              </p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

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
        <?php foreach ($locations as $loc):
          $href = (string) $loc['path'];
          $img = (string) ($loc['image'] ?? '/images/calculation.jpg');
          $alt = (string) ($loc['image_alt'] ?? $loc['h1']);
          $blurb = (string) ($loc['hub_blurb'] ?? $loc['description']);
          ?>
          <div class="col-xl-3 col-lg-3 col-md-6 mb-4">
            <article class="services scroll-animate-scale">
              <a class="services-thumbnail" href="<?= e($href) ?>">
                <?= site_image($img, $alt, $cardImageAttrs) ?>
                <strong class="services-title"><?= e($loc['h1']) ?></strong>
              </a>
              <p class="services-description"><?= e($blurb) ?></p>
              <p class="industries-card-link">
                <a href="<?= e($href) ?>">Read more</a>
              </p>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="common-section text-center">
    <div class="container">
      <h2 class="section-title">Tell us what you do</h2>
      <p class="industries-intro mx-auto">
        We will tell you what bookkeeping, payroll, and tax help makes sense for your business.
      </p>
      <div class="cta-buttons">
        <a href="/contact" class="cta-button primary">Get a free consultation</a>
        <a href="<?= e(company_phone_href()) ?>" class="cta-button orange">
          <i class="fas fa-phone"></i> <?= e(company_phone()) ?>
        </a>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
