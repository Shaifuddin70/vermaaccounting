<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('clients.view');

$clientRepo = new ClientRepository();
$partnerId = partner_user_id();

$search = trim((string) ($_GET['q'] ?? ''));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();

// Backfill from existing submissions when the list has never been synced.
if ($partnerId === null && $search === '' && $page === 1 && $clientRepo->count(null) === 0) {
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

$total = $clientRepo->count($search !== '' ? $search : null, $partnerId);
$totalAll = $search !== '' ? $clientRepo->count(null, $partnerId) : $total;
$pagination = pagination_meta($total, $page, $perPage);
$clients = $clientRepo->allWithStats($search !== '' ? $search : null, $pagination['per_page'], $pagination['offset'], $partnerId);

$paginationPath = '/admin/clients';
$paginationQuery = $search !== '' ? ['q' => $search] : [];
$paginationLabel = 'clients';
$paginationAriaLabel = 'Client list pages';
$paginationUrl = fn (int $p) => clients_page_url($search, $p, $pagination['per_page']);
$csrf = Auth::csrfToken();
$deleteAllConfirm = 'This will permanently delete ALL '
    . number_format($totalAll)
    . ' client'
    . ($totalAll === 1 ? '' : 's')
    . '. Form submissions stay in the system. Linked invoices keep bill-to details but lose the client link. Type DELETE ALL in the next prompt to confirm.';

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

function client_source_label(string $source): string
{
    return match ($source) {
        'import' => 'Imported',
        'manual' => 'Manual',
        default => 'Form',
    };
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Clients</h1>
  <div class="admin-header-actions">
    <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
    <a href="/admin/client-edit" class="admin-btn">+ Add client</a>
    <form method="post" action="/admin/clients-sync" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" class="admin-btn admin-btn-secondary">Sync from submissions</button>
    </form>
    <a href="/admin/clients-import" class="admin-btn admin-btn-secondary">Import CSV</a>
    <a href="/admin/clients-export" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php if ($totalAll > 0): ?>
      <form method="post" action="/admin/client-action" class="inline-form" id="clients-delete-all-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="delete_all">
        <input type="hidden" name="confirm_text" id="clients-delete-all-confirm" value="">
        <button type="submit" class="admin-btn admin-btn-danger"
          onclick="return confirmDeleteAllClients(<?= e(json_encode($deleteAllConfirm)) ?>);">
          Delete all
        </button>
      </form>
    <?php endif; ?>
    <?php elseif (Auth::can('clients.manage') && $partnerId !== null): ?>
    <a href="/admin/clients-export" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/clients" class="clients-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="clients-search">Search</label>
      <input type="search" id="clients-search" name="q" value="<?= e($search) ?>" placeholder="Client ID, name, SIN, email, phone, or company…">
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
        <p class="admin-empty-state-text">Try a different name, SIN, email, phone, or company.</p>
        <a href="/admin/clients" class="admin-btn admin-btn-secondary">Clear search</a>
      <?php else: ?>
        <h2 class="admin-empty-state-title">No clients yet</h2>
        <p class="admin-empty-state-text"><?= $partnerId !== null
          ? 'Clients appear here after form submissions that include your partner reference.'
          : 'Add a client manually, import a spreadsheet, or sync from existing form submissions.' ?></p>
        <div class="admin-header-actions">
          <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
          <a href="/admin/client-edit" class="admin-btn">Add client</a>
          <a href="/admin/clients-import" class="admin-btn admin-btn-secondary">Import from spreadsheet</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="admin-table-toolbar clients-table-toolbar">
      <?php $paginationShow = 'per_page_bare'; require __DIR__ . '/includes/pagination.php'; ?>
      <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
      <div class="clients-bulk-bar" id="clients-bulk-bar" hidden>
        <label class="clients-bulk-select-all">
          <input type="checkbox" id="clients-select-all" aria-label="Select all clients on this page">
          <span>Select all on page</span>
        </label>
        <span class="clients-bulk-count" id="clients-bulk-count" aria-live="polite">0 selected</span>
        <button type="submit" form="clients-bulk-form"
          class="admin-btn admin-btn-danger admin-btn-sm" id="clients-bulk-delete" disabled>
          Delete selected
        </button>
      </div>
      <?php endif; ?>
    </div>

    <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
    <form method="post" action="/admin/client-action" id="clients-bulk-form"
      onsubmit="return confirmClientsBulkDelete(this);">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="delete_bulk">
    <?php endif; ?>

      <div class="admin-table-scroll">
      <table class="admin-table clients-table">
        <thead>
          <tr>
            <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
            <th class="clients-col-check">
              <input type="checkbox" id="clients-select-all-head" aria-label="Select all clients on this page">
            </th>
            <?php endif; ?>
            <th>Client ID</th>
            <th>Name</th>
            <th>SIN</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Company</th>
            <th>Submissions</th>
            <th>Last activity</th>
            <th>Source</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $client):
            $source = (string) ($client['source'] ?? 'submission');
            $deleteConfirm = 'Delete “' . $client['name'] . '”? Linked form submissions stay in the system, but this client record will be removed. This cannot be undone.';
          ?>
            <tr>
              <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
              <td class="clients-col-check">
                <input type="checkbox" class="clients-row-check" name="ids[]"
                  value="<?= (int) $client['id'] ?>"
                  aria-label="Select <?= e($client['name']) ?>">
              </td>
              <?php endif; ?>
              <td class="clients-col-id"><?= (int) $client['id'] ?></td>
              <td>
                <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="clients-name-link">
                  <strong><?= e($client['name']) ?></strong>
                </a>
              </td>
              <td class="clients-col-sin">
                <?= ($client['sin'] ?? '') !== '' ? e($client['sin']) : '—' ?>
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
                <span class="clients-source-badge clients-source-badge--<?= e($source) ?>">
                  <?= e(client_source_label($source)) ?>
                </span>
              </td>
              <td>
                <div class="admin-table-actions">
                  <?php if (Auth::can('clients.manage')): ?>
                  <a href="/admin/client-edit?id=<?= (int) $client['id'] ?>" class="admin-btn admin-btn-sm">Edit</a>
                  <button type="submit" form="clients-single-delete-<?= (int) $client['id'] ?>"
                    class="admin-btn admin-btn-sm admin-btn-danger"
                    onclick="return confirm(<?= e(json_encode($deleteConfirm)) ?>);">Delete</button>
                  <?php else: ?>
                  <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="admin-btn admin-btn-sm">View</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php if (Auth::can('clients.manage') && $partnerId === null): ?>
    </form>
    <?php endif; ?>

    <?php if (Auth::can('clients.manage')): ?>
    <?php foreach ($clients as $client): ?>
      <form method="post" action="/admin/client-action" id="clients-single-delete-<?= (int) $client['id'] ?>" hidden>
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
        <input type="hidden" name="action" value="delete">
      </form>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<script>
window.confirmDeleteAllClients = function (message) {
  if (!confirm(message)) return false;
  var typed = window.prompt('Type DELETE ALL to permanently remove every client:');
  if (typed === null) return false;
  if (String(typed).trim().toUpperCase() !== 'DELETE ALL') {
    alert('Deletion cancelled. You must type DELETE ALL exactly.');
    return false;
  }
  var confirmInput = document.getElementById('clients-delete-all-confirm');
  if (confirmInput) confirmInput.value = 'DELETE ALL';
  return true;
};

(function () {
  var form = document.getElementById('clients-bulk-form');
  if (!form) return;

  var bar = document.getElementById('clients-bulk-bar');
  var countEl = document.getElementById('clients-bulk-count');
  var deleteBtn = document.getElementById('clients-bulk-delete');
  var selectAll = document.getElementById('clients-select-all');
  var selectAllHead = document.getElementById('clients-select-all-head');
  var checks = Array.prototype.slice.call(form.querySelectorAll('.clients-row-check'));

  function selectedCount() {
    return checks.filter(function (cb) { return cb.checked; }).length;
  }

  function sync() {
    var count = selectedCount();
    if (bar) bar.hidden = false;
    if (countEl) countEl.textContent = count + ' selected';
    if (deleteBtn) deleteBtn.disabled = count < 1;
    var allChecked = checks.length > 0 && count === checks.length;
    if (selectAll) selectAll.checked = allChecked;
    if (selectAllHead) selectAllHead.checked = allChecked;
  }

  function setAll(checked) {
    checks.forEach(function (cb) { cb.checked = checked; });
    sync();
  }

  checks.forEach(function (cb) {
    cb.addEventListener('change', sync);
  });
  if (selectAll) selectAll.addEventListener('change', function () { setAll(selectAll.checked); });
  if (selectAllHead) selectAllHead.addEventListener('change', function () { setAll(selectAllHead.checked); });

  window.confirmClientsBulkDelete = function () {
    var count = selectedCount();
    if (count < 1) {
      alert('Select at least one client to delete.');
      return false;
    }
    return confirm(
      'Delete ' + count + ' selected client' + (count === 1 ? '' : 's') +
      '? Linked form submissions stay in the system, but these client records will be removed. This cannot be undone.'
    );
  };

  sync();
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
