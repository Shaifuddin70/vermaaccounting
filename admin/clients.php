<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('clients.view');

$clientRepo = new ClientRepository();
$partnerId = partner_user_id();

$search = trim((string) ($_GET['q'] ?? ''));
$filters = client_list_filters_from_request($_GET);
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$searchArg = $search !== '' ? $search : null;
$hasNarrowing = $search !== '' || array_filter(array_diff_key($filters, ['sort' => ''])) !== [];

// Backfill from existing submissions when the list has never been synced.
if (
    $partnerId === null
    && Auth::can('clients.sync')
    && !$hasNarrowing
    && $page === 1
    && $clientRepo->count(null) === 0
) {
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

$statusCounts = $clientRepo->statusCounts($searchArg, $partnerId, $filters);
$total = $clientRepo->count($searchArg, $partnerId, $filters);
$totalAll = $hasNarrowing ? $clientRepo->count(null, $partnerId) : $statusCounts['all'];
$pagination = pagination_meta($total, $page, $perPage);
$clients = $clientRepo->allWithStats($searchArg, $pagination['per_page'], $pagination['offset'], $partnerId, $filters);

$listQuery = array_filter(array_merge(['q' => $search], $filters), static fn (string $v): bool => $v !== '');
$currentListUrl = pagination_url('/admin/clients', $listQuery, $pagination['page'], $pagination['per_page']);

$paginationPath = '/admin/clients';
$paginationQuery = $listQuery;
$paginationLabel = 'clients';
$paginationAriaLabel = 'Client list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/clients', $listQuery, $p, $pagination['per_page']);
$csrf = Auth::csrfToken();
$canBulk = $partnerId === null && (Auth::can('clients.delete') || Auth::can('clients.edit'));
$deleteAllConfirm = 'This will permanently delete ALL '
    . number_format($totalAll)
    . ' client'
    . ($totalAll === 1 ? '' : 's')
    . '. Form submissions stay in the system. Linked invoices keep bill-to details but lose the client link. Type DELETE ALL in the next prompt to confirm.';

$statusTabs = [
    '' => ['label' => 'All', 'count' => $statusCounts['all']],
    'active' => ['label' => 'Active', 'count' => $statusCounts['active']],
    'inactive' => ['label' => 'Inactive', 'count' => $statusCounts['inactive']],
];
$activeFilterCount = count(array_filter(array_diff_key($filters, ['status' => '', 'sort' => ''])));

$pageTitle = 'Clients';
$activeNav = 'clients';

