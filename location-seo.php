<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$city = strtolower(trim((string) ($_GET['city'] ?? '')));
$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$path = ($city !== '' && $slug !== '') ? ('/' . $city . '/' . $slug) : '';

if ($path !== '') {
    try {
        $cms = (new CmsContentRepository())->findByPath($path);
        if ($cms !== null && CmsContentRepository::isLive($cms)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            CmsRouter::emit(CmsRouter::resolve((string) ($_SERVER['REQUEST_URI'] ?? $path), $_GET, (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
            exit;
        }
    } catch (Throwable $e) {
        error_log('location-seo CMS resolve failed: ' . $e->getMessage());
    }
}

$loc = ($city !== '' && $slug !== '') ? IndustryCatalog::findLocation($city, $slug) : null;

if ($loc === null) {
    http_response_code(404);
    CmsRouter::emit(CmsRenderer::notFound());
    exit;
}

$pageTitle = (string) $loc['h1'];
$seoTitle = (string) $loc['title'];
$seoDescription = (string) $loc['description'];
$seoCanonicalPath = $path;
$breadcrumbTitle = (string) $loc['h1'];
$cityLabel = $city === 'ontario' ? 'Ontario' : ucfirst($city);
$siteCrumbs = [
    ['name' => 'Home', 'path' => '/'],
    ['name' => 'Industries', 'path' => IndustryCatalog::HUB_PATH],
    ['name' => (string) $loc['h1'], 'path' => $path],
];

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="<?= e(asset_versioned('css/industries.css')) ?>">
<main class="main-content industries-page location-seo">
  <section class="page-header">
    <div class="scroll-animate">
      <h1><?= e($loc['h1']) ?></h1>
      <p><?= e($loc['intro']) ?></p>
      <div class="cta-buttons">
        <a href="/contact" class="cta-button primary">Book a consultation</a>
        <a href="<?= e(company_phone_href()) ?>" class="cta-button orange"><i class="fas fa-phone"></i> <?= e(company_phone()) ?></a>
      </div>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <h2 class="section-title">How we help</h2>
      <ul class="ul-list industry-bullets">
        <?php foreach ($loc['bullets'] as $bullet): ?>
          <li><?= e($bullet) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <h2 class="section-title">Explore next</h2>
      <ul class="industry-service-links">
        <?php foreach ($loc['related'] as $link): ?>
          <li><a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="common-section text-center">
    <div class="container">
      <h2 class="section-title">Talk with Verma Accounting</h2>
      <p class="industries-intro mx-auto">We help <?= e($cityLabel) ?> businesses with accounting, bookkeeping, payroll, and tax.</p>
      <a href="/contact" class="cta-button primary">Get in touch</a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
