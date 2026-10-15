<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.publish');

$sessionKey = 'cms_city_defaults_old';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $input = array_intersect_key($_POST, CmsCityDefaults::defaults());
    $errors = CmsCityDefaults::save($input);
    if ($errors) {
        $_SESSION[$sessionKey] = $input;
        cms_admin_back('/admin/cms-city-defaults', null, implode(' ', $errors));
    }
    $service = new CmsContentService();
    $repo = new CmsContentRepository();
    foreach ($repo->search(['type' => 'city'], 1000) as $row) {
        if (CmsCityDefaults::bodyIsEmpty((string) $row['body_html'])) {
            $service->indexLinks((int) $row['id'], CmsCityDefaults::fill('body_html', $repo->city((int) $row['id'])));
        }
    }
    cms_admin_back('/admin/cms-city-defaults', 'Saved. Every city page without its own content shows the new defaults now.');
}

$values = CmsCityDefaults::get();
$old = $_SESSION[$sessionKey] ?? null;
unset($_SESSION[$sessionKey]);
if (is_array($old)) {
    $values = array_merge($values, array_map('strval', $old));
}

$repo = new CmsContentRepository();
$cityPages = $repo->search(['type' => 'city'], 1000);
$sampleId = (int) ($_GET['sample'] ?? ($cityPages[0]['id'] ?? 0));
$sample = $sampleId ? $repo->find($sampleId) : null;
$sampleCity = $sample ? $repo->city($sampleId) : ['city' => 'Kanata', 'region' => 'Ontario', 'country' => 'Canada'];
$usage = CmsCityDefaults::usage();
$csrf = Auth::csrfToken();

$pageTitle = 'City page defaults';
$activeNav = 'cms-city';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>City page defaults</h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-content?type=city">City pages</a>
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-city-generate">Add city pages</a>
  </div>
</div>

<div class="admin-card">
  <p class="admin-field-hint">City pages only need a city name. Any field left empty on a city page uses the default below, with the placeholders filled in for that city. As soon as you type your own text into a field on a city page, that page shows your text for that field instead. Changes here update every city page that still uses the default.</p>
  <p class="cms-mt"><strong>Pages using each default</strong> (of <?= (int) $usage['total'] ?> city pages):
    <?php $parts = []; foreach (CmsCityDefaults::FIELDS as $field => $label) { $parts[] = e($label) . ' <strong>' . (int) $usage['fields'][$field] . '</strong>'; } echo implode(' · ', $parts); ?>
  </p>
</div>

<form method="post" action="/admin/cms-city-defaults" class="admin-card">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <h2 class="admin-card-title">Defaults</h2>
  <p class="admin-field-hint">Placeholders:
    <?php foreach (CmsCityDefaults::PLACEHOLDERS as $ph => $desc): ?><code title="<?= e($desc) ?>"><?= e($ph) ?></code> <?php endforeach; ?>
    · Reusable blocks (<code>{{block:name}}</code>) work in the body.</p>
  <div class="admin-fields-2col cms-mt">
    <div class="admin-field admin-field--full">
      <label for="d-title">Title <span class="cms-req">*</span></label>
      <input type="text" id="d-title" name="title" value="<?= e((string) $values['title']) ?>" maxlength="500" required>
      <p class="admin-field-hint">Must include <code>{city}</code>. Used for the page heading too, unless you set a heading below.</p>
    </div>
    <div class="admin-field">
      <label for="d-h1">Page heading (H1)</label>
      <input type="text" id="d-h1" name="h1" value="<?= e((string) $values['h1']) ?>" maxlength="500" placeholder="Same as title">
    </div>
    <div class="admin-field">
      <label for="d-service">Default service for {service}</label>
      <input type="text" id="d-service" name="service" value="<?= e((string) $values['service']) ?>" maxlength="191">
      <p class="admin-field-hint">Used when a city page has no primary service of its own.</p>
    </div>
    <div class="admin-field admin-field--full">
      <label for="d-excerpt">Summary</label>
      <textarea id="d-excerpt" name="excerpt" rows="2" maxlength="1000"><?= e((string) $values['excerpt']) ?></textarea>
      <p class="admin-field-hint">Shown under the heading.</p>
    </div>
    <div class="admin-field admin-field--full">
      <label for="d-seo-title">SEO title</label>
      <input type="text" id="d-seo-title" name="seo_title" value="<?= e((string) $values['seo_title']) ?>" maxlength="500" placeholder="Title + site suffix">
    </div>
    <div class="admin-field admin-field--full">
      <label for="d-desc">Meta description</label>
      <textarea id="d-desc" name="meta_description" rows="2"><?= e((string) $values['meta_description']) ?></textarea>
      <p class="admin-field-hint">Include <code>{city}</code> so every page has its own description. Leave empty to use the summary.</p>
    </div>
    <div class="admin-field admin-field--full cms-code-field">
      <label for="d-body">Body (HTML)</label>
      <textarea id="d-body" name="body_html" rows="18" class="cms-code" spellcheck="false"><?= e((string) $values['body_html']) ?></textarea>
      <p class="admin-field-hint">Use Heading 2 for sections. Scripts and unsafe markup are removed when saved; use a block for embeds. <code>{{contact_form}}</code> shows the site inquiry form.</p>
    </div>
    <div class="admin-field admin-field--full">
      <label for="d-faqs">FAQs</label>
      <textarea id="d-faqs" name="faqs" rows="12" spellcheck="true"><?= e((string) $values['faqs']) ?></textarea>
      <p class="admin-field-hint">One question per block: the question on the first line, the answer on the lines below, and a blank line between questions. Shown as the FAQ section (with FAQ rich-result data for Google) on city pages that have no FAQs of their own. Plain text only.</p>
    </div>
  </div>
  <div class="cms-form-actions">
    <button type="submit" class="admin-btn" data-busy="Saving…" data-confirm="Update the defaults? Every city page without its own content changes immediately.">Save defaults</button>
  </div>
