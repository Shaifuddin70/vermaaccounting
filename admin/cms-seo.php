<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/includes/cms-common.php';
Auth::requireLogin();
Auth::requireCapability('cms.view');

$includeDrafts = !empty($_GET['drafts']);
$severity = (string) ($_GET['severity'] ?? '');
$checkFilter = (string) ($_GET['check'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));

$all = CmsAudit::run(['include_drafts' => $includeDrafts]);
$issues = array_values(array_filter($all, static function (array $i) use ($severity, $checkFilter, $q): bool {
    return ($severity === '' || $i['severity'] === $severity)
        && ($checkFilter === '' || $i['check'] === $checkFilter)
        && ($q === '' || stripos($i['path'] . ' ' . $i['title'] . ' ' . $i['message'], $q) !== false);
}));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-audit-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['severity', 'check', 'path', 'title', 'message', 'edit_url']);
    foreach ($issues as $i) {
        fputcsv($out, [$i['severity'], CmsAudit::CHECKS[$i['check']] ?? $i['check'], $i['path'], $i['title'], $i['message'], $i['content_id'] ? '/admin/cms-edit?id=' . $i['content_id'] : '']);
    }
    exit;
}

$summary = CmsAudit::summary($all);
$byCheck = [];
foreach ($all as $i) {
    $byCheck[$i['check']][$i['severity']] = ($byCheck[$i['check']][$i['severity']] ?? 0) + 1;
}
uksort($byCheck, static fn ($a, $b) => array_search($a, array_keys(CmsAudit::CHECKS), true) <=> array_search($b, array_keys(CmsAudit::CHECKS), true));
$graph = CmsAudit::linkGraph();
$weak = array_values(array_filter($graph, static fn ($p) => !$p['menu'] && $p['inbound'] < 2));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = count($issues);
$pagination = pagination_meta($total, $page, $perPage);
$shown = array_slice($issues, ($pagination['page'] - 1) * $perPage, $perPage);
$query = array_filter(['severity' => $severity, 'check' => $checkFilter, 'q' => $q, 'drafts' => $includeDrafts ? '1' : '']);
$paginationPath = '/admin/cms-seo';
$paginationQuery = $query;
$paginationLabel = 'issues';
$paginationUrl = fn (int $p) => pagination_url('/admin/cms-seo', $query, $p, $perPage);
$labels = ['error' => 'Errors', 'warning' => 'Warnings', 'info' => 'Suggestions'];
$badge = ['error' => 'cms-badge-error', 'warning' => 'cms-badge-warning', 'info' => 'admin-badge-muted'];

$pageTitle = 'SEO audit';
$activeNav = 'cms-seo';
$loadCmsAdmin = true;
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>SEO audit</h1>
  <div class="admin-header-actions">
    <a class="admin-btn admin-btn-secondary" href="/admin/cms-seo?<?= e(http_build_query($query + ['export' => 'csv'])) ?>">Export CSV</a>
  </div>
</div>

<p class="admin-table-sub">Checks <?= $includeDrafts ? 'all pages including drafts' : 'published pages' ?>, redirects, the sitemap and the media library against technical SEO rules. Passing every check does not guarantee rankings; it removes problems that can hold pages back.</p>

<dl class="cms-stat-list">
  <?php foreach ($labels as $sev => $label): ?>
    <div><dt><?= e($label) ?></dt><dd><a href="/admin/cms-seo?<?= e(http_build_query(array_filter(['severity' => $sev, 'drafts' => $includeDrafts ? '1' : '']))) ?>"><?= $summary[$sev] ?></a></dd></div>
  <?php endforeach; ?>
  <div><dt>Live pages</dt><dd><?= count($graph) ?></dd></div>
</dl>

