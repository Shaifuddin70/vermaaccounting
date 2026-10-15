<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.redirects');

$migration = new CmsMigration();
$canPublish = Auth::can('cms.publish');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cms_admin_require_post();
    $action = (string) ($_POST['action'] ?? '');
    $back = '/admin/cms-migration';

    if ($action === 'seed') {
        $n = $migration->inventoryFromSite();
        cms_admin_back($back, $n . ' URL(s) from the hand-written pages and the old sitemap are in the inventory.');
    }

    if ($action === 'add_url') {
        $id = $migration->upsertItem(trim((string) ($_POST['url'] ?? '')), 'manual');
        cms_admin_back($back, $id ? 'URL added.' : null, $id ? null : 'Enter a path or URL on this website.');
    }

    if ($action === 'import_csv') {
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            cms_admin_back($back, null, 'Choose a CSV file to import.');
        }
        if ((int) $file['size'] > 2 * 1024 * 1024) {
            cms_admin_back($back, null, 'The CSV file is larger than 2 MB.');
        }
        $result = $migration->importInventoryCsv((string) $file['tmp_name']);
        cms_admin_back($back, $result['added'] . ' URL(s) added or updated.', $result['errors'] ? implode(' ', array_slice($result['errors'], 0, 10)) : null);
    }

    if ($action === 'crawl') {
        $n = $migration->crawlBatch();
        cms_admin_back($back, $n ? 'Crawled ' . $n . ' URL(s).' : 'Every URL has been crawled.');
    }

    if ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $op = (string) ($_POST['bulk_action'] ?? '');
        if (!$ids) {
            cms_admin_back($back, null, 'Select at least one URL.');
        }
        if ($op === 'crawl') {
            $n = $migration->crawlBatch(array_slice($ids, 0, 25));
            cms_admin_back($back, 'Crawled ' . $n . ' URL(s).' . (count($ids) > 25 ? ' Only the first 25 are crawled at a time.' : ''));
        }
        if ($op === 'import') {
            $ok = 0;
            $failed = [];
            foreach ($ids as $id) {
                $item = $migration->find($id);
                if (!$item || $item['action'] === 'imported') {
                    continue;
                }
                $r = $migration->import($item, !empty($_POST['import_images']));
                $r['ok'] ? $ok++ : $failed[] = $item['source_path'] . ': ' . implode(' ', $r['problems']);
            }
            CmsCache::flush();
            cms_admin_back($back, $ok . ' draft(s) created. Nothing has been published.', $failed ? 'Not imported — ' . implode(' | ', array_slice($failed, 0, 8)) : null);
        }
        if ($op === 'delete') {
            foreach ($ids as $id) {
                $migration->delete($id);
            }
            cms_admin_back($back, count($ids) . ' URL(s) removed from the inventory.');
        }
        if (isset(CmsMigration::ACTIONS[$op]) && $op !== 'imported') {
            foreach ($ids as $id) {
                $item = $migration->find($id);
                if ($item && $item['action'] !== 'imported') {
                    $migration->update($id, ['action' => $op]);
                }
            }
            cms_admin_back($back, 'Updated ' . count($ids) . ' URL(s).');
        }
        cms_admin_back($back, null, 'Choose what to do with the selected URLs.');
    }

    $item = $migration->find((int) ($_POST['id'] ?? 0));
    if (in_array($action, ['update', 'crawl_item', 'import_item', 'delete_item'], true) && !$item) {
        cms_admin_back($back, null, 'That URL is no longer in the inventory.');
    }
    $itemUrl = $item ? '/admin/cms-migration?item=' . $item['id'] : $back;

    if ($action === 'update') {
        $target = CmsPath::normalize((string) ($_POST['target_path'] ?? ''));
        $errors = $target === '/' ? [] : CmsPath::validate($target);
        $type = (string) ($_POST['content_type'] ?? 'page');
        $folderId = (int) ($_POST['folder_id'] ?? 0);
        if ($folderId && !(new CmsFolderRepository())->find($folderId)) {
            $errors[] = 'Choose a valid folder.';
        }
        if ($errors) {
            cms_admin_back($itemUrl, null, 'New URL: ' . implode(' ', $errors));
        }
        $fields = [
            'target_path' => $target,
            'content_type' => isset(CmsContentRepository::TYPES[$type]) ? $type : 'page',
            'folder_id' => $folderId ?: null,
            'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000),
        ];
        $act = (string) ($_POST['item_action'] ?? '');
        if ($item['action'] !== 'imported' && isset(CmsMigration::ACTIONS[$act]) && $act !== 'imported') {
            $fields['action'] = $act;
        }
        $migration->update((int) $item['id'], $fields);
        cms_admin_back($itemUrl, 'Saved.');
    }

    if ($action === 'crawl_item') {
        $migration->crawlBatch([(int) $item['id']]);
        cms_admin_back($itemUrl, 'Crawled again.');
    }

    if ($action === 'import_item') {
        $r = $migration->import($item, !empty($_POST['import_images']));
        if (!$r['ok']) {
            cms_admin_back($itemUrl, null, implode(' ', $r['problems']));
        }
        CmsCache::flush();
        cms_admin_back('/admin/cms-edit?id=' . $r['content_id'], 'Imported as a draft. Review it, then publish when ready — the old URL keeps working until then.');
    }

    if ($action === 'delete_item') {
        $migration->delete((int) $item['id']);
        cms_admin_back($back, 'Removed from the inventory.');
    }

    if ($action === 'redirects') {
        $r = $migration->createRedirects();
        cms_admin_back('/admin/cms-migration?view=report', $r['created'] . ' redirect(s) created' . ($r['waiting'] ? '; ' . $r['waiting'] . ' wait for their new page to be published' : '') . '.', $r['problems'] ? implode(' ', array_slice($r['problems'], 0, 10)) : null);
    }

    if ($action === 'rollback') {
        if (!$canPublish) {
            cms_admin_back($back, null, 'Rolling back needs publish permission.');
        }
        $r = $migration->rollback();
        cms_admin_back('/admin/cms-migration?view=report', 'Rolled back: ' . $r['unpublished'] . ' page(s) unpublished, ' . $r['redirects_removed'] . ' migration redirect(s) removed. The hand-written pages are live again.');
    }

    cms_admin_back($back, null, 'Unknown action.');
}

