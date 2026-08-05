<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$serviceRepo = new InvoiceServiceRepository();

$search = trim((string) ($_GET['q'] ?? ''));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = $serviceRepo->count($search !== '' ? $search : null);
$pagination = pagination_meta($total, $page, $perPage);
$services = $serviceRepo->all($search !== '' ? $search : null, null, $pagination['per_page'], $pagination['offset']);

$paginationPath = '/admin/invoice-services';
$paginationQuery = $search !== '' ? ['q' => $search] : [];
$paginationLabel = 'services';
$paginationAriaLabel = 'Service list pages';
$paginationUrl = fn (int $p) => pagination_url('/admin/invoice-services', $paginationQuery, $p, $pagination['per_page']);

$csrf = Auth::csrfToken();
$pageTitle = 'Invoice services';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Invoice services</h1>
  <div class="admin-header-actions">
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← Invoices</a>
    <a href="/admin/invoice-service-edit" class="admin-btn admin-btn-primary">+ Add service</a>
  </div>
</div>

<div class="admin-card clients-filters-card">
  <form method="get" action="/admin/invoice-services" class="clients-filter-form">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <div class="admin-field clients-filter-field clients-filter-field--search">
      <label for="services-search">Search</label>
      <input type="search" id="services-search" name="q" value="<?= e($search) ?>" placeholder="Service name or description…">
    </div>
    <div class="clients-filter-actions">
      <button type="submit" class="admin-btn admin-btn-secondary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="/admin/invoice-services" class="admin-btn admin-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <?php if (!$services): ?>
    <div class="admin-empty-state">
      <h2 class="admin-empty-state-title">No services yet</h2>
      <p class="admin-empty-state-text">Add priced services (e.g. Corporate Tax Return) to select them when creating invoices.</p>
      <a href="/admin/invoice-service-edit" class="admin-btn admin-btn-primary">Add service</a>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Service</th>
            <th>Price</th>
            <th>Status</th>
            <th>Sort</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $service): ?>
            <tr>
              <td>
                <a href="/admin/invoice-service-edit?id=<?= (int) $service['id'] ?>">
                  <strong><?= e((string) $service['name']) ?></strong>
                </a>
                <?php if (trim((string) ($service['description'] ?? '')) !== ''): ?>
                  <div class="admin-field-hint" style="margin-top:0.25rem;"><?php
                    $desc = (string) ($service['description'] ?? '');
                    echo e(strlen($desc) > 120 ? substr($desc, 0, 117) . '…' : $desc);
                  ?></div>
                <?php endif; ?>
              </td>
              <td><?= e(invoice_format_money($service['unit_price'] ?? 0)) ?></td>
              <td>
                <?php if (!empty($service['is_active'])): ?>
                  <span class="admin-badge admin-badge-success">Active</span>
                <?php else: ?>
                  <span class="admin-badge">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= (int) ($service['sort_order'] ?? 0) ?></td>
              <td class="admin-table-actions">
                <a href="/admin/invoice-service-edit?id=<?= (int) $service['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
                <form method="post" action="/admin/invoice-service-action" class="inline-form" onsubmit="return confirm('Delete this service?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
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
