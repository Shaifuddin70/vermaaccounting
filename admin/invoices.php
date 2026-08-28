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

<div class="admin-card invoice-toolbar-card">
  <form method="get" action="/admin/invoices" class="invoice-toolbar-form" id="invoice-list-filter" data-realtime="1">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field invoice-toolbar-search">
      <label for="invoices-search">Search</label>
      <input type="search" id="invoices-search" name="q" value="<?= e($search) ?>"
        placeholder="Search by invoice #, client name, or company…"
        autocomplete="off">
    </div>
    <div class="admin-field invoice-toolbar-status">
      <label for="invoices-status">Status</label>
      <select id="invoices-status" name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All statuses</option>
        <?php foreach (invoice_status_options() as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e(invoice_status_label($opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($search !== '' || $status !== 'all'): ?>
      <div class="invoice-toolbar-clear">
        <a href="/admin/invoices" class="admin-btn admin-btn-secondary">Clear</a>
      </div>
    <?php endif; ?>
  </form>
  <p class="admin-field-hint invoice-toolbar-hint" id="invoice-list-hint">
    <?= number_format($total) ?> invoice<?= $total === 1 ? '' : 's' ?>
    <?php if ($search !== ''): ?> matching “<?= e($search) ?>”<?php endif; ?>
  </p>
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
      <?php if ($search !== '' || $status !== 'all'): ?>
        <h2 class="admin-empty-state-title">No matching invoices</h2>
        <p class="admin-empty-state-text">Try a different client name, invoice number, or status.</p>
        <a href="/admin/invoices" class="admin-btn admin-btn-secondary">Clear filters</a>
      <?php else: ?>
        <h2 class="admin-empty-state-title">No invoices yet</h2>
        <p class="admin-empty-state-text">Create an invoice by selecting a client and one or more services.</p>
        <a href="/admin/invoice-edit" class="admin-btn admin-btn-primary">Create invoice</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="admin-table-wrap">
      <table class="admin-table invoice-list-table">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Client</th>
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
              $clientName = trim((string) ($inv['client_name'] ?? ''));
              $clientCompany = trim((string) ($inv['client_company'] ?? ''));
              $billCompany = trim((string) ($inv['bill_to_company'] ?? ''));
              $billPerson = trim((string) ($inv['bill_to_name'] ?? ''));
              $billLabel = $billCompany !== '' ? $billCompany : ($billPerson !== '' ? $billPerson : '—');
            ?>
            <tr>
              <td>
                <a href="/admin/invoice-view?id=<?= (int) $inv['id'] ?>">
                  <strong>#<?= e((string) $inv['invoice_number']) ?></strong>
                </a>
              </td>
              <td>
                <?php if ($clientName !== ''): ?>
                  <div class="invoice-client-cell">
                    <strong><?= e($clientName) ?></strong>
                    <?php if ($clientCompany !== '' && strcasecmp($clientCompany, $clientName) !== 0): ?>
                      <span class="admin-field-hint"><?= e($clientCompany) ?></span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="admin-field-hint">Manual</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="invoice-client-cell">
                  <span><?= e($billLabel) ?></span>
                  <?php if ($billCompany !== '' && $billPerson !== '' && strcasecmp($billCompany, $billPerson) !== 0): ?>
                    <span class="admin-field-hint"><?= e($billPerson) ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td><?= e(invoice_format_date((string) ($inv['invoice_date'] ?? ''))) ?></td>
              <td><?= e(invoice_format_date((string) ($inv['due_date'] ?? ''))) ?></td>
              <td><strong><?= e(invoice_format_money($inv['total'] ?? 0)) ?></strong></td>
              <td>
                <?php
                  $st = (string) ($inv['status'] ?? 'draft');
                  $badge = match ($st) {
                      'paid' => 'admin-badge-success',
                      'sent' => 'admin-badge-info',
                      'void' => 'admin-badge-muted',
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
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>
<script src="/admin/js/invoice-list.js?v=1" defer></script>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
