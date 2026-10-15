<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.code', 'cms.seo');

$canCode = Auth::can('cms.code');
$canSeo = Auth::can('cms.seo');
$sessionKey = 'cms_site_config_old';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['seed'] ?? '') !== '') {
    if (!$canCode) {
        json_response(['error' => 'Site code permission required.'], 403);
    }
    json_response(['html' => CmsRenderer::chromeMarkup((string) $_GET['seed'])]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? 'save');
    $input = $_POST;
    unset($input['csrf_token'], $input['action'], $input['comment'], $input['preview_target'], $input['home_content_id']);
    if (!$canSeo) {
        foreach (array_keys(CmsSiteConfig::defaults()) as $key) {
            if (!in_array($key, CmsSiteConfig::CODE_FIELDS, true)) {
                unset($input[$key]);
            }
        }
    }

    if ($action === 'preview') {
        [$candidate, $errors] = CmsSiteConfig::candidate($input, $canCode);
        if ($errors) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Preview</title><body style="font-family:sans-serif;padding:2rem"><h1>Fix these first</h1><ul>';
            foreach ($errors as $err) {
                echo '<li>' . e($err) . '</li>';
            }
            echo '</ul></body>';
            exit;
        }
        CmsSiteConfig::override($candidate);
        $target = (string) ($_POST['preview_target'] ?? '404');
        $content = ctype_digit($target) ? (new CmsContentRepository())->find((int) $target) : null;
        $response = $content ? CmsRenderer::render($content, ['preview' => true]) : CmsRenderer::notFound();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        echo $response['body'];
        exit;
    }

    if ($action === 'restore') {
        $rev = CmsSiteConfig::revision((int) ($_POST['revision_id'] ?? 0));
        if ($rev === null) {
            cms_admin_back('/admin/cms-site-config', null, 'Revision not found.');
        }
        $restore = $rev['config'];
        unset($restore['home_content_id']);
        if (!$canSeo) {
            $restore = array_intersect_key($restore, array_flip(CmsSiteConfig::CODE_FIELDS));
        }
        $errors = CmsSiteConfig::save($restore, $canCode, 'Restored revision #' . (int) $rev['id']);
        if ($errors) {
            cms_admin_back('/admin/cms-site-config?compare=' . (int) $rev['id'], null, implode(' ', $errors));
        }
        ActivityLog::record('cms.site_config_restored', 'cms_site_config', (int) $rev['id']);
        cms_admin_back('/admin/cms-site-config', 'Configuration restored from ' . cms_admin_date((string) $rev['created_at']) . ($canCode ? '' : ' (code fields need site code permission and were not changed)') . '.');
    }

    $errors = CmsSiteConfig::save($input, $canCode, trim((string) ($_POST['comment'] ?? '')));
    if ($errors) {
        $_SESSION[$sessionKey] = $input;
        cms_admin_back('/admin/cms-site-config', null, implode(' ', $errors));
    }
    ActivityLog::record('cms.site_config_saved', 'cms_site_config', null, ['comment' => mb_substr((string) ($_POST['comment'] ?? ''), 0, 100)]);
    cms_admin_back('/admin/cms-site-config' . (isset($_POST['tab']) ? '#tab-' . preg_replace('/[^a-z]/', '', (string) $_POST['tab']) : ''), 'Site configuration saved. The page cache was cleared.');
}

$config = CmsSiteConfig::get();
if (isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
    $config = array_merge($config, $_SESSION[$sessionKey]);
}
unset($_SESSION[$sessionKey]);

$compare = isset($_GET['compare']) ? CmsSiteConfig::revision((int) $_GET['compare']) : null;
$revisions = CmsSiteConfig::revisions(30);
$warnings = CmsSiteConfig::warnings($config);
$previewTargets = (new CmsContentRepository())->search(['status' => 'published', 'sort' => 'path', 'dir' => 'asc'], 300);
$csrf = Auth::csrfToken();

