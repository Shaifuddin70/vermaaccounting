<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$clientRepo = new ClientRepository();

$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

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
$clients = $clientRepo->allWithStats($search !== '' ? $search : null, $perPage, $offset);
$totalPages = max(1, (int) ceil($total / $perPage));

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pageTitle = 'Clients';
$activeNav = 'clients';

function clients_page_url(string $search, int $page = 1): string
{
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return '/admin/clients.php' . ($params ? '?' . http_build_query($params) : '');
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Clients</h1>
  <div class="admin-header-actions">
    <form method="post" action="/admin/clients-sync.php" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
      <button type="submit" class="admin-btn admin-btn-secondary">Sync from submissions</button>
    </form>
    <a href="/admin/clients-import.php" class="admin-btn admin-btn-secondary">Import CSV</a>
    <a href="/admin/clients-export.php" class="admin-btn admin-btn-secondary">Export CSV</a>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="admin-alert admin-alert-success"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="admin-alert admin-alert-error"><?= e($flashError) ?></div>
<?php endif; ?>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/clients.php" class="clients-filter-form">
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="clients-search">Search</label>
      <input type="search" id="clients-search" name="q" value="<?= e($search) ?>" placeholder="Name, CIN, email, phone, or company…">
    </div>
    <div class="clients-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="/admin/clients.php" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <?php if (!$clients): ?>
    <div class="clients-empty">
      <p>No clients yet.</p>
      <p>
        <a href="/admin/clients-import.php" class="admin-btn admin-btn-secondary">Import from spreadsheet</a>
        or sync from existing form submissions.
      </p>
    </div>
  <?php else: ?>
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
              <a href="/admin/client.php?id=<?= (int) $client['id'] ?>" class="clients-name-link">
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
                <a href="/admin/client.php?id=<?= (int) $client['id'] ?>" class="clients-submission-link"><?= $subCount ?></a>
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

<?php if ($totalPages > 1): ?>
  <nav class="clients-pagination" aria-label="Client list pages">
    <?php if ($page > 1): ?>
      <a href="<?= e(clients_page_url($search, $page - 1)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">← Previous</a>
    <?php endif; ?>
    <span class="clients-pagination-info">Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($total) ?> clients)</span>
    <?php if ($page < $totalPages): ?>
      <a href="<?= e(clients_page_url($search, $page + 1)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Next →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
