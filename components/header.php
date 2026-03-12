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
  <title>
    Professional Accounting & Tax Services in Canada | Verma Accounting
  </title>
  <meta
    name="description"
    content="Leading accounting firm in Canada specializing in personal tax preparation, corporate tax filing, bookkeeping, payroll management, and business registration. Certified accountants help maximize tax savings with CRA compliance. Serving Ontario clients for 10+ years." />
  <meta
    name="keywords"
    content="accounting services Canada, tax preparation Canada, personal tax filing, corporate tax services, bookkeeping services Ontario, payroll management Canada, business registration, CRA compliance, tax optimization, accounting firm London Ontario, certified accountants Canada" />
  <meta name="robots" content="index, follow" />
  <?php
  // Build canonical URL per page (without query string)
  $canonicalPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
  if ($canonicalPath === '' || $canonicalPath === false) {
    $canonicalPath = '/';
  }
  if ($canonicalPath !== '/') {
    $canonicalUrl = 'https://vermaaccounting.ca' . rtrim($canonicalPath, '/');
  } else {
    $canonicalUrl = 'https://vermaaccounting.ca/';
  }
  ?>
  <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES); ?>" />

  <!-- Calendly link widget begin -->
  <link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
  <script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>

  <!-- Calendly link widget end -->
  <!-- Favicon -->
  <link rel="icon" type="image/jpeg" href="images/verma-accounting-favicon.jpg" />
  <link rel="shortcut icon" type="image/jpeg" href="images/verma-accounting-favicon.jpg" />
  <link rel="apple-touch-icon" href="images/verma-accounting-favicon.jpg" />
  <link rel="apple-touch-icon" sizes="180x180" href="images/verma-accounting-favicon.jpg" />
  <link rel="icon" type="image/jpeg" sizes="32x32" href="images/verma-accounting-favicon.jpg" />
  <link rel="icon" type="image/jpeg" sizes="16x16" href="images/verma-accounting-favicon.jpg" />

  <!-- Additional SEO Meta Tags -->
  <meta name="author" content="Verma Accounting & Financial Services" />
  <meta name="geo.region" content="CA-ON" />
  <meta name="geo.placename" content="Canada" />
  <meta name="geo.position" content="42.9849;-81.2453" />
  <meta name="ICBM" content="42.9849, -81.2453" />

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="Professional Accounting & Tax Services in Canada | Verma Accounting" />
  <meta property="og:description" content="Leading accounting firm in Canada specializing in personal tax preparation, corporate tax filing, bookkeeping, payroll management, and business registration." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES); ?>" />
  <meta property="og:site_name" content="Verma Accounting & Financial Services" />
  <meta property="og:locale" content="en_CA" />

  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Professional Accounting & Tax Services in Canada | Verma Accounting" />
  <meta name="twitter:description" content="Leading accounting firm in Canada specializing in personal tax preparation, corporate tax filing, bookkeeping, payroll management, and business registration." />

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



  <!-- Structured Data for Local Business -->
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "AccountingService",
      "name": "Verma Accounting & Financial Services",
      "description": "Professional accounting services, bookkeeping, payroll management, tax preparation, and business registration in Canada",
      "url": "https://vermaaccounting.ca/",
      "telephone": "613-318-6478",
      "email": "info@vermaaccounting.ca",
      "address": {
        "@type": "PostalAddress",
        "addressCountry": "CA",
        "addressRegion": "ON",
        "addressLocality": "London"
      },
      "serviceArea": {
        "@type": "Country",
        "name": "Canada"
      },
      "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Accounting Services",
        "itemListElement": [{
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Accounting Services"
            }
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Bookkeeping Services"
            }
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Payroll Services"
            }
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Personal Tax Preparation"
            }
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Corporate Tax Services"
            }
          },
          {
            "@type": "Offer",
            "itemOffered": {
              "@type": "Service",
              "name": "Business Registration"
            }
          }
        ]
      },
      "openingHours": "Mo-Fr 09:00-17:00,Sa 10:00-16:00"
    }
  </script>

  <link rel="stylesheet" href="css/styles.css" />
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
              src="images/verma-accounting-logo.png"
              alt="Verma Accounting & Financial Services"
              class="verma-logo-img" />
          </a>
        </div>
        <a href="tel:613-318-6478" class="cta-button orange d-lg-none phone-icon-button">
          <i class="fas fa-phone"></i>
        </a>
        <!-- Mobile Menu Toggle - Hamburger -->
        <label class="hamburger" id="hamburgerMenu" for="hamburgerCheckbox">
          <input type="checkbox" id="hamburgerCheckbox">
          <svg viewBox="0 0 32 32">
            <path class="line line-top-bottom" d="M27 10 13 10C10.8 10 9 8.2 9 6 9 3.5 10.8 2 13 2 15.2 2 17 3.8 17 6L17 26C17 28.2 18.8 30 21 30 23.2 30 25 28.2 25 26 25 23.8 23.2 22 21 22L7 22"></path>
            <path class="line" d="M7 16 27 16"></path>
          </svg>
        </label>
        <!-- Navigation Links -->
        <div class="verma-menu">
          <a href="/" class="verma-link active" data-page="home">Home</a>
          <div class="verma-submenu">
            <a
              href="/services"
              class="verma-link verma-toggle"
              data-page="services">
              Our Services
              <span class="verma-arrow">▼</span>
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
          <a href="/contact" class="verma-link" data-page="contact">Contact Us</a>
          <a href="/about" class="verma-link" data-page="about">About Us</a>
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