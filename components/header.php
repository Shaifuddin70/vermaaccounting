<?php
if (!function_exists('asset')) {
  require_once __DIR__ . '/../lib/helpers.php';
}
require_once __DIR__ . '/../lib/seo.php';

$siteCta = site_cta_resolve();

// A published CMS page at this URL replaces the hand-written page (page-by-page migration).
if (function_exists('cms_takeover_legacy_page')) {
  cms_takeover_legacy_page();
}
app_begin_output_filter();

$canonicalPath = seo_normalize_path($_SERVER['REQUEST_URI'] ?? '/');
$seoRobots = !empty($seoNoindex) ? 'noindex, follow' : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1';

$seoTitleOverride = $seoTitle ?? ($pageTitle ?? null);
$seoDescriptionOverride = $seoDescription ?? null;
$seoMeta = seo_resolve($canonicalPath, $seoTitleOverride, $seoDescriptionOverride);
$seoPageSchema = seo_page_schema($canonicalPath);
$siteCrumbs = CmsNavigation::breadcrumbs(
  $canonicalPath,
  $breadcrumbTitle ?? (isset($pageTitle) && !str_contains((string) $pageTitle, '|') ? (string) $pageTitle : null)
);

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
  <meta name="robots" content="<?= e($seoRobots) ?>" />
  <link rel="canonical" href="<?= e($canonicalUrl) ?>" />
  <link rel="alternate" type="text/markdown" href="/llms.txt" title="LLM-friendly site summary (llms.txt)" />

  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
  <link rel="preload" href="/fonts/roboto-latin.woff2" as="font" type="font/woff2" crossorigin />
  <?php if (!empty($preloadImage) && is_array($preloadImage)): ?>
  <link rel="preload" as="image" href="<?= e($preloadImage['href']) ?>"<?php if (!empty($preloadImage['srcset'])): ?> imagesrcset="<?= e($preloadImage['srcset']) ?>" imagesizes="<?= e($preloadImage['sizes'] ?? '100vw') ?>"<?php endif; ?> fetchpriority="high" />
  <?php endif; ?>
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
  <link rel="me" href="<?= e(company_phone_href()) ?>" />
  <link rel="me" href="<?= e(company_email_href()) ?>" />

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="<?= e($seoMeta['title']) ?>" />
  <meta property="og:description" content="<?= e($seoMeta['description']) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= e($canonicalUrl) ?>" />
  <meta property="og:site_name" content="Verma Accounting & Financial Services" />
  <meta property="og:locale" content="en_CA" />
  <?php
    $ogImageUrl = !empty($ogImage) && is_string($ogImage) ? $ogImage : '/images/og-image.jpg';
    $ogImageSize = !preg_match('#^https?://#i', $ogImageUrl) ? @getimagesize(PROJECT_ROOT . '/' . ltrim($ogImageUrl, '/')) : false;
    $ogImageAlt = isset($ogImageAlt) && is_string($ogImageAlt) && $ogImageAlt !== '' ? $ogImageAlt : 'Verma Accounting & Financial Services';
  ?>
  <meta property="og:image" content="<?= e(seo_absolute_url($ogImageUrl)) ?>" />
  <?php if ($ogImageSize): ?>
  <meta property="og:image:width" content="<?= (int) $ogImageSize[0] ?>" />
  <meta property="og:image:height" content="<?= (int) $ogImageSize[1] ?>" />
  <meta property="og:image:type" content="<?= e((string) $ogImageSize['mime']) ?>" />
  <?php endif; ?>
  <meta property="og:image:alt" content="<?= e($ogImageAlt) ?>" />

  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e($seoMeta['title']) ?>" />
  <meta name="twitter:description" content="<?= e($seoMeta['description']) ?>" />
  <meta name="twitter:image" content="<?= e(seo_absolute_url($ogImageUrl)) ?>" />
  <meta name="twitter:image:alt" content="<?= e($ogImageAlt) ?>" />

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
  <?php if (count($siteCrumbs) > 1): ?>
  <script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(static fn (int $i, array $c): array => [
      '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => seo_site_base_url() . ($c['path'] === '/' ? '/' : $c['path']),
    ], array_keys($siteCrumbs), $siteCrumbs),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
  <?php if ($canonicalPath === '/'):
    $homeFaqSchema = seo_faq_page_schema(seo_home_faqs());
    if ($homeFaqSchema !== null):
  ?>
  <script type="application/ld+json"><?= json_encode($homeFaqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; endif; ?>

  <link rel="stylesheet" href="<?= e(asset_versioned('css/styles.css')) ?>" />
  <link rel="stylesheet" href="<?= e(asset_versioned('css/header.css')) ?>" />
  <?= class_exists('CmsSiteConfig', false) ? CmsSiteConfig::headExtras() : '' ?>
</head>

<body class="<?= $canonicalPath === '/' ? 'is-home' : '' ?>">
<?= class_exists('CmsSiteConfig', false) ? CmsSiteConfig::bodyStartExtras() : '' ?>
<?php include __DIR__ . '/site-nav.php'; ?>
