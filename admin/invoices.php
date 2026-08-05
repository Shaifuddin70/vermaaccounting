<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$invoiceRepo = new InvoiceRepository();
$serviceRepo = new InvoiceServiceRepository();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'all'));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();

$total = $invoiceRepo->count($search !== '' ? $search : null, $status);
$pagination = pagination_meta($total, $page, $perPage);
$invoices = $invoiceRepo->all($search !== '' ? $search : null, $status, $pagination['per_page'], $pagination['offset']);
$serviceCount = $serviceRepo->count(null, true);

$paginationQuery = array_filter([
    'q' => $search !== '' ? $search : null,
    'status' => $status !== 'all' ? $status : null,
]);
$paginationPath = '/admin/invoices';
$paginationLabel = 'invoices';
$paginationAriaLabel = 'Invoice list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/invoices', $paginationQuery, $p, $pagination['per_page']);

$csrf = Auth::csrfToken();
$pageTitle = 'Invoices';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Invoices</h1>
  <div class="admin-header-actions">
    <a href="/admin/invoice-settings" class="admin-btn admin-btn-secondary">Company details</a>
    <a href="/admin/invoice-services" class="admin-btn admin-btn-secondary">Services (<?= (int) $serviceCount ?>)</a>
    <a href="/admin/invoice-edit" class="admin-btn admin-btn-primary">+ New invoice</a>
  </div>
</div>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/invoices" class="clients-filter-form invoice-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="invoices-search">Search</label>
      <input type="search" id="invoices-search" name="q" value="<?= e($search) ?>" placeholder="Number, client, or company…">
    </div>
    <div class="admin-field">
      <label for="invoices-status">Status</label>
      <select id="invoices-status" name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        <?php foreach (invoice_status_options() as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(invoice_status_label($opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="clients-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Filter</button>
      <?php if ($search !== '' || $status !== 'all'): ?>
        <a href="/admin/invoices" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($serviceCount < 1): ?>
  <div class="admin-alert admin-alert-info">
    Add at least one service before creating invoices.
    <a href="/admin/invoice-service-edit">Add a service →</a>
  </div>
<?php endif; ?>

<div class="admin-card">
  <?php if (!$invoices): ?>
    <div class="admin-empty-state">
      <h2 class="admin-empty-state-title">No invoices yet</h2>
      <p class="admin-empty-state-text">Create an invoice by selecting a client and one or more services.</p>
      <a href="/admin/invoice-edit" class="admin-btn admin-btn-primary">Create invoice</a>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Bill to</th>
            <th>Date</th>
            <th>Due</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
            <?php
              $billName = trim((string) ($inv['bill_to_company'] ?? '')) !== ''
                ? (string) $inv['bill_to_company']
                : (string) ($inv['bill_to_name'] ?? $inv['client_name'] ?? '—');
            ?>
            <tr>
              <td>
                <a href="/admin/invoice-view?id=<?= (int) $inv['id'] ?>">
                  <strong>#<?= e((string) $inv['invoice_number']) ?></strong>
                </a>
              </td>
              <td><?= e($billName) ?></td>
              <td><?= e(invoice_format_date((string) ($inv['invoice_date'] ?? ''))) ?></td>
              <td><?= e(invoice_format_date((string) ($inv['due_date'] ?? ''))) ?></td>
              <td><?= e(invoice_format_money($inv['total'] ?? 0)) ?></td>
              <td>
                <?php
                  $st = (string) ($inv['status'] ?? 'draft');
                  $badge = match ($st) {
                      'paid' => 'admin-badge-success',
                      'sent' => 'admin-badge-info',
                      'void' => '',
                      default => '',
                  };
                ?>
                <span class="admin-badge <?= e($badge) ?>"><?= e(invoice_status_label($st)) ?></span>
              </td>
              <td class="admin-table-actions">
                <a href="/admin/invoice-view?id=<?= (int) $inv['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View</a>
                <a href="/admin/invoice-edit?id=<?= (int) $inv['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
                <form method="post" action="/admin/invoice-action" class="inline-form" onsubmit="return confirm('Delete invoice #<?= e((string) $inv['invoice_number']) ?>?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                  <button type="submit" name="action" value="delete" class="admin-btn admin-btn-secondary admin-btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