</form>

<div class="admin-card">
  <div class="cms-card-head">
    <h2 class="admin-card-title">Preview (saved defaults)</h2>
    <?php if ($cityPages !== []): ?>
      <form method="get" action="/admin/cms-city-defaults" class="cms-inline-form">
        <select name="sample" aria-label="Preview for city" onchange="this.form.submit()">
          <?php foreach ($cityPages as $p): ?>
            <?php $pc = $repo->city((int) $p['id']); ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $sampleId ? 'selected' : '' ?>><?= e((string) ($pc['city'] ?? '') ?: CmsCityDefaults::label($p)) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Show</button></noscript>
      </form>
    <?php endif; ?>
  </div>
  <?php
  $title = CmsCityDefaults::fill('title', $sampleCity);
  $rows = [
      'Title' => $title,
      'Page heading (H1)' => CmsCityDefaults::fill('h1', $sampleCity) ?: $title,
      'Summary' => CmsCityDefaults::fill('excerpt', $sampleCity),
      'SEO title' => CmsCityDefaults::fill('seo_title', $sampleCity) ?: $title . CmsSiteConfig::value('title_suffix'),
      'Meta description' => CmsCityDefaults::fill('meta_description', $sampleCity),
  ];
  ?>
  <p class="admin-field-hint">As shown for <strong><?= e((string) ($sampleCity['city'] ?? '')) ?></strong> when that page has no content of its own.<?php if ($sample): ?> <a href="<?= e((string) $sample['path']) ?>" target="_blank" rel="noopener">Open the page</a><?php endif; ?></p>
  <table class="admin-table cms-mt">
    <tbody>
    <?php foreach ($rows as $label => $value): ?>
      <tr><th style="width:12rem"><?= e($label) ?></th><td><?= $value !== '' ? e($value) : '<span class="admin-field-hint">—</span>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php $body = CmsCityDefaults::fill('body_html', $sampleCity); ?>
  <div class="cms-default-preview cms-mt"><?= $body !== '' ? $body : '<p class="admin-field-hint">No default body yet.</p>' ?></div>
  <?php if ($body !== '' && (CmsBlocks::referencedTokens($body) !== [] || str_contains($body, '{{contact_form}}'))): ?><p class="admin-field-hint">Blocks and the contact form are shown as tokens here and render on the live page.</p><?php endif; ?>
  <?php $faqs = CmsCityDefaults::faqs($sampleCity); ?>
  <?php if ($faqs !== []): ?>
    <h3 class="cms-mt">FAQs</h3>
    <dl class="cms-default-preview">
      <?php foreach ($faqs as $faq): ?><dt><strong><?= e($faq['question']) ?></strong></dt><dd><?= nl2br(e($faq['answer'])) ?></dd><?php endforeach; ?>
    </dl>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
