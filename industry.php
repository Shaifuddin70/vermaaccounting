<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$path = $slug !== '' ? IndustryCatalog::path($slug) : '';

// Published CMS pages take priority over the PHP catalog (fixes 404s for CMS-only industries).
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
        error_log('industry CMS resolve failed: ' . $e->getMessage());
    }
}

$industry = $slug !== '' ? IndustryCatalog::find($slug) : null;

if ($industry === null) {
    http_response_code(404);
    $response = CmsRenderer::notFound();
    CmsRouter::emit($response);
    exit;
}

$pageTitle = (string) $industry['h1'];
$seoTitle = $industry['h1'] . ' | Verma Accounting Ottawa';
$seoDescription = mb_substr((string) $industry['intro'], 0, 155);
$seoCanonicalPath = $path;
$breadcrumbTitle = (string) $industry['name'];
$siteCrumbs = [
    ['name' => 'Home', 'path' => '/'],
    ['name' => 'Industries', 'path' => IndustryCatalog::HUB_PATH],
    ['name' => (string) $industry['name'], 'path' => $path],
];

include __DIR__ . '/components/header.php';
?>
<link rel="stylesheet" href="<?= e(asset_versioned('css/industries.css')) ?>">
<main class="main-content industries-page industry-detail">
  <section class="page-header">
    <div class="scroll-animate">
      <h1><?= e($industry['h1']) ?></h1>
      <p><?= e($industry['intro']) ?></p>
      <div class="cta-buttons">
        <a href="/contact" class="cta-button primary">Talk to an accountant</a>
        <a href="<?= e(company_phone_href()) ?>" class="cta-button orange"><i class="fas fa-phone"></i> <?= e(company_phone()) ?></a>
      </div>
    </div>
  </section>

  <?php foreach ($industry['sections'] as $i => $section): ?>
    <section class="common-section">
      <div class="container">
        <div class="scroll-animate">
          <h2 class="section-title"><?= e($section['heading']) ?></h2>
          <p class="industry-body"><?= e($section['body']) ?></p>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="common-section">
    <div class="container">
      <h2 class="section-title">Related services</h2>
      <ul class="industry-service-links">
        <?php foreach ($industry['services'] as $svc): ?>
          <li><a href="<?= e($svc['url']) ?>"><?= e($svc['label']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= e(IndustryCatalog::HUB_PATH) ?>">All industries</a></li>
      </ul>
    </div>
  </section>

  <?php if (!empty($industry['faq'])): ?>
    <section class="common-section">
      <div class="container">
        <h2 class="section-title">FAQ</h2>
        <div class="industry-faq">
          <?php foreach ($industry['faq'] as $item): ?>
            <details>
              <summary><?= e($item['q']) ?></summary>
              <p><?= e($item['a']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'FAQPage',
      'mainEntity' => array_map(static fn (array $item): array => [
          '@type' => 'Question',
          'name' => $item['q'],
          'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
      ], $industry['faq']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>

  <section class="common-section text-center">
    <div class="container">
      <h2 class="section-title">Ready to get organized?</h2>
      <p class="industries-intro mx-auto">Book a call and we will tell you what bookkeeping, payroll, and tax help fits your work.</p>
      <a href="/contact" class="cta-button primary">Get a free consultation</a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/components/footer.php'; ?>