/**
 * Field spec: [name, label, kind, hint, options]
 * kinds: text, textarea, code, select, number, checkbox
 */
$sections = [
    'general' => ['General', false, [
        ['site_name', 'Site name', 'text', 'Used in og:site_name and structured data.'],
        ['base_url', 'Public base URL', 'text', 'The live domain, e.g. https://vermaaccounting.ca. Canonicals, the sitemap and social tags use it.'],
        ['language', 'Default language', 'text', 'For example en-CA.'],
    ]],
    'company' => ['Company details', false, [
        ['company_phone', 'Phone number', 'tel', 'Shown in the header, footer, contact page and every “Call” button. Links dial the digits.'],
        ['company_email', 'Email address', 'email', 'Shown in the footer, menu and contact sections.'],
        ['company_hours', 'Business hours', 'text', 'For example “Mon – Sun: 9:00 AM – 6:00 PM”.'],
        ['company_street', 'Street address', 'text', ''],
        ['company_city', 'City', 'text', ''],
        ['company_region', 'Province / state', 'text', 'For example ON.'],
        ['company_postal_code', 'Postal code', 'text', ''],
        ['company_country', 'Country code', 'text', 'For example CA.'],
    ]],
    'seo' => ['SEO defaults', false, [
        ['title_suffix', 'Title suffix', 'text', 'Appended to page titles that have no custom SEO title, e.g. “ | Verma Accounting”.'],
        ['default_description', 'Fallback meta description', 'textarea', 'Only used when a page has no description, summary or body text.'],
        ['default_og_image', 'Default social image', 'text', 'A /path or https:// URL, ideally 1200×630.'],
        ['default_og_image_alt', 'Default social image alt text', 'text', ''],
        ['twitter_card', 'X / Twitter card', 'select', '', ['summary_large_image' => 'summary_large_image', 'summary' => 'summary']],
        ['robots_index', 'Default indexing', 'select', 'Pages and folders set to “inherit” use this.', ['index' => 'index', 'noindex' => 'noindex']],
        ['robots_follow', 'Default link following', 'select', '', ['follow' => 'follow', 'nofollow' => 'nofollow']],
        ['max_snippet', 'max-snippet', 'number', '-1 means no limit; 0 means nosnippet.'],
        ['max_image_preview', 'max-image-preview', 'select', '', ['large' => 'large', 'standard' => 'standard', 'none' => 'none']],
        ['max_video_preview', 'max-video-preview', 'number', '-1 means no limit.'],
        ['schema_website', 'Output WebSite structured data', 'checkbox', ''],
        ['schema_organization', 'Output Organization / LocalBusiness structured data', 'checkbox', ''],
        ['thin_content_words', 'Thin content threshold (words)', 'number', 'Pages below this are flagged in the audit.'],
        ['city_similarity_threshold', 'City page similarity warning (%)', 'number', 'Warn when two city pages are at least this similar once city names are ignored.'],
    ]],
    'chrome' => ['Header & footer', true, [
        ['header_html', 'Header HTML', 'code', 'Placed right after &lt;body&gt;. Keep {{site_header}} to use the standard navigation with menus that update automatically.'],
        ['footer_html', 'Footer HTML', 'code', 'Keep {{site_footer}} to use the standard footer.'],
    ]],
    'scripts' => ['Head & scripts', true, [
        ['verification_tags', 'Verification meta tags', 'code', 'Search Console, Bing, Pinterest and similar &lt;meta&gt; tags.'],
        ['analytics_ga4_id', 'GA4 measurement ID', 'text', 'For example G-ABC123XYZ. Leave empty if analytics is added another way.'],
        ['head_markup', 'Extra &lt;head&gt; markup', 'code', 'Preloads, meta tags and similar. Title, description, canonical and robots tags are generated per page and are rejected here.'],
        ['head_scripts', 'Scripts in &lt;head&gt;', 'code', 'Tag managers and similar.'],
        ['body_start_scripts', 'Scripts after &lt;body&gt;', 'code', 'For example a Tag Manager &lt;noscript&gt; iframe.'],
        ['body_end_scripts', 'Scripts before &lt;/body&gt;', 'code', 'Chat widgets and similar.'],
    ]],
    'assets' => ['Styles & assets', true, [
        ['shared_styles', 'Stylesheets', 'code', 'One https:// URL or /path per line, loaded on every CMS page in this order. Local files get a ?v= stamp automatically. To change the files themselves, use <a href="/admin/cms-stylesheets">Stylesheets</a>.'],
        ['shared_scripts', 'Scripts', 'code', 'One https:// URL or /path per line, loaded with defer.'],
        ['global_css', 'Global CSS', 'code', 'Plain CSS added in a &lt;style&gt; block on every CMS page.'],
        ['global_js', 'Global JavaScript', 'code', 'Plain JavaScript added before &lt;/body&gt;.'],
    ]],
    'notfound' => ['404 page', false, [
        ['not_found_title', '404 heading', 'text', ''],
        ['not_found_html', '404 body HTML', 'code', 'Shown for missing pages. Always sent with a real 404 status and noindex.'],
    ]],
];

