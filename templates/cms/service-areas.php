<?php
/** @var array $cms */
$c = $cms['content'];
$e = [CmsRenderer::class, 'e'];
$h1 = trim((string) ($c['h1'] ?? '')) ?: (string) $c['title'];
$areas = $cms['service_areas'] ?? [];
$byRegion = [];
foreach ($areas as $area) {
    $byRegion[$area['region'] !== '' ? $area['region'] : 'Other areas'][] = $area;
}
$searchKey = static function (string $name): string {
    $name = mb_strtolower($name);
    if (class_exists('Normalizer')) {
        $name = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($name, Normalizer::FORM_D));
    }
    return $name;
};
uksort($byRegion, static fn ($a, $b) => ($a === 'Other areas') <=> ($b === 'Other areas') ?: strcasecmp($a, $b));
?>
<main class="main-content cms-main" id="main">
  <section class="common-section">
    <div class="container">
      <h1 class="section-title areas-title"><?= $e($h1) ?></h1>
      <?php if (trim(strip_tags((string) $cms['body'])) !== ''): ?>
        <div class="cms-body areas-intro"><?= $cms['body'] ?></div>
      <?php endif; ?>
      <?php if ($areas === []): ?>
        <p class="areas-empty">We serve clients across Ontario and Canada online. <a href="/contact">Contact us</a> to ask about your area.</p>
      <?php else: ?>
        <div class="areas-toolbar">
          <label class="areas-search">
            <i class="fas fa-search" aria-hidden="true"></i>
            <span class="visually-hidden">Find your city</span>
            <input type="search" id="areas-search" placeholder="Find your city…" autocomplete="off">
          </label>
          <p class="areas-count" id="areas-count" aria-live="polite"><?= count($areas) ?> <?= count($areas) === 1 ? 'area' : 'areas' ?></p>
        </div>

        <?php foreach ($byRegion as $region => $list): ?>
          <section class="areas-region" aria-labelledby="region-<?= $e(md5($region)) ?>">
            <h2 id="region-<?= $e(md5($region)) ?>"><?= $e($region) ?></h2>
            <ul class="areas-list">
              <?php foreach ($list as $area): ?>
                <li data-name="<?= $e($searchKey($area['name'])) ?>"><a href="<?= $e($area['path']) ?>"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= $e($area['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endforeach; ?>
        <p class="areas-empty" id="areas-none" hidden>No matching city.</p>
        <p class="areas-note"><strong>Don't see your city?</strong> We also work with clients online anywhere in Canada. <a href="/contact">Contact us</a> to ask about your area.</p>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php if ($areas !== []): ?>
<script>
  (function () {
    var input = document.getElementById('areas-search');
    var items = document.querySelectorAll('.areas-list li');
    var regions = document.querySelectorAll('.areas-region');
    var count = document.getElementById('areas-count');
    var none = document.getElementById('areas-none');
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      var shown = 0;
      items.forEach(function (li) {
        var match = q === '' || li.getAttribute('data-name').indexOf(q) !== -1;
        li.hidden = !match;
        if (match) shown++;
      });
      regions.forEach(function (r) {
        r.hidden = r.querySelectorAll('li:not([hidden])').length === 0;
      });
      count.textContent = shown + (shown === 1 ? ' area' : ' areas');
      none.hidden = shown !== 0;
    });
  })();
</script>
<?php endif; ?>
