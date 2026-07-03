<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$clientRepo = new ClientRepository();

$search = trim((string) ($_GET['q'] ?? ''));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();

// Backfill from existing submissions when the list has never been synced.
if ($search === '' && $page === 1 && $clientRepo->count(null) === 0) {
    $submissionCount = (int) Database::instance()->pdo()->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    if ($submissionCount > 0) {
        $syncResult = $clientRepo->syncFromSubmissions();
        if (($syncResult['clients_created'] ?? 0) > 0 || ($syncResult['submissions_linked'] ?? 0) > 0) {
            $_SESSION['flash_success'] = 'Imported '
                . (int) $syncResult['clients_created'] . ' client'
                . ($syncResult['clients_created'] === 1 ? '' : 's')
                . ' from ' . (int) $syncResult['submissions_linked'] . ' existing submission'
                . ($syncResult['submissions_linked'] === 1 ? '' : 's') . '.';
        }
    }
}

$total = $clientRepo->count($search !== '' ? $search : null);
$pagination = pagination_meta($total, $page, $perPage);
$clients = $clientRepo->allWithStats($search !== '' ? $search : null, $pagination['per_page'], $pagination['offset']);

$paginationPath = '/admin/clients';
$paginationQuery = $search !== '' ? ['q' => $search] : [];
$paginationLabel = 'clients';
$paginationAriaLabel = 'Client list pages';
$paginationUrl = fn (int $p) => clients_page_url($search, $p, $pagination['per_page']);

$pageTitle = 'Clients';
$activeNav = 'clients';

function clients_page_url(string $search, int $page = 1, ?int $perPage = null): string
{
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    return pagination_url('/admin/clients', $params, $page, $perPage);
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Clients</h1>
  <div class="admin-header-actions">
    <form method="post" action="/admin/clients-sync" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
      <button type="submit" class="admin-btn admin-btn-secondary">Sync from submissions</button>
    </form>
    <a href="/admin/clients-import" class="admin-btn admin-btn-secondary">Import CSV</a>
    <a href="/admin/clients-export" class="admin-btn admin-btn-secondary">Export CSV</a>
  </div>
</div>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/clients" class="clients-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="clients-search">Search</label>
      <input type="search" id="clients-search" name="q" value="<?= e($search) ?>" placeholder="Name, CIN, email, phone, or company…">
    </div>
    <div class="clients-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="/admin/clients" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <?php if (!$clients): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      </span>
      <?php if ($search !== ''): ?>
        <h2 class="admin-empty-state-title">No clients match “<?= e($search) ?>”</h2>
        <p class="admin-empty-state-text">Try a different name, CIN, email, phone, or company.</p>
        <a href="/admin/clients" class="admin-btn admin-btn-secondary">Clear search</a>
      <?php else: ?>
        <h2 class="admin-empty-state-title">No clients yet</h2>
        <p class="admin-empty-state-text">Import a spreadsheet of clients or use “Sync from submissions” to build the directory from existing form responses.</p>
        <a href="/admin/clients-import" class="admin-btn">Import from spreadsheet</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <table class="admin-table clients-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>CIN</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Company</th>
          <th>Submissions</th>
          <th>Last activity</th>
          <th>Source</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $client): ?>
          <tr>
            <td>
              <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="clients-name-link">
                <strong><?= e($client['name']) ?></strong>
              </a>
            </td>
            <td class="clients-col-cin">
              <?= ($client['cin'] ?? '') !== '' ? e($client['cin']) : '—' ?>
            </td>
            <td><?= ($client['email'] ?? '') !== '' ? e($client['email']) : '—' ?></td>
            <td><?= ($client['phone'] ?? '') !== '' ? e($client['phone']) : '—' ?></td>
            <td><?= ($client['company'] ?? '') !== '' ? e($client['company']) : '—' ?></td>
            <td class="clients-col-num">
              <?php $subCount = (int) ($client['submission_count'] ?? 0); ?>
              <?php if ($subCount > 0): ?>
                <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="clients-submission-link"><?= $subCount ?></a>
              <?php else: ?>
                <?= $subCount ?>
              <?php endif; ?>
            </td>
            <td class="clients-col-date">
              <?= !empty($client['last_submission_at']) ? e(substr((string) $client['last_submission_at'], 0, 10)) : '—' ?>
            </td>
            <td>
              <span class="clients-source-badge clients-source-badge--<?= e($client['source'] ?? 'submission') ?>">
                <?= ($client['source'] ?? '') === 'import' ? 'Imported' : 'Form' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