$pageTitle = 'Site configuration';
$activeNav = 'cms-site-config';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="admin-header">
  <h1>Site configuration</h1>
</div>

<?php foreach ($warnings as $w): ?>
  <div class="admin-alert admin-alert-info"><?= e($w) ?></div>
<?php endforeach; ?>
<?php if (!$canCode): ?>
  <div class="admin-alert admin-alert-info">Header, footer, scripts and other code fields need site code permission. You can change the SEO defaults.</div>
<?php endif; ?>

<?php if ($compare): ?>
  <?php $changed = CmsSiteConfig::changedKeys($compare['config'], CmsSiteConfig::get()); ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Revision from <?= e(cms_admin_date((string) $compare['created_at'])) ?> compared with now</h2>
    <p class="admin-table-sub"><?= e((string) ($compare['comment'] ?: 'No comment')) ?> · <?= e((string) ($compare['user_name'] ?: 'unknown')) ?></p>
    <?php if ($changed === []): ?>
      <p class="admin-field-hint">Identical to the current configuration.</p>
    <?php else: ?>
      <div class="cms-table-scroll">
        <table class="cms-diff-table">
          <thead><tr><th style="width:12rem">Setting</th><th>In this revision</th><th>Current</th></tr></thead>
          <tbody>
          <?php foreach ($changed as $key): ?>
            <tr><td><?= e(str_replace('_', ' ', $key)) ?></td><td class="cms-diff-del"><?= e((string) $compare['config'][$key]) ?></td><td class="cms-diff-add"><?= e((string) CmsSiteConfig::value($key)) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <form method="post" action="/admin/cms-site-config" class="cms-form-actions">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="revision_id" value="<?= (int) $compare['id'] ?>">
      <?php if ($changed !== []): ?><button type="submit" class="admin-btn" data-confirm="Restore this configuration? The current one is kept as a revision.">Restore this revision</button><?php endif; ?>
      <a class="admin-btn admin-btn-secondary" href="/admin/cms-site-config">Close</a>
    </form>
  </div>
<?php endif; ?>