<div class="admin-fields-2col cms-mt">
  <div class="admin-card">
    <h2 class="admin-card-title">By check</h2>
    <?php if (!$byCheck): ?>
      <p>No issues found.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Check</th><th>Errors</th><th>Warnings</th><th>Suggestions</th></tr></thead>
        <tbody>
        <?php foreach ($byCheck as $check => $counts): ?>
          <tr>
            <td><a href="/admin/cms-seo?<?= e(http_build_query(array_filter(['check' => $check, 'drafts' => $includeDrafts ? '1' : '']))) ?>"><?= e(CmsAudit::CHECKS[$check] ?? $check) ?></a></td>
            <td><?= $counts['error'] ?? '' ?></td><td><?= $counts['warning'] ?? '' ?></td><td><?= $counts['info'] ?? '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="admin-card">
    <h2 class="admin-card-title">Internal links</h2>
    <p>Pages with few links from other pages are harder for visitors and search engines to find. Pages in the site menu or footer, and articles listed on the blog index, are counted as linked.</p>
    <?php if (!$graph): ?>
      <p>No CMS pages are live yet.</p>
    <?php elseif (!$weak): ?>
      <p>Every live page has at least two internal links pointing to it.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Page</th><th>Links in</th><th>Links out</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($weak, 0, 25) as $p): ?>
          <tr>
            <td><a href="/admin/cms-edit?id=<?= $p['id'] ?>"><code><?= e($p['path']) ?></code></a><div class="admin-table-sub"><?= e($p['title']) ?></div></td>
            <td><?= $p['inbound'] === 0 ? '<span class="admin-badge admin-badge-info">0</span>' : $p['inbound'] ?></td>
            <td><?= $p['outbound'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($weak) > 25): ?><p class="admin-table-sub"><?= count($weak) - 25 ?> more.</p><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <form method="get" action="/admin/cms-seo" class="cms-filters">
    <div class="admin-field"><label for="a-q">Search</label><input type="search" id="a-q" name="q" value="<?= e($q) ?>" placeholder="Path, title or message"></div>
    <div class="admin-field">
      <label for="a-sev">Severity</label>
      <select id="a-sev" name="severity">
        <option value="">Any</option>
        <?php foreach ($labels as $sev => $label): ?><option value="<?= $sev ?>" <?= $severity === $sev ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field">
      <label for="a-check">Check</label>
      <select id="a-check" name="check">
        <option value="">Any</option>
        <?php foreach (CmsAudit::CHECKS as $k => $l): ?><option value="<?= $k ?>" <?= $checkFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <label class="admin-checkbox-label"><input type="checkbox" name="drafts" value="1" <?= $includeDrafts ? 'checked' : '' ?>> <span>Include drafts</span></label>
    <div class="cms-filters-actions"><button type="submit" class="admin-btn admin-btn-secondary">Filter</button><?php if ($query): ?> <a href="/admin/cms-seo">Reset</a><?php endif; ?></div>
  </form>
  <?php if (!$issues): ?>
    <div class="admin-empty-state"><p class="admin-empty-state-text"><?= $all ? 'No issues match these filters.' : 'No issues found.' ?></p></div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="cms-table-scroll">
      <table class="admin-table">
        <thead><tr><th>Severity</th><th>Check</th><th>Where</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($shown as $i): ?>
          <tr>
            <td><span class="admin-badge <?= $badge[$i['severity']] ?>"><?= e(ucfirst($i['severity'] === 'info' ? 'suggestion' : $i['severity'])) ?></span></td>
            <td><?= e(CmsAudit::CHECKS[$i['check']] ?? $i['check']) ?></td>
            <td>
              <?php if ($i['content_id']): ?>
                <a href="/admin/cms-edit?id=<?= (int) $i['content_id'] ?>"><code><?= e($i['path']) ?></code></a><div class="admin-table-sub"><?= e($i['title']) ?></div>
              <?php elseif (str_starts_with($i['check'], 'redirect_')): ?>
                <a href="/admin/cms-redirects?q=<?= e(rawurlencode($i['path'])) ?>"><code><?= e($i['path']) ?></code></a>
              <?php elseif (str_starts_with($i['path'], '/media/')): ?>
                <a href="/admin/cms-media?q=<?= e(rawurlencode(basename($i['path']))) ?>"><code><?= e($i['path']) ?></code></a>
              <?php else: ?>
                <code><?= e($i['path']) ?></code>
              <?php endif; ?>
            </td>
            <td><?= e($i['message']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