$view = (string) ($_GET['view'] ?? 'inventory');
$itemId = (int) ($_GET['item'] ?? 0);
$detail = $itemId ? $migration->find($itemId) : null;
if ($itemId && !$detail) {
    cms_admin_back('/admin/cms-migration', null, 'That URL is no longer in the inventory.');
}
$filter = (string) ($_GET['action'] ?? '');
$counts = $migration->counts();
$csrf = Auth::csrfToken();
$folderNames = [];
foreach ((new CmsFolderRepository())->flatTree() as $f) {
    $folderNames[(int) $f['id']] = (string) $f['name'];
}
$statusLabel = static function (array $item): string {
    if ($item['crawled_at'] === null) {
        return '<span class="admin-table-sub">Not crawled</span>';
    }
    $s = (int) $item['http_status'];
    return '<span class="admin-badge ' . ($s === 200 ? 'admin-badge-success' : 'admin-badge-info') . '">' . ($s ?: 'Error') . '</span>';
};

$pageTitle = 'Migration';
$activeNav = 'cms-migration';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Website migration</h1>
</div>

<nav class="admin-tabs" aria-label="Migration sections">
  <a class="admin-tab <?= !$detail && $view === 'inventory' ? 'is-active' : '' ?>" href="/admin/cms-migration">Inventory (<?= $counts['total'] ?>)</a>
  <a class="admin-tab <?= $view === 'compare' ? 'is-active' : '' ?>" href="/admin/cms-migration?view=compare">Old vs new (<?= $counts['imported'] ?>)</a>
  <a class="admin-tab <?= $view === 'report' ? 'is-active' : '' ?>" href="/admin/cms-migration?view=report">Launch report</a>
</nav>