<form method="post" action="/admin/cms-site-config" id="cms-config-form" data-csrf="<?= e($csrf) ?>">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <div>
    <nav class="admin-tabs cms-tabs" role="tablist" aria-label="Configuration sections">
      <?php foreach ($sections as $key => [$label, $isCode]): ?>
        <a href="#tab-<?= $key ?>" class="admin-tab cms-tab" role="tab" data-tab="<?= $key ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="#tab-revisions" class="admin-tab cms-tab" role="tab" data-tab="revisions">Revisions <span class="admin-tab-count"><?= count($revisions) ?></span></a>
    </nav>

    <?php foreach ($sections as $key => [$label, $isCode, $fields]): ?>
      <section class="admin-card cms-panel" id="tab-<?= $key ?>" data-panel="<?= $key ?>">
        <h2 class="admin-card-title"><?= e($label) ?></h2>
        <?php if ($key === 'chrome' && $canCode): ?>
          <p class="admin-field-hint">
            Need to change the navigation markup itself? Load the current markup into the editor. Menus become fixed HTML, so keep <code>{{submit_menu}}</code> where the Submit Document links belong.
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-seed="header">Load current header HTML</button>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" data-seed="footer">Load current footer HTML</button>
          </p>
          <p class="admin-field-hint">Placeholders:
            <?php foreach (CmsRenderer::placeholders() as $ph => $desc): ?><code title="<?= e($desc) ?>"><?= e($ph) ?></code> <?php endforeach; ?>
          </p>
        <?php endif; ?>
        <div class="admin-fields-2col">
          <?php foreach ($fields as $field): ?>
            <?php
            [$name, $flabel, $kind, $hint] = $field;
            $options = $field[4] ?? [];
            $locked = in_array($name, CmsSiteConfig::CODE_FIELDS, true) ? !$canCode : !$canSeo;
            $value = $config[$name] ?? '';
            $full = in_array($kind, ['textarea', 'code'], true);
            ?>
            <div class="admin-field<?= $full ? ' admin-field--full cms-code-field' : '' ?>">
              <?php if ($kind === 'checkbox'): ?>
                <input type="hidden" name="<?= e($name) ?>" value="0" <?= $locked ? 'disabled' : '' ?>>
                <label class="admin-checkbox-label"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>> <span><?= $flabel ?></span></label>
              <?php else: ?>
                <label for="sc-<?= e($name) ?>"><?= $flabel ?></label>
                <?php if ($kind === 'select'): ?>
                  <select id="sc-<?= e($name) ?>" name="<?= e($name) ?>" <?= $locked ? 'disabled' : '' ?>>
                    <?php foreach ($options as $ov => $ol): ?><option value="<?= e((string) $ov) ?>" <?= (string) $value === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
                  </select>
                <?php elseif ($kind === 'number'): ?>
                  <input type="number" id="sc-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>" <?= $locked ? 'disabled' : '' ?>>
                <?php elseif ($kind === 'textarea'): ?>
                  <textarea id="sc-<?= e($name) ?>" name="<?= e($name) ?>" rows="3" <?= $locked ? 'disabled' : '' ?>><?= e((string) $value) ?></textarea>
                <?php elseif ($kind === 'code'): ?>
                  <textarea id="sc-<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= in_array($name, ['header_html', 'footer_html', 'global_css', 'not_found_html'], true) ? 10 : 6 ?>" class="cms-code" spellcheck="false" <?= $locked ? 'disabled' : '' ?>><?= e((string) $value) ?></textarea>
                <?php else: ?>
                  <input type="<?= in_array($kind, ['email', 'tel'], true) ? $kind : 'text' ?>" id="sc-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>" <?= $locked ? 'disabled' : '' ?>>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($hint !== ''): ?><p class="admin-field-hint"><?= $hint ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($key === 'company'): ?>
          <h3 class="cms-subheading">Social media</h3>
          <p class="admin-field-hint">Switched-on profiles appear as icons in the footer and the floating chat button, and are listed in the site's structured data (WhatsApp excluded). Turn one off to hide it without losing the link.</p>
          <div class="cms-social-list">
            <?php foreach (CmsCompany::SOCIAL as $sk => [$slabel, $sicon, $sexample]): ?>
              <?php $on = (int) ($config['social_' . $sk . '_on'] ?? 0) === 1; ?>
              <div class="cms-social-row">
                <span class="cms-social-icon" aria-hidden="true"><i class="<?= e($sicon) ?>"></i></span>
                <label class="cms-social-name" for="sc-social_<?= e($sk) ?>_url"><?= e($slabel) ?></label>
                <input type="url" id="sc-social_<?= e($sk) ?>_url" name="social_<?= e($sk) ?>_url" value="<?= e((string) ($config['social_' . $sk . '_url'] ?? '')) ?>" placeholder="<?= e($sexample) ?>" <?= !$canSeo ? 'disabled' : '' ?>>
                <input type="hidden" name="social_<?= e($sk) ?>_on" value="0" <?= !$canSeo ? 'disabled' : '' ?>>
                <label class="cms-switch" title="Show <?= e($slabel) ?> on the website">
                  <input type="checkbox" name="social_<?= e($sk) ?>_on" value="1" <?= $on ? 'checked' : '' ?> <?= !$canSeo ? 'disabled' : '' ?>>
                  <span class="cms-switch-track" aria-hidden="true"></span>
                  <span class="cms-switch-label"><?= $on ? 'On' : 'Off' ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="admin-field-hint">Use these in page bodies, blocks and the header/footer code: <code>{{company_phone}}</code> <code>{{company_phone_link}}</code> <code>{{company_email}}</code> <code>{{company_email_link}}</code> <code>{{company_address}}</code> <code>{{company_hours}}</code> <code>{{social_links}}</code>. For example <code>&lt;a href="{{company_phone_link}}"&gt;{{company_phone}}&lt;/a&gt;</code>.</p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <section class="admin-card cms-panel" id="tab-revisions" data-panel="revisions">
      <h2 class="admin-card-title">Revisions</h2>
      <?php if ($revisions === []): ?>
        <p class="admin-field-hint">No saved revisions yet. Every save creates one.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>When</th><th>By</th><th>Note</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($revisions as $rev): ?>
            <tr>
              <td><?= e(cms_admin_date((string) $rev['created_at'])) ?></td>
              <td><?= e((string) ($rev['user_name'] ?: '—')) ?></td>
              <td><?= e((string) $rev['comment']) ?></td>
              <td class="admin-table-actions"><a class="admin-btn admin-btn-sm admin-btn-secondary" href="/admin/cms-site-config?compare=<?= (int) $rev['id'] ?>">Compare / restore</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <div class="admin-card">
    <div class="admin-fields-2col">
      <div class="admin-field">
        <label for="sc-comment">Change note</label>
        <input type="text" id="sc-comment" name="comment" maxlength="255" placeholder="What changed? (optional)">
      </div>
      <div class="admin-field">
        <label for="sc-preview">Preview with</label>
        <select id="sc-preview" name="preview_target">
          <option value="404">The 404 page</option>
          <?php foreach ($previewTargets as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $config['home_content_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title'] . ' (' . $p['path'] . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="cms-form-actions">
      <button type="submit" name="action" value="save" class="admin-btn">Save configuration</button>
      <button type="submit" name="action" value="preview" class="admin-btn admin-btn-secondary" formtarget="_blank">Preview unsaved changes</button>
      <span class="admin-field-hint">Saving clears the page cache so every CMS page picks up the change.</span>
    </div>
  </div>
</form>

<script>
document.querySelectorAll('[data-seed]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var part = btn.getAttribute('data-seed');
    var field = document.getElementById(part === 'footer' ? 'sc-footer_html' : 'sc-header_html');
    if (!field || !window.confirm('Replace the ' + part + ' field with the current ' + part + ' markup? Nothing is saved until you click Save.')) return;
    fetch('/admin/cms-site-config?seed=' + part, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.html) throw new Error(d.error || 'Could not load the markup.');
        field.value = d.html;
        field.focus();
      })
      .catch(function (err) { window.alert(err.message); });
  });
});
document.querySelectorAll('.cms-switch input[type=checkbox]').forEach(function (box) {
  box.addEventListener('change', function () {
    var label = box.parentElement.querySelector('.cms-switch-label');
    if (label) label.textContent = box.checked ? 'On' : 'Off';
  });
});
</script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
