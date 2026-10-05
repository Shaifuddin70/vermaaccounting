<?php
if (!function_exists('asset')) {
  require_once __DIR__ . '/../lib/helpers.php';
}
require_once __DIR__ . '/../lib/seo.php';
app_begin_output_filter();

$siteCta = site_cta_resolve();

$canonicalPath = seo_normalize_path($_SERVER['REQUEST_URI'] ?? '/');

$seoTitleOverride = $seoTitle ?? ($pageTitle ?? null);
$seoDescriptionOverride = $seoDescription ?? null;
$seoMeta = seo_resolve($canonicalPath, $seoTitleOverride, $seoDescriptionOverride);
$seoPageSchema = seo_page_schema($canonicalPath);

if (isset($seoCanonicalPath) && is_string($seoCanonicalPath) && $seoCanonicalPath !== '') {
  // Pages like /blog?page=2 need a self-referencing canonical that keeps the query string.
  $canonicalUrl = seo_site_base_url() . $seoCanonicalPath;
} elseif ($canonicalPath !== '/') {
  $canonicalUrl = seo_site_base_url() . $canonicalPath;
} else {
  $canonicalUrl = seo_site_base_url() . '/';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="theme-color" content="#1e3a8a" />
  <title><?= e($seoMeta['title']) ?></title>
  <meta name="description" content="<?= e($seoMeta['description']) ?>" />
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="<?= e($canonicalUrl) ?>" />

  <!-- Calendly link widget begin -->
  <link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
  <script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>

  <!-- Calendly link widget end -->
  <!-- Favicon -->
  <link rel="icon" type="image/jpeg" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />
  <link rel="shortcut icon" type="image/jpeg" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />
  <link rel="apple-touch-icon" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />
  <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />
  <link rel="icon" type="image/jpeg" sizes="32x32" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />
  <link rel="icon" type="image/jpeg" sizes="16x16" href="<?= asset('images/verma-accounting-favicon.jpg') ?>" />

  <!-- Additional SEO Meta Tags -->
  <meta name="geo.region" content="CA-ON" />
  <meta name="geo.placename" content="Nepean, Ontario" />
  <meta name="geo.position" content="45.2691;-75.7518" />
  <meta name="ICBM" content="45.2691, -75.7518" />
  <meta name="author" content="Verma Accounting & Financial Services" />
  <link rel="me" href="tel:+16133186478" />
  <link rel="me" href="mailto:info@vermaaccounting.ca" />

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="<?= e($seoMeta['title']) ?>" />
  <meta property="og:description" content="<?= e($seoMeta['description']) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= e($canonicalUrl) ?>" />
  <meta property="og:site_name" content="Verma Accounting & Financial Services" />
  <meta property="og:locale" content="en_CA" />

  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($seoMeta['title']) ?>" />
  <meta name="twitter:description" content="<?= e($seoMeta['description']) ?>" />

  <!-- Google Fonts - Roboto -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap"
    rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <!-- Font Awesome CDN -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer" />



  <!-- Structured data (JSON-LD) -->
  <script type="application/ld+json"><?= json_encode(seo_local_business_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <script type="application/ld+json"><?= json_encode(seo_website_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php if ($seoPageSchema !== null): ?>
  <script type="application/ld+json"><?= json_encode($seoPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
  <?php if ($canonicalPath === '/'):
    $homeFaqSchema = seo_faq_page_schema(seo_home_faqs());
    if ($homeFaqSchema !== null):
  ?>
  <script type="application/ld+json"><?= json_encode($homeFaqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; endif; ?>

  <link rel="stylesheet" href="<?= asset('css/styles.css') ?>?v=86" />
  <link rel="stylesheet" href="<?= asset('css/header.css') ?>?v=3" />
</head>

<?php
$siteServiceMenu = [
  ['url' => '/accounting', 'title' => 'Accounting', 'desc' => 'Financial statements & year-end', 'icon' => 'fa-calculator'],
  ['url' => '/bookkeeping', 'title' => 'Bookkeeping', 'desc' => 'Clean, reconciled monthly books', 'icon' => 'fa-book-open'],
  ['url' => '/payroll', 'title' => 'Payroll', 'desc' => 'Pay runs, remittances & T4s', 'icon' => 'fa-money-check-dollar'],
  ['url' => '/personal-tax', 'title' => 'Personal Tax', 'desc' => 'T1 returns that maximize refunds', 'icon' => 'fa-user-tie'],
  ['url' => '/corporate-tax', 'title' => 'Corporate Tax', 'desc' => 'T2 filing & tax planning', 'icon' => 'fa-building'],
  ['url' => '/business-registration', 'title' => 'Business Registration', 'desc' => 'BN, GST/HST & incorporation', 'icon' => 'fa-file-signature'],
  ['url' => '/loan', 'title' => 'Business Loans', 'desc' => 'Financing support for growth', 'icon' => 'fa-hand-holding-dollar'],
  ['url' => 'https://owningottawa.com/', 'title' => 'Real Estate', 'desc' => 'Buying & selling in Ottawa', 'icon' => 'fa-house', 'external' => true],
];
$siteSubmitMenu = site_submit_menu_forms();

$siteNav = [
  ['type' => 'link', 'url' => '/', 'label' => 'Home', 'icon' => 'fa-house'],
  [
    'type' => 'mega', 'id' => 'services', 'label' => 'Our Services', 'icon' => 'fa-briefcase',
    'items' => $siteServiceMenu,
    'all' => ['url' => '/services', 'label' => 'View all services'],
  ],
];
if ($siteSubmitMenu) {
  $siteNav[] = [
    'type' => 'mega', 'id' => 'submit', 'label' => 'Submit Document', 'icon' => 'fa-cloud-arrow-up',
    'compact' => true,
    'items' => array_map(static fn (array $f): array => [
      'url' => $f['url'], 'title' => $f['title'], 'desc' => $f['description'], 'icon' => $f['icon'],
    ], $siteSubmitMenu),
  ];
}
$siteNav[] = ['type' => 'link', 'url' => '/resources', 'label' => 'Resources', 'icon' => 'fa-book-open-reader'];
$siteNav[] = [
  'type' => 'mega', 'id' => 'about', 'label' => 'About Us', 'icon' => 'fa-users',
  'compact' => true,
  'items' => [
    ['url' => '/about', 'title' => 'About Us', 'desc' => 'Our team, story & values', 'icon' => 'fa-users'],
    ['url' => '/blog', 'title' => 'Blog', 'desc' => 'Tax tips, news & guides', 'icon' => 'fa-newspaper'],
    ['url' => '/contact', 'title' => 'Contact Us', 'desc' => 'Get in touch or book a call', 'icon' => 'fa-envelope'],
  ],
];
if ($siteCta['nav_label'] !== '') {
  $siteNav[] = ['type' => 'link', 'url' => $siteCta['url'], 'label' => $siteCta['nav_label'], 'icon' => 'fa-file-lines'];
}

$sitePhoneHref = 'tel:+16133186478';
$sitePhoneLabel = '+1 (613) 318-6478';
$siteLinkAttrs = static fn (array $item): string => !empty($item['external']) ? ' target="_blank" rel="noopener"' : '';
?>
<body class="<?= $canonicalPath === '/' ? 'is-home' : '' ?>">
  <header class="sh" id="siteHeader">
    <div class="sh-bar">
      <a href="/" class="sh-logo" aria-label="Verma Accounting home">
        <img src="<?= asset('images/verma-accounting-logo.png') ?>" alt="Verma Accounting &amp; Financial Services" width="170" height="42" />
      </a>

      <nav class="sh-nav" aria-label="Main">
        <ul class="sh-list">
          <?php foreach ($siteNav as $item): ?>
            <?php if ($item['type'] === 'link'): ?>
              <li><a href="<?= e($item['url']) ?>" class="sh-link"><?= e($item['label']) ?></a></li>
            <?php else: ?>
              <li class="sh-item">
                <button type="button" class="sh-link sh-trigger" aria-expanded="false" aria-controls="sh-mega-<?= e($item['id']) ?>">
                  <?= e($item['label']) ?>
                  <i class="fas fa-chevron-down sh-caret" aria-hidden="true"></i>
                </button>
                <div class="sh-mega<?= !empty($item['compact']) ? ' sh-mega--compact' : '' ?>" id="sh-mega-<?= e($item['id']) ?>">
                  <div class="sh-mega-main">
                    <div class="sh-mega-grid">
                      <?php foreach ($item['items'] as $sub): ?>
                        <a href="<?= e($sub['url']) ?>" class="sh-mega-link"<?= $siteLinkAttrs($sub) ?>>
                          <span class="sh-mega-icon" aria-hidden="true"><i class="fas <?= e($sub['icon']) ?>"></i></span>
                          <span class="sh-mega-text">
                            <span class="sh-mega-title"><?= e($sub['title']) ?><?php if (!empty($sub['external'])): ?> <i class="fas fa-arrow-up-right-from-square sh-ext" aria-hidden="true"></i><?php endif; ?></span>
                            <span class="sh-mega-desc"><?= e($sub['desc']) ?></span>
                          </span>
                        </a>
                      <?php endforeach; ?>
                    </div>
                    <?php if (!empty($item['all'])): ?>
                      <a href="<?= e($item['all']['url']) ?>" class="sh-mega-all"><?= e($item['all']['label']) ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    <?php endif; ?>
                  </div>
                </div>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="sh-actions">
        <a href="<?= e($sitePhoneHref) ?>" class="sh-phone" aria-label="Call <?= e($sitePhoneLabel) ?>">
          <span class="sh-phone-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
          <span class="sh-phone-text"><small>Call us</small><?= e($sitePhoneLabel) ?></span>
        </a>
        <a href="/contact" class="sh-btn sh-btn-primary sh-book">
          <i class="fas fa-calendar-check" aria-hidden="true"></i>
          <span class="sh-book-text">Book Appointment</span>
        </a>
        <button type="button" class="sh-burger" aria-controls="shDrawer" aria-expanded="false" aria-label="Open menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <div class="sh-overlay" id="shOverlay" data-sh-close></div>
  <aside class="sh-drawer" id="shDrawer" aria-label="Menu" aria-hidden="true" inert>
    <div class="sh-drawer-head">
      <a href="/" class="sh-logo" aria-label="Verma Accounting home">
        <img src="<?= asset('images/verma-accounting-logo.png') ?>" alt="" width="150" height="37" />
      </a>
      <button type="button" class="sh-close" data-sh-close aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <nav class="sh-drawer-nav" aria-label="Mobile">
      <?php foreach ($siteNav as $item): ?>
        <?php if ($item['type'] === 'link'): ?>
          <a href="<?= e($item['url']) ?>" class="sh-dlink">
            <span class="sh-dicon" aria-hidden="true"><i class="fas <?= e($item['icon']) ?>"></i></span>
            <span class="sh-dlabel"><?= e($item['label']) ?></span>
          </a>
        <?php else: ?>
          <div class="sh-dgroup">
            <button type="button" class="sh-dlink sh-dtoggle" aria-expanded="false" aria-controls="sh-d-<?= e($item['id']) ?>">
              <span class="sh-dicon" aria-hidden="true"><i class="fas <?= e($item['icon']) ?>"></i></span>
              <span class="sh-dlabel"><?= e($item['label']) ?></span>
              <i class="fas fa-chevron-down sh-caret" aria-hidden="true"></i>
            </button>
            <div class="sh-dsub" id="sh-d-<?= e($item['id']) ?>" hidden>
              <?php foreach ($item['items'] as $sub): ?>
                <a href="<?= e($sub['url']) ?>" class="sh-dsub-link"<?= $siteLinkAttrs($sub) ?>>
                  <i class="fas <?= e($sub['icon']) ?>" aria-hidden="true"></i><?= e($sub['title']) ?>
                </a>
              <?php endforeach; ?>
              <?php if (!empty($item['all'])): ?>
                <a href="<?= e($item['all']['url']) ?>" class="sh-dsub-link sh-dsub-all"><?= e($item['all']['label']) ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sh-drawer-foot">
      <a href="/contact" class="sh-btn sh-btn-primary sh-btn-block"><i class="fas fa-calendar-check" aria-hidden="true"></i> Book Appointment</a>
      <div class="sh-drawer-contact">
        <a href="<?= e($sitePhoneHref) ?>"><i class="fas fa-phone" aria-hidden="true"></i> <?= e($sitePhoneLabel) ?></a>
        <a href="mailto:info@vermaaccounting.ca"><i class="fas fa-envelope" aria-hidden="true"></i> info@vermaaccounting.ca</a>
      </div>
    </div>
  </aside>
