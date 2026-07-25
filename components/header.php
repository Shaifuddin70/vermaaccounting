<?php
if (!function_exists('asset')) {
  require_once __DIR__ . '/../lib/helpers.php';
}
require_once __DIR__ . '/../lib/seo.php';

$siteCta = site_cta_resolve();

$canonicalPath = seo_normalize_path($_SERVER['REQUEST_URI'] ?? '/');

$seoTitleOverride = $seoTitle ?? ($pageTitle ?? null);
$seoDescriptionOverride = $seoDescription ?? null;
$seoMeta = seo_resolve($canonicalPath, $seoTitleOverride, $seoDescriptionOverride);
$seoPageSchema = seo_page_schema($canonicalPath);

if ($canonicalPath !== '/') {
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
  <meta name="author" content="Verma Accounting & Financial Services" />
  <meta name="geo.region" content="CA-ON" />
  <meta name="geo.placename" content="Ontario" />
  <meta name="geo.position" content="42.9849;-81.2453" />
  <meta name="ICBM" content="42.9849, -81.2453" />

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



  <?php if ($canonicalPath === '/'): ?>
  <!-- Structured Data for Local Business (home page only) -->
  <script type="application/ld+json"><?= json_encode(seo_local_business_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    '@id' => seo_site_base_url() . '/#website',
    'url' => seo_site_base_url() . '/',
    'name' => 'Verma Accounting & Financial Services',
    'publisher' => ['@id' => seo_site_base_url() . '/#business'],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php elseif ($seoPageSchema !== null): ?>
  <script type="application/ld+json"><?= json_encode($seoPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>

  <link rel="stylesheet" href="<?= asset('css/styles.css') ?>?v=69" />
</head>

<body>
  <!-- Header Placeholder -->
  <div id="header-placeholder"></div>

  <!-- Navigation Header -->
  <header class="verma-header">
    <nav class="verma-nav">
      <div class="verma-container">
        <!-- Logo Section -->
        <div class="verma-logo">
          <a href="/">
            <img
              src="<?= asset('images/verma-accounting-logo.png') ?>"
              alt="Verma Accounting & Financial Services"
              class="verma-logo-img" />
          </a>
        </div>
        <a href="tel:613-318-6478" class="cta-button orange d-lg-none phone-icon-button" aria-label="Call 613-318-6478">
          <i class="fas fa-phone" aria-hidden="true"></i>
        </a>
        <!-- Mobile Menu Toggle - Hamburger -->
        <label
          class="hamburger"
          id="hamburgerMenu"
          for="hamburgerCheckbox"
          aria-label="Open menu"
          aria-expanded="false"
          aria-controls="vermaMenu">
          <input type="checkbox" id="hamburgerCheckbox" aria-hidden="true" tabindex="-1">
          <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
            <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22"></path>
            <path class="line" d="M7 16 27 16"></path>
          </svg>
        </label>
        <div class="nav-overlay" id="navOverlay" aria-hidden="true"></div>
        <!-- Navigation Links -->
        <div class="verma-menu" id="vermaMenu">
          <div class="verma-menu-header">
            <span class="verma-menu-title">Menu</span>
            <button type="button" class="verma-menu-close" id="mobileMenuClose" aria-label="Close menu">
              <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <a href="/" class="verma-link" data-page="home">Home</a>
          <div class="verma-submenu">
            <a
              href="/services"
              class="verma-link verma-toggle"
              data-page="services"
              aria-expanded="false"
              aria-haspopup="true">
              Our Services
              <span class="verma-arrow" aria-hidden="true">▼</span>
            </a>
            <div class="verma-submenu-items">
              <a href="/services" class="verma-submenu-link verma-submenu-all d-lg-none">View All Services</a>
              <a href="/accounting" class="verma-submenu-link">Accounting</a>
              <a href="/bookkeeping" class="verma-submenu-link">Bookkeeping</a>
              <a href="/payroll" class="verma-submenu-link">Payroll</a>
              <a href="/personal-tax" class="verma-submenu-link">Personal Tax</a>
              <a href="/corporate-tax" class="verma-submenu-link">Corporate Tax</a>
              <a href="/business-registration" class="verma-submenu-link">Business Registration</a>
              <a href="/loan" class="verma-submenu-link">Business Loans</a>
              <a href="https://owningottawa.com/" class="verma-submenu-link" target="_blank" rel="noopener">Real Estate</a>
            </div>
          </div>
          <a href="/resources" class="verma-link" data-page="resources">Resources</a>
          <div class="verma-submenu">
            <a
              href="/about"
              class="verma-link verma-toggle"
              data-page="about"
              aria-expanded="false"
              aria-haspopup="true">
              About Us
              <span class="verma-arrow" aria-hidden="true">▼</span>
            </a>
            <div class="verma-submenu-items">
              <a href="/about" class="verma-submenu-link verma-submenu-all d-lg-none">About Us</a>
              <a href="/contact" class="verma-submenu-link">Contact Us</a>
              <a href="/privacy" class="verma-submenu-link">Privacy Policy</a>
            </div>
          </div>
          <?php if ($siteCta['nav_label'] !== ''): ?>
            <a href="<?= e($siteCta['url']) ?>" class="verma-link" data-page="form-cta"><?= e($siteCta['nav_label']) ?></a>
          <?php endif; ?>
        </div>

        <!-- Contact Information -->
        <div class="verma-contact">
          <div class="verma-contact-text">
            <div class="verma-phone">
              <div class="verma-contact-icon">
                <i class="fas fa-phone"></i>
              </div>
              <a href="tel:613-318-6478" class="verma-phone-link">613-318-6478</a>
            </div>
          </div>
          <div class="verma-cta">
            <a href="/contact" class="verma-book">
              <div class="verma-contact-icon verma-book-icon">
                <i class="fas fa-calendar-alt"></i>
              </div>
              <span class="verma-book-link">Book Appointment</span>
            </a>
          </div>
        </div>
      </div>
    </nav>
  </header>