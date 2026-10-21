<?php
/**
 * Reusable city / service-area internal links.
 *
 * Set $serviceAreasVariant before include:
 *   footer | home | service | nearby | contact
 *
 * Optional:
 *   $serviceAreasServiceLabel — e.g. "Bookkeeping services" (service variant)
 *   $serviceAreasCurrentPath — city path to exclude (nearby variant)
 *   $serviceAreasLimit — max cities
 */
declare(strict_types=1);

if (!class_exists('CmsServiceAreas')) {
    return;
}

$variant = $serviceAreasVariant ?? 'home';
$limit = (int) ($serviceAreasLimit ?? 8);
$serviceLabel = isset($serviceAreasServiceLabel) ? trim((string) $serviceAreasServiceLabel) : '';
$currentPath = isset($serviceAreasCurrentPath) ? (string) $serviceAreasCurrentPath : '';
$indexPath = CmsServiceAreas::indexPath();
$indexLabel = CmsServiceAreas::INDEX_LABEL;

$areas = match ($variant) {
    'footer' => CmsServiceAreas::all(),
    'nearby' => CmsServiceAreas::nearby($currentPath, max(4, $limit)),
    'service' => CmsServiceAreas::forService($serviceLabel !== '' ? $serviceLabel : null, max(4, $limit)),
    'contact' => CmsServiceAreas::featured(max(4, min(10, $limit))),
    default => CmsServiceAreas::featured(max(4, $limit)),
};

if ($areas === [] && $variant !== 'footer') {
    return;
}

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($variant === 'footer'): ?>
  <nav class="footer-col footer-areas" aria-label="Areas we serve">
    <h3 class="footer-heading">Areas We Serve</h3>
    <?php if ($areas === []): ?>
      <ul class="footer-links">
        <li><a href="<?= $e($indexPath) ?>"><?= $e($indexLabel) ?></a></li>
      </ul>
    <?php else: ?>
      <div class="footer-areas-scroll" tabindex="0" role="region" aria-label="Cities we serve">
        <ul class="footer-links footer-areas-list">
          <?php foreach ($areas as $area): ?>
            <li><a href="<?= $e($area['path']) ?>"><?= $e($area['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <p class="footer-areas-more"><a href="<?= $e($indexPath) ?>">View all service areas</a></p>
    <?php endif; ?>
  </nav>
<?php elseif ($variant === 'nearby'): ?>
  <section class="service-areas-inline common-section" aria-labelledby="nearby-areas-heading">
    <div class="container">
      <h2 class="section-title text-center" id="nearby-areas-heading">Nearby areas we serve</h2>
      <p class="text-center service-areas-inline-intro">Verma Accounting also helps clients in these communities. Browse a city page or see our full <a href="<?= $e($indexPath) ?>"><?= $e(strtolower($indexLabel)) ?></a> list.</p>
      <ul class="service-areas-chip-list">
        <?php foreach ($areas as $area): ?>
          <li><a href="<?= $e($area['path']) ?>"><?= $e($area['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <p class="text-center service-areas-inline-cta"><a class="cta-button primary" href="/contact">Get Free Consultation</a></p>
    </div>
  </section>
<?php elseif ($variant === 'service'): ?>
  <section class="service-areas-inline common-section" aria-labelledby="service-areas-heading">
    <div class="container">
      <h2 class="section-title text-center" id="service-areas-heading">Serving Ottawa and nearby communities</h2>
      <p class="text-center service-areas-inline-intro">
        We work with clients across the National Capital Region
        <?= $serviceLabel !== '' ? ' for ' . $e(mb_strtolower($serviceLabel)) : '' ?>.
        Choose your community or visit our <a href="<?= $e($indexPath) ?>"><?= $e(strtolower($indexLabel)) ?></a> directory.
      </p>
      <ul class="service-areas-chip-list">
        <?php foreach ($areas as $area):
            $label = (string) ($area['label'] ?? $area['name']);
            ?>
          <li><a href="<?= $e($area['path']) ?>"><?= $e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php elseif ($variant === 'contact'): ?>
  <section class="service-areas-inline service-areas-inline--contact" aria-labelledby="contact-areas-heading">
    <h2 class="section-title" id="contact-areas-heading">Areas we serve</h2>
    <p>Based in Nepean, we work with clients across Ottawa and nearby communities — online or by appointment.</p>
    <ul class="service-areas-chip-list">
      <?php foreach ($areas as $area): ?>
        <li><a href="<?= $e($area['path']) ?>"><?= $e($area['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p><a href="<?= $e($indexPath) ?>">See all <?= $e(strtolower($indexLabel)) ?></a></p>
  </section>
<?php else: /* home */ ?>
  <div class="home-service-areas">
    <p class="home-service-areas-lead">We work with clients in Ottawa and communities across the National Capital Region:</p>
    <ul class="service-areas-chip-list service-areas-chip-list--home">
      <?php foreach ($areas as $area): ?>
        <li><a href="<?= $e($area['path']) ?>"><?= $e($area['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="home-service-areas-more"><a href="<?= $e($indexPath) ?>">View all service areas</a></p>
  </div>
<?php endif; ?>
