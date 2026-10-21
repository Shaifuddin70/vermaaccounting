<?php
// Shared site header/navigation markup (used by hand-written pages and the CMS renderer).
$canonicalPath = $canonicalPath ?? seo_normalize_path($_SERVER['REQUEST_URI'] ?? '/');
$siteCta = $siteCta ?? site_cta_resolve();
$siteMenu = CmsNavigation::forSite();
$siteNav = $siteMenu['nav'];
if ($siteCta['nav_label'] !== '') {
  $siteNav[] = ['type' => 'link', 'url' => $siteCta['url'], 'label' => $siteCta['nav_label']];
}

$sitePhoneHref = $siteMenu['phone_href'];
$sitePhoneLabel = $siteMenu['phone_label'];
$siteLinkAttrs = static fn (array $item): string => !empty($item['external']) ? ' target="_blank" rel="noopener"' : '';
$siteCrumbs = $siteCrumbs ?? CmsNavigation::breadcrumbs($canonicalPath);
?>
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <header class="sh<?= count($siteCrumbs) > 1 ? ' sh--crumbs' : '' ?>" id="siteHeader">
    <div class="sh-bar">
      <a href="/" class="sh-logo" aria-label="Verma Accounting home">
        <img src="<?= asset('images/verma-accounting-logo-340.webp') ?>" alt="Verma Accounting &amp; Financial Services" width="170" height="41" />
      </a>

      <nav class="sh-nav" aria-label="Main">
        <ul class="sh-list">
          <?php foreach ($siteNav as $item): ?>
            <?php if ($item['type'] === 'link'): ?>
              <li><a href="<?= e($item['url']) ?>" class="sh-link"<?= $siteLinkAttrs($item) ?>><?= e($item['label']) ?></a></li>
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
                          <span class="sh-mega-title"><?= e($sub['title']) ?><?php if (!empty($sub['external'])): ?> <i class="fas fa-arrow-up-right-from-square sh-ext" aria-hidden="true"></i><?php endif; ?></span>
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
        <?php if ($sitePhoneHref !== ''): ?>
          <a href="<?= e($sitePhoneHref) ?>" class="sh-phone" aria-label="Call <?= e($sitePhoneLabel) ?>">
            <span class="sh-phone-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
            <span class="sh-phone-text"><small>Call us</small><?= e($sitePhoneLabel) ?></span>
          </a>
        <?php endif; ?>
        <?php if ($siteMenu['button_label'] !== ''): ?>
          <a href="<?= e($siteMenu['button_url']) ?>" class="sh-btn sh-btn-primary sh-book" aria-label="<?= e($siteMenu['button_label']) ?>">
            <i class="fas fa-calendar-check" aria-hidden="true"></i>
            <span class="sh-book-text"><?= e($siteMenu['button_label']) ?></span>
          </a>
        <?php endif; ?>
        <button type="button" class="sh-burger" aria-controls="shDrawer" aria-expanded="false" aria-label="Open menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>

    <?php if (count($siteCrumbs) > 1): ?>
      <div class="sh-crumbs">
        <nav class="sh-bar" aria-label="Breadcrumb">
          <ol class="sh-crumb-list">
            <?php foreach ($siteCrumbs as $i => $crumb): ?>
              <?php if ($i === count($siteCrumbs) - 1): ?>
                <li><span aria-current="page"><?= e($crumb['name']) ?></span></li>
              <?php else: ?>
                <li><a href="<?= e($crumb['path']) ?>"><?php if ($i === 0): ?><i class="fas fa-house" aria-hidden="true"></i> <?php endif; ?><?= e($crumb['name']) ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol>
        </nav>
      </div>
    <?php endif; ?>
  </header>

  <div class="sh-overlay" id="shOverlay" data-sh-close></div>
  <aside class="sh-drawer" id="shDrawer" aria-label="Menu" aria-hidden="true" inert>
    <div class="sh-drawer-head">
      <a href="/" class="sh-logo" aria-label="Verma Accounting home">
        <img src="<?= asset('images/verma-accounting-logo-340.webp') ?>" alt="Verma Accounting" width="150" height="36" loading="lazy" />
      </a>
      <button type="button" class="sh-close" data-sh-close aria-label="Close menu"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <nav class="sh-drawer-nav" aria-label="Mobile">
      <?php foreach ($siteNav as $item): ?>
        <?php if ($item['type'] === 'link'): ?>
          <a href="<?= e($item['url']) ?>" class="sh-dlink"<?= $siteLinkAttrs($item) ?>>
            <span class="sh-dlabel"><?= e($item['label']) ?></span>
          </a>
        <?php else: ?>
          <div class="sh-dgroup">
            <button type="button" class="sh-dlink sh-dtoggle" aria-expanded="false" aria-controls="sh-d-<?= e($item['id']) ?>">
              <span class="sh-dlabel"><?= e($item['label']) ?></span>
              <i class="fas fa-chevron-down sh-caret" aria-hidden="true"></i>
            </button>
            <div class="sh-dsub" id="sh-d-<?= e($item['id']) ?>" hidden>
              <?php foreach ($item['items'] as $sub): ?>
                <a href="<?= e($sub['url']) ?>" class="sh-dsub-link"<?= $siteLinkAttrs($sub) ?>>
                  <?= e($sub['title']) ?>
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
      <?php if ($siteMenu['button_label'] !== ''): ?>
        <a href="<?= e($siteMenu['button_url']) ?>" class="sh-btn sh-btn-primary sh-btn-block"><i class="fas fa-calendar-check" aria-hidden="true"></i> <?= e($siteMenu['button_label']) ?></a>
      <?php endif; ?>
      <div class="sh-drawer-contact">
        <?php if ($sitePhoneHref !== ''): ?>
          <a href="<?= e($sitePhoneHref) ?>"><i class="fas fa-phone" aria-hidden="true"></i> <?= e($sitePhoneLabel) ?></a>
        <?php endif; ?>
        <a href="<?= e(company_email_href()) ?>"><i class="fas fa-envelope" aria-hidden="true"></i> <?= e(company_email()) ?></a>
      </div>
    </div>
  </aside>
  <div id="main-content" tabindex="-1"></div>
