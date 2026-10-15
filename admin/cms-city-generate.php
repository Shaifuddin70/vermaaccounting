<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.edit');

$repo = new CmsContentRepository();
$caps = cms_admin_caps();
$canPublish = (bool) $caps['publish'];
$cityDefaults = CmsCityDefaults::get();

$input = [
    'cities' => '',
    'service' => '',
    'folder_id' => 0,
    'prefix' => '/locations',
    'publish' => false,
];
$preview = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    foreach (['cities', 'service', 'prefix'] as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $input['publish'] = $canPublish && !empty($_POST['publish']);
    $input['folder_id'] = (int) ($_POST['folder_id'] ?? 0);
    $prefix = $input['prefix'] === '' || $input['prefix'] === '/' ? '' : CmsPath::normalize($input['prefix']);

    $cities = [];
    $seen = [];
    foreach (preg_split('/\R/', $input['cities']) ?: [] as $line) {
        $parts = array_map('trim', explode(',', $line));
        if (($parts[0] ?? '') === '' || isset($seen[mb_strtolower($parts[0])])) {
            continue;
        }
        $seen[mb_strtolower($parts[0])] = true;
        $cities[] = ['city' => $parts[0], 'region' => $parts[1] ?? '', 'country' => $parts[2] ?? 'Canada'];
    }

    $errors = [];
    if ($cities === []) {
        $errors[] = 'Enter at least one city.';
    }
    if (count($cities) > 100) {
        $errors[] = 'Add at most 100 pages at a time.';
    }
    if ($prefix !== '') {
        foreach (CmsPath::validate($prefix) as $problem) {
            $errors[] = 'URL prefix: ' . $problem;
        }
    }

    if ($errors === []) {
        $preview = [];
        foreach ($cities as $c) {
            $path = CmsPath::join($prefix, CmsPath::slugify($c['city']));
            $problem = $repo->pathTaken($path) ? 'URL already in use' : (CmsPath::validate($path)[0] ?? null);
            $title = CmsCityDefaults::fill('title', $c + ['primary_service' => $input['service']]);
            $preview[] = $c + ['title' => $title, 'path' => $path, 'problem' => $problem];
        }
        if (($_POST['step'] ?? '') === 'create') {
            $ready = array_map(
                static fn ($p) => ['city' => $p['city'], 'region' => $p['region'], 'country' => $p['country'], 'service' => $input['service'], 'path' => $p['path']],
                array_values(array_filter($preview, static fn ($p) => $p['problem'] === null))
            );
            $result = (new CmsContentService())->createDefaultCityPages($ready, $input['folder_id'] ?: null, $input['publish'], $caps);
            $message = count($result['created']) . ' city page(s) ' . ($input['publish'] ? 'published' : 'created as drafts') . '. They show the default city content until you add your own.';
            $skipped = count($preview) - count($ready);
            $problems = $result['errors'];
            if ($skipped > 0) {
                array_unshift($problems, $skipped . ' skipped because the URL is taken or invalid.');
            }
            cms_admin_back('/admin/cms-content?type=city' . ($input['publish'] ? '' : '&status=draft'), $message, $problems ? implode(' ', $problems) : null);
        }
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
    }
}

$csrf = Auth::csrfToken();
$pageTitle = 'Add city pages';
$activeNav = 'cms-city';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Add city pages</h1>
  <div class="admin-header-actions">
    <?php if ($canPublish): ?><a class="admin-btn admin-btn-secondary" href="/admin/cms-city-defaults">Default content</a><?php endif; ?>
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-content?type=city">City pages</a>
  </div>
</div>

<form method="post" action="/admin/cms-city-generate" class="admin-card">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <p class="admin-field-hint">Enter city names only. The title, description and body come from the <?= $canPublish ? '<a href="/admin/cms-city-defaults">city page defaults</a>' : 'city page defaults' ?>. Type your own content into a page later and it replaces the default on that page.</p>
  <div class="admin-fields-2col cms-mt">
    <div class="admin-field">
      <label for="g-cities">Cities (one per line: City, Province, Country)</label>
      <textarea id="g-cities" name="cities" rows="8" placeholder="Orleans, Ontario&#10;Kanata, Ontario&#10;Gatineau, Quebec" required><?= e($input['cities']) ?></textarea>
      <p class="admin-field-hint">Province and country are optional; country defaults to Canada.</p>
    </div>
    <div>
      <div class="admin-field">
        <label for="g-service">Primary service</label>
        <input type="text" id="g-service" name="service" value="<?= e($input['service']) ?>" placeholder="<?= e((string) $cityDefaults['service']) ?>">
        <p class="admin-field-hint">Leave empty to use the default service (<?= e((string) $cityDefaults['service']) ?>).</p>
      </div>
      <div class="admin-field">
        <label for="g-folder">Folder</label>
        <select id="g-folder" name="folder_id" data-prefix-target="#g-prefix"><?= cms_admin_folder_options($input['folder_id'] ?: null) ?></select>
      </div>
      <div class="admin-field">
        <label for="g-prefix">URL prefix</label>
        <input type="text" id="g-prefix" name="prefix" value="<?= e($input['prefix']) ?>">
        <p class="admin-field-hint">Each page's URL is the prefix plus the city name, e.g. <code>/locations/orleans</code>.</p>
      </div>
    </div>
  </div>

  <?php if ($canPublish): ?>
    <label class="admin-checkbox-label"><input type="checkbox" name="publish" value="1" <?= $input['publish'] ? 'checked' : '' ?>> <span>Publish the pages now (otherwise they are saved as drafts)</span></label>
  <?php endif; ?>

  <div class="cms-form-actions">
    <button type="submit" name="step" value="preview" class="admin-btn admin-btn-secondary">Preview</button>
    <?php if ($preview !== null): ?>
      <?php $ok = count(array_filter($preview, fn ($p) => $p['problem'] === null)); ?>
      <button type="submit" name="step" value="create" class="admin-btn" data-busy="Creating…" <?= $ok === 0 ? 'disabled' : '' ?>>Create <?= $ok ?> page(s)</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($preview !== null): ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Preview</h2>
    <div class="cms-table-scroll">
    <table class="admin-table">
      <thead><tr><th>City</th><th>Title</th><th>URL</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($preview as $p): ?>
        <tr>
          <td><?= e($p['city']) ?><?= $p['region'] !== '' ? ', ' . e($p['region']) : '' ?></td>
          <td><?= e($p['title']) ?> <span class="admin-badge admin-badge-muted">default</span></td>
          <td><code><?= e($p['path']) ?></code></td>
          <td><?= $p['problem'] === null ? '<span class="admin-badge admin-badge-success">Will be created</span>' : '<span class="admin-badge admin-badge-muted">Skipped: ' . e($p['problem']) . '</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