function clients_status_tab_url(array $listQuery, string $status, int $perPage): string
{
    $query = $listQuery;
    unset($query['status']);
    if ($status !== '') {
        $query['status'] = $status;
    }
    return pagination_url('/admin/clients', $query, 1, $perPage);
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Clients</h1>
  <div class="admin-header-actions">
    <?php if (Auth::can('clients.create')): ?>
    <a href="/admin/client-edit" class="admin-btn">+ Add client</a>
    <?php endif; ?>
    <?php if (Auth::can('clients.sync') && $partnerId === null): ?>
    <form method="post" action="/admin/clients-sync" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <button type="submit" class="admin-btn admin-btn-secondary">Sync from submissions</button>
    </form>
    <?php endif; ?>
    <?php if (Auth::can('clients.import') && $partnerId === null): ?>
    <a href="/admin/clients-import" class="admin-btn admin-btn-secondary">Import CSV</a>
    <?php endif; ?>
    <?php if (Auth::can('clients.export')): ?>
    <a href="/admin/clients-export" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php endif; ?>
    <?php if (Auth::can('clients.delete') && $partnerId === null && $totalAll > 0): ?>
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
  </div>
</div>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/clients" class="clients-filter-form" id="clients-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <?php if ($filters['status'] !== ''): ?>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <?php endif; ?>
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="clients-search">Search</label>
      <input type="search" id="clients-search" name="q" value="<?= e($search) ?>" placeholder="Client ID, name, SIN, email, phone, company, or city…">
    </div>
    <div class="admin-field clients-filter-field">
      <label for="clients-filing">Filing status</label>
      <select id="clients-filing" name="filing" data-autosubmit>
        <option value="">All statuses</option>
        <?php foreach (client_known_statuses() as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $filters['filing'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
        <option value="none" <?= $filters['filing'] === 'none' ? 'selected' : '' ?>>No status</option>
      </select>
    </div>
    <div class="admin-field clients-filter-field">
      <label for="clients-province">Province</label>
      <select id="clients-province" name="province" data-autosubmit>
        <option value="">All provinces</option>
        <?php foreach (client_provinces() as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $filters['province'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field clients-filter-field">
      <label for="clients-contact">Contact info</label>
      <select id="clients-contact" name="contact" data-autosubmit>
        <option value="">Any</option>
        <?php foreach (client_contact_filters() as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $filters['contact'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field clients-filter-field">
      <label for="clients-source">Source</label>
      <select id="clients-source" name="source" data-autosubmit>
        <option value="">All sources</option>
        <?php foreach (['submission', 'import', 'manual'] as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $filters['source'] === $opt ? 'selected' : '' ?>><?= e(client_source_label($opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="admin-field clients-filter-field">
      <label for="clients-sort">Sort by</label>
      <select id="clients-sort" name="sort" data-autosubmit>
        <?php foreach (client_sort_options() as $key => $label): ?>
          <option value="<?= $key === 'name' ? '' : e($key) ?>" <?= ($filters['sort'] === $key || ($filters['sort'] === '' && $key === 'name')) ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="clients-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
      <?php $canReset = $search !== '' || $activeFilterCount > 0 || $filters['sort'] !== ''; ?>
      <a href="<?= e(clients_status_tab_url([], $filters['status'], $pagination['per_page'])) ?>"
        class="admin-btn admin-btn-secondary<?= $canReset ? '' : ' is-placeholder' ?>"
        <?= $canReset ? '' : 'aria-hidden="true" tabindex="-1"' ?>>Reset</a>
    </div>
  </form>
</div>

<div class="admin-card">
  <nav class="clients-status-tabs" aria-label="Client status">
    <?php foreach ($statusTabs as $key => $tab): ?>
      <a href="<?= e(clients_status_tab_url($listQuery, $key, $pagination['per_page'])) ?>"
        class="clients-status-tab<?= $filters['status'] === $key ? ' is-active' : '' ?>"
        <?= $filters['status'] === $key ? 'aria-current="page"' : '' ?>>
        <?php if ($key !== ''): ?><i class="client-active-dot<?= $key === 'active' ? ' client-active-dot--on' : '' ?>" aria-hidden="true"></i><?php endif; ?>
        <?= e($tab['label']) ?>
        <span class="clients-status-tab-count"><?= number_format($tab['count']) ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($activeFilterCount > 0 || $search !== ''): ?>
      <span class="clients-status-tabs-note">
        <?= number_format($total) ?> match<?= $total === 1 ? '' : 'es' ?>
        <?= $activeFilterCount > 0 ? ' · ' . $activeFilterCount . ' filter' . ($activeFilterCount === 1 ? '' : 's') . ' applied' : '' ?>
      </span>
    <?php endif; ?>
  </nav>

  <?php if (!$clients): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      </span>
      <?php if ($hasNarrowing): ?>
        <h2 class="admin-empty-state-title">No clients match these filters</h2>
        <p class="admin-empty-state-text">Try a different search or remove some filters.</p>
        <a href="/admin/clients" class="admin-btn admin-btn-secondary">Reset filters</a>
      <?php else: ?>
        <h2 class="admin-empty-state-title">No clients yet</h2>
        <p class="admin-empty-state-text"><?= $partnerId !== null
          ? (Auth::can('clients.create')
            ? 'Add a client manually, or wait for form submissions that include your partner reference.'
            : 'Clients appear here after form submissions that include your partner reference.')
          : 'Add a client manually, import a spreadsheet, or sync from existing form submissions.' ?></p>
        <div class="admin-header-actions">
          <?php if (Auth::can('clients.create')): ?>
          <a href="/admin/client-edit" class="admin-btn">Add client</a>
          <?php endif; ?>
          <?php if (Auth::can('clients.import') && $partnerId === null): ?>
          <a href="/admin/clients-import" class="admin-btn admin-btn-secondary">Import from spreadsheet</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="admin-table-toolbar clients-table-toolbar">
      <?php $paginationShow = 'per_page_bare'; require __DIR__ . '/includes/pagination.php'; ?>
      <?php if ($canBulk): ?>
      <div class="clients-bulk-bar" id="clients-bulk-bar" hidden>
        <label class="clients-bulk-select-all">
          <input type="checkbox" id="clients-select-all" aria-label="Select all clients on this page">
          <span>Select all on page</span>
        </label>
        <span class="clients-bulk-count" id="clients-bulk-count" aria-live="polite">0 selected</span>
        <?php if (Auth::can('clients.edit')): ?>
        <button type="submit" form="clients-bulk-form" name="action" value="bulk_active"
          class="admin-btn admin-btn-secondary admin-btn-sm" data-bulk-action disabled>Mark active</button>
        <button type="submit" form="clients-bulk-form" name="action" value="bulk_inactive"
          class="admin-btn admin-btn-secondary admin-btn-sm" data-bulk-action disabled>Mark inactive</button>
        <?php endif; ?>
        <?php if (Auth::can('clients.delete')): ?>
        <button type="submit" form="clients-bulk-form" name="action" value="delete_bulk"
          class="admin-btn admin-btn-danger admin-btn-sm" data-bulk-action data-bulk-delete disabled>
          Delete selected
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($canBulk): ?>
    <form method="post" action="/admin/client-action" id="clients-bulk-form"
      onsubmit="return confirmClientsBulk(event);">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="return_to" value="<?= e($currentListUrl) ?>">
    <?php endif; ?>

      <div class="admin-table-scroll">
      <table class="admin-table clients-table">
        <thead>
          <tr>
            <?php if ($canBulk): ?>
            <th class="clients-col-check">
              <input type="checkbox" id="clients-select-all-head" aria-label="Select all clients on this page">
            </th>
            <?php endif; ?>
            <th>Client</th>
            <th>Contact</th>
            <th>Location</th>
            <th>Filing status</th>
            <th class="clients-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clients as $client):
            $source = (string) ($client['source'] ?? 'submission');
            $meta = client_notes_meta($client['notes'] ?? '');
            $locality = client_address_locality($client['address'] ?? '');
            $email = (string) ($client['email'] ?? '');
            $phone = (string) ($client['phone'] ?? '');
            $isActive = (int) ($client['is_active'] ?? 1) === 1;
            $deleteConfirm = 'Delete “' . $client['name'] . '”? Linked form submissions stay in the system, but this client record will be removed. This cannot be undone.';
          ?>
            <tr class="<?= $isActive ? '' : 'clients-row--inactive' ?>">
              <?php if ($canBulk): ?>
              <td class="clients-col-check">
                <input type="checkbox" class="clients-row-check" name="ids[]"
                  value="<?= (int) $client['id'] ?>"
                  aria-label="Select <?= e($client['name']) ?>">
              </td>
              <?php endif; ?>
              <td>
                <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="clients-person">
                  <span class="client-avatar" aria-hidden="true"><?= e(client_initials((string) $client['name'])) ?></span>
                  <span class="clients-person-text">
                    <strong class="clients-person-name"><?= e($client['name']) ?></strong>
                    <span class="clients-person-sub">
                      ID <?= (int) $client['id'] ?>
                      <span class="client-active-badge client-active-badge--<?= $isActive ? 'on' : 'off' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                      <span class="clients-source-badge clients-source-badge--<?= e($source) ?>"><?= e(client_source_label($source)) ?></span>
                    </span>
                  </span>
                </a>
              </td>
              <td class="clients-col-contact">
                <?php if ($email === '' && $phone === ''): ?>
                  <span class="clients-muted">—</span>
                <?php else: ?>
                  <?php if ($email !== ''): ?>
                    <a href="mailto:<?= e($email) ?>" class="clients-contact-line"><?= e($email) ?></a>
                  <?php endif; ?>
                  <?php if ($phone !== ''): ?>
                    <span class="clients-contact-line clients-muted"><?= e($phone) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="clients-col-location">
                <?= $locality !== '' ? e($locality) : '<span class="clients-muted">—</span>' ?>
              </td>
              <td>
                <?php if ($meta['status'] !== ''): ?>
                  <span class="client-status-badge client-status-badge--<?= e(client_status_tone($meta['status'])) ?>"><?= e($meta['status']) ?></span>
                <?php else: ?>
                  <span class="clients-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="clients-col-actions">
                <div class="admin-table-actions">
                  <a href="/admin/client?id=<?= (int) $client['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">View</a>
                  <?php if (Auth::can('clients.edit')): ?>
                  <a href="/admin/client-edit?id=<?= (int) $client['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
                  <?php endif; ?>
                  <?php if (Auth::can('clients.delete')): ?>
                  <button type="submit" form="clients-single-delete-<?= (int) $client['id'] ?>"
                    class="admin-btn admin-btn-sm admin-btn-danger"
                    onclick="return confirm(<?= e(json_encode($deleteConfirm)) ?>);">Delete</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php if ($canBulk): ?>
    </form>
    <?php endif; ?>

    <?php if (Auth::can('clients.delete')): ?>
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
  var filterForm = document.getElementById('clients-filter-form');
  if (!filterForm) return;
  filterForm.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { filterForm.submit(); });
  });
  filterForm.addEventListener('submit', function () {
    filterForm.querySelectorAll('select, input[type="search"]').forEach(function (el) {
      if (el.value === '') el.disabled = true;
    });
  });
})();

(function () {
  var form = document.getElementById('clients-bulk-form');
  if (!form) return;

  var bar = document.getElementById('clients-bulk-bar');
  var countEl = document.getElementById('clients-bulk-count');
  var actionBtns = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-action]'));
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
    actionBtns.forEach(function (btn) { btn.disabled = count < 1; });
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

  window.confirmClientsBulk = function (event) {
    var count = selectedCount();
    if (count < 1) {
      alert('Select at least one client.');
      return false;
    }
    var submitter = event && event.submitter;
    var plural = count === 1 ? '' : 's';
    if (submitter && submitter.hasAttribute('data-bulk-delete')) {
      return confirm(
        'Delete ' + count + ' selected client' + plural +
        '? Linked form submissions stay in the system, but these client records will be removed. This cannot be undone.'
      );
    }
    var label = submitter && submitter.value === 'bulk_active' ? 'active' : 'inactive';
    return confirm('Mark ' + count + ' selected client' + plural + ' as ' + label + '?');
  };

  sync();
})();
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