<?php if ($detail): ?>
  <?php $plan = $migration->plan($detail); ?>
  <p><a href="/admin/cms-migration">← Inventory</a></p>
  <div class="admin-card">
    <h2 class="admin-card-title"><code><?= e((string) $detail['source_path']) ?></code></h2>
    <p class="admin-table-sub">Found via <?= e((string) $detail['origin']) ?> · <?= $statusLabel($detail) ?> <?= $detail['crawled_at'] ? 'crawled ' . e(cms_admin_date((string) $detail['crawled_at'])) : '' ?></p>
    <?php if ($detail['action'] === 'imported' && $detail['content_id']): ?>
      <div class="admin-alert admin-alert-success">Imported as <a href="/admin/cms-edit?id=<?= (int) $detail['content_id'] ?>">CMS content #<?= (int) $detail['content_id'] ?></a>.</div>
    <?php else: ?>
      <h3>Import check</h3>
      <?php if ($plan['problems']): ?>
        <ul class="cms-warning-list"><?php foreach ($plan['problems'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <p>Ready to import as a draft at <code><?= e($plan['path']) ?></code>.</p>
      <?php endif; ?>
      <?php if ($plan['notes']): ?><ul class="admin-table-sub"><?php foreach ($plan['notes'] as $n): ?><li><?= e($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php endif; ?>

    <form method="post" action="/admin/cms-migration">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
      <div class="admin-fields-2col">
        <div class="admin-field">
          <label for="m-action">Plan</label>
          <select id="m-action" name="item_action" <?= $detail['action'] === 'imported' ? 'disabled' : '' ?>>
            <?php foreach (CmsMigration::ACTIONS as $k => $l): if ($k === 'imported' && $detail['action'] !== 'imported') { continue; } ?>
              <option value="<?= $k ?>" <?= $detail['action'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="m-target">New URL</label>
          <input type="text" id="m-target" name="target_path" value="<?= e((string) $detail['target_path']) ?>">
          <p class="admin-field-hint">Keep the same URL whenever possible. For “Redirect only”, this is where the old URL should go.</p>
        </div>
        <div class="admin-field">
          <label for="m-type">Content type</label>
          <select id="m-type" name="content_type">
            <?php foreach (CmsContentRepository::TYPES as $k => $l): ?><option value="<?= $k ?>" <?= $detail['content_type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="admin-field">
          <label for="m-folder">Folder</label>
          <select id="m-folder" name="folder_id"><?= cms_admin_folder_options($detail['folder_id'] ? (int) $detail['folder_id'] : null) ?></select>
        </div>
        <div class="admin-field admin-field--full">
          <label for="m-notes">Notes</label>
          <textarea id="m-notes" name="notes" rows="2"><?= e((string) $detail['notes']) ?></textarea>
        </div>
      </div>
      <div class="cms-form-actions">
        <button type="submit" class="admin-btn admin-btn-secondary">Save</button>
      </div>
    </form>

    <div class="cms-form-actions">
      <form method="post" action="/admin/cms-migration" class="cms-inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
        <button type="submit" name="action" value="crawl_item" class="admin-btn admin-btn-secondary" data-busy="Crawling…"><?= $detail['crawled_at'] ? 'Crawl again' : 'Crawl now' ?></button>
      </form>
      <?php if ($detail['action'] !== 'imported' && $plan['ok']): ?>
        <form method="post" action="/admin/cms-migration" class="cms-inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
          <label class="admin-checkbox-label"><input type="checkbox" name="import_images" value="1" checked> <span>Copy images into the media library</span></label>
          <button type="submit" name="action" value="import_item" class="admin-btn" data-busy="Importing…">Import as draft</button>
        </form>
      <?php endif; ?>
      <form method="post" action="/admin/cms-migration" class="cms-inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
        <button type="submit" name="action" value="delete_item" class="admin-btn admin-btn-danger" data-confirm="Remove this URL from the inventory? Any imported page is kept.">Remove</button>
      </form>
    </div>
  </div>

  <?php if ($detail['crawled_at']): ?>
    <div class="admin-card">
      <h2 class="admin-card-title">What was found</h2>
      <table class="admin-table cms-compare-table">
        <tbody>
          <tr><th>Title</th><td><?= e((string) $detail['title']) ?: '<em>missing</em>' ?></td></tr>
          <tr><th>Description</th><td><?= e((string) $detail['description']) ?: '<em>missing</em>' ?></td></tr>
          <tr><th>H1</th><td><?= e((string) $detail['h1']) ?: '<em>missing</em>' ?></td></tr>
          <tr><th>Canonical</th><td><?= e((string) $detail['canonical']) ?: '—' ?></td></tr>
          <tr><th>Robots</th><td><?= e((string) $detail['robots']) ?: '—' ?></td></tr>
          <tr><th>Words</th><td><?= (int) $detail['word_count'] ?></td></tr>
          <tr><th>Links</th><td><?= count($detail['links']) ?> (<?= count(array_filter($detail['links'], fn ($l) => !empty($l['internal']))) ?> internal)</td></tr>
          <tr><th>Images</th><td><?= count($detail['images']) ?><?php $noAlt = count(array_filter($detail['images'], fn ($i) => $i['alt'] === null || trim((string) $i['alt']) === '')); if ($noAlt): ?> — <?= $noAlt ?> without alt text<?php endif; ?></td></tr>
        </tbody>
      </table>
      <?php if (trim((string) $detail['body_html']) !== ''): ?>
        <details class="cms-mt"><summary>Extracted main content</summary><pre class="cms-code-preview"><?= e(mb_substr((string) $detail['body_html'], 0, 20000)) ?></pre></details>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php elseif ($view === 'compare'): ?>
  <?php $rows = $migration->comparisons(); ?>
  <div class="admin-card">
    <p>Each imported page is compared with what was crawled from the old page. Check every difference before publishing.</p>
    <?php if (!$rows): ?>
      <div class="admin-empty-state"><p class="admin-empty-state-text">Nothing imported yet.</p></div>
    <?php else: ?>
      <div class="cms-table-scroll">
        <table class="admin-table">
          <thead><tr><th>Old URL</th><th>New page</th><th>Status</th><th>Differences</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><a href="/admin/cms-migration?item=<?= (int) $r['item']['id'] ?>"><code><?= e((string) $r['item']['source_path']) ?></code></a></td>
              <td><?php if ($r['content']): ?><a href="/admin/cms-edit?id=<?= (int) $r['content']['id'] ?>"><code><?= e((string) $r['content']['path']) ?></code></a><?php else: ?><em>deleted</em><?php endif; ?></td>
              <td><?= $r['content'] ? e(CmsContentRepository::STATUSES[$r['content']['status']] ?? (string) $r['content']['status']) : '—' ?></td>
              <td>
                <?php if (!$r['diffs']): ?><span class="admin-badge admin-badge-success">Matches</span><?php endif; ?>
                <?php foreach ($r['diffs'] as $d): ?><span class="admin-badge admin-badge-info"><?= e($d) ?></span> <?php endforeach; ?>
                <?php if ($r['effective'] && $r['diffs']): ?>
                  <details><summary class="admin-table-sub">Details</summary>
                    <table class="admin-table cms-compare-table">
                      <tr><th></th><th>Old</th><th>New</th></tr>
                      <tr><th>Title</th><td><?= e((string) $r['item']['title']) ?></td><td><?= e((string) $r['effective']['title']) ?></td></tr>
                      <tr><th>Description</th><td><?= e((string) $r['item']['description']) ?></td><td><?= e((string) $r['effective']['description']) ?></td></tr>
                      <tr><th>H1</th><td><?= e((string) $r['item']['h1']) ?></td><td><?= e((string) (($r['content']['h1'] ?? '') ?: $r['content']['title'])) ?></td></tr>
                      <tr><th>Canonical</th><td><?= e((string) $r['item']['canonical']) ?></td><td><?= e((string) $r['effective']['canonical']) ?></td></tr>
                      <tr><th>Words</th><td><?= (int) $r['item']['word_count'] ?></td><td><?= CmsHtml::wordCount((string) $r['content']['body_html']) ?></td></tr>
                    </table>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($view === 'report'): ?>
  <?php $live = !empty($_GET['live']); $report = $migration->report($live); $bad = array_filter($report['rows'], fn ($r) => !$r['ok']); ?>
  <div class="admin-card">
    <h2 class="admin-card-title">Cutover</h2>
    <ol class="cms-steps">
      <li>Import pages as drafts and compare them with the old versions.</li>
      <li>Publish one page at a time from its editor. The CMS page replaces the hand-written page at the same URL as soon as it is published.</li>
      <li>For the homepage, publish the imported <code>/home</code> page, then choose <strong>Use as homepage</strong> in its editor.</li>
      <li>When pages with new URLs are published, create their redirects below.</li>
      <li>After launch, run the live check and resubmit the sitemap in Google Search Console.</li>
    </ol>
    <div class="cms-form-actions">
      <form method="post" action="/admin/cms-migration" class="cms-inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" name="action" value="redirects" class="admin-btn">Create redirects for changed URLs</button>
      </form>
      <a class="admin-btn admin-btn-secondary" href="/admin/cms-migration?view=report&amp;live=1" data-busy="Checking…">Check the live site (slow)</a>
      <?php if ($canPublish): ?>
        <form method="post" action="/admin/cms-migration" class="cms-inline-form">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <button type="submit" name="action" value="rollback" class="admin-btn admin-btn-danger" data-confirm="Unpublish every migrated page and remove the migration redirects? The hand-written pages become live again. Blog articles are not affected.">Roll back migration</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-card">
    <h2 class="admin-card-title"><?= $live ? 'Live check' : 'Pre-launch check' ?>: <?= count($report['rows']) - count($bad) ?> of <?= count($report['rows']) ?> URLs OK</h2>
    <?php if (!$report['rows']): ?>
      <div class="admin-empty-state"><p class="admin-empty-state-text">The inventory is empty.</p></div>
    <?php else: ?>
      <div class="cms-table-scroll">
        <table class="admin-table">
          <thead><tr><th>Old URL</th><th>Should end at</th><th>Result</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($bad + array_diff_key($report['rows'], $bad) as $r): ?>
            <tr>
              <td><code><?= e($r['path']) ?></code></td>
              <td><code><?= e($r['expected']) ?></code></td>
              <td><?= e($r['result']) ?></td>
              <td><?= $r['ok'] ? '<span class="admin-badge admin-badge-success">OK</span>' : '<span class="admin-badge admin-badge-info">Check</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="admin-fields-2col">
    <div class="admin-card">
      <h2 class="admin-card-title">Broken internal links (<?= count($report['broken_links']) ?>)</h2>
      <?php if (!$report['broken_links']): ?><p>None found in the crawled pages.</p><?php else: ?>
        <ul class="cms-warning-list"><?php foreach (array_slice($report['broken_links'], 0, 200) as $l): ?><li><code><?= e($l['from']) ?></code> links to <code><?= e($l['to']) ?></code> <a href="/admin/cms-redirects?edit=new&amp;source=<?= e(rawurlencode($l['to'])) ?>">Add redirect</a></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <div class="admin-card">
      <h2 class="admin-card-title">Sitemap differences</h2>
      <p><strong>Only in the old sitemap (<?= count($report['sitemap']['old_only']) ?>)</strong> — make sure each one is published, redirected, or intentionally removed.</p>
      <ul class="cms-warning-list"><?php foreach ($report['sitemap']['old_only'] as $p): ?><li><code><?= e($p) ?></code></li><?php endforeach; ?></ul>
      <p><strong>New in the CMS sitemap (<?= count($report['sitemap']['new_only']) ?>)</strong></p>
      <ul class="admin-table-sub"><?php foreach (array_slice($report['sitemap']['new_only'], 0, 100) as $p): ?><li><code><?= e($p) ?></code></li><?php endforeach; ?></ul>
    </div>
  </div>

<?php else: ?>
  <?php $items = $migration->items($filter, 1000); ?>
  <div class="admin-fields-2col">
    <div class="admin-card">
      <h2 class="admin-card-title">1. Build the URL inventory</h2>
      <form method="post" action="/admin/cms-migration" class="cms-form-actions">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" name="action" value="seed" class="admin-btn admin-btn-secondary">Add hand-written pages and old sitemap</button>
      </form>
      <form method="post" action="/admin/cms-migration" class="cms-filters cms-mt">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_url">
        <div class="admin-field"><label for="m-url">Add one URL</label><input type="text" id="m-url" name="url" placeholder="/old-page" required></div>
        <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Add</button></div>
      </form>
      <form method="post" action="/admin/cms-migration" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="import_csv">
        <div class="admin-field">
          <label for="m-csv">Or import a CSV</label>
          <input type="file" id="m-csv" name="csv" accept=".csv,text/csv" required>
          <p class="admin-field-hint">Header row required: <code>url,target_path,type,action,notes</code>. Use it to add URLs from Search Console or analytics exports.</p>
        </div>
        <button type="submit" class="admin-btn admin-btn-secondary">Import CSV</button>
      </form>
    </div>
    <div class="admin-card">
      <h2 class="admin-card-title">2. Crawl, then 3. import as drafts</h2>
      <p><?= $counts['crawled'] ?> of <?= $counts['total'] ?> URLs crawled. Crawling reads each page's title, description, headings, links, images and main content<?= $counts['total'] > $counts['crawled'] ? ', ' . CmsMigration::CRAWL_BATCH . ' at a time' : '' ?>.</p>
      <form method="post" action="/admin/cms-migration" class="cms-form-actions">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" name="action" value="crawl" class="admin-btn admin-btn-secondary" data-busy="Crawling…" <?= $counts['total'] === $counts['crawled'] ? 'disabled' : '' ?>>Crawl next batch</button>
      </form>
      <p class="admin-table-sub">Imports always create drafts. Live pages keep working until you publish each draft, and you can roll everything back from the launch report.</p>
      <dl class="cms-stat-list">
        <?php foreach (CmsMigration::ACTIONS as $k => $l): ?><div><dt><?= e($l) ?></dt><dd><a href="/admin/cms-migration?action=<?= $k ?>"><?= $counts[$k] ?></a></dd></div><?php endforeach; ?>
      </dl>
    </div>
  </div>

  <div class="admin-card">
    <form method="get" action="/admin/cms-migration" class="cms-filters">
      <div class="admin-field">
        <label for="m-filter">Show</label>
        <select id="m-filter" name="action">
          <option value="">All URLs</option>
          <?php foreach (CmsMigration::ACTIONS as $k => $l): ?><option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Filter</button></div>
    </form>
    <?php if (!$items): ?>
      <div class="admin-empty-state"><p class="admin-empty-state-text"><?= $filter !== '' ? 'No URLs with this plan.' : 'The inventory is empty. Start by adding the hand-written pages and the old sitemap.' ?></p></div>
    <?php else: ?>
      <form method="post" action="/admin/cms-migration" id="m-bulk">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="bulk">
        <div class="cms-filters">
          <div class="admin-field">
            <label for="m-bulk-action">With selected</label>
            <select id="m-bulk-action" name="bulk_action">
              <option value="">Choose…</option>
              <option value="crawl">Crawl</option>
              <option value="import">Import as drafts</option>
              <?php foreach (CmsMigration::ACTIONS as $k => $l): if ($k === 'imported') { continue; } ?><option value="<?= $k ?>">Plan: <?= e($l) ?></option><?php endforeach; ?>
              <option value="delete" data-confirm="Remove the selected URLs from the inventory? Imported pages are kept.">Remove from inventory</option>
            </select>
          </div>
          <label class="admin-checkbox-label"><input type="checkbox" name="import_images" value="1" checked> <span>Copy images when importing</span></label>
          <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary" data-busy="Working…">Apply</button></div>
        </div>
        <div class="cms-table-scroll">
          <table class="admin-table">
            <thead><tr><th><input type="checkbox" data-check-all="ids[]" aria-label="Select all"></th><th>Old URL</th><th>Status</th><th>Title</th><th>Plan</th><th>New URL</th><th>Type / folder</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $it['id'] ?>" aria-label="Select <?= e((string) $it['source_path']) ?>"></td>
                <td><a href="/admin/cms-migration?item=<?= (int) $it['id'] ?>"><code><?= e((string) $it['source_path']) ?></code></a><div class="admin-table-sub"><?= e((string) $it['origin']) ?></div></td>
                <td><?= $statusLabel($it) ?></td>
                <td><?= e(mb_strimwidth((string) $it['title'], 0, 70, '…')) ?><?php if ($it['crawled_at']): ?><div class="admin-table-sub"><?= (int) $it['word_count'] ?> words</div><?php endif; ?></td>
                <td><?= e(CmsMigration::ACTIONS[$it['action']] ?? (string) $it['action']) ?><?php if ($it['content_id']): ?> <a href="/admin/cms-edit?id=<?= (int) $it['content_id'] ?>">#<?= (int) $it['content_id'] ?></a><?php endif; ?></td>
                <td><code><?= e((string) $it['target_path']) ?></code></td>
                <td><?= e(CmsContentRepository::TYPES[$it['content_type']] ?? (string) $it['content_type']) ?><?php if ($it['folder_id'] && isset($folderNames[(int) $it['folder_id']])): ?><div class="admin-table-sub"><?= e($folderNames[(int) $it['folder_id']]) ?></div><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
