<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('reports.view');

$fromInput = trim((string) ($_GET['from'] ?? ''));
$toInput = trim((string) ($_GET['to'] ?? ''));
$period = report_resolve_period(
    $fromInput !== '' ? $fromInput : null,
    $toInput !== '' ? $toInput : null
);

$invoiceRepo = new InvoiceRepository();
$clientRepo = new ClientRepository();
$formRepo = new FormRepository();
$partnerId = partner_user_id();

$invoiceRows = $invoiceRepo->reportRows($period['from'], $period['to'], $partnerId);
$summary = report_summarize_invoices($invoiceRows);
$topClients = report_top_clients($invoiceRows, 8);
$submissionStats = $formRepo->submissionCountsBetween($period['from'], $period['to'], $partnerId);
$newClients = $clientRepo->countCreatedBetween($period['from'], $period['to'], $partnerId);
$totalClients = $clientRepo->count(null, $partnerId);

$chartYear = (int) substr($period['to'], 0, 4);
$yearFrom = sprintf('%04d-01-01', $chartYear);
$yearTo = sprintf('%04d-12-31', $chartYear);
$yearRows = $invoiceRepo->reportRows($yearFrom, $yearTo, $partnerId);
$series = report_build_series($yearRows, 'month', $yearFrom, $yearTo);

$maxSeriesIncome = 0.0;
foreach ($series as $bucket) {
    $maxSeriesIncome = max($maxSeriesIncome, (float) $bucket['income']);
}

$recentIncome = array_values(array_filter(
    $invoiceRows,
    static fn (array $row): bool => report_invoice_counts_as_income((string) ($row['status'] ?? ''), $row)
));
usort($recentIncome, static function (array $a, array $b): int {
    $cmp = strcmp((string) ($b['invoice_date'] ?? ''), (string) ($a['invoice_date'] ?? ''));
    return $cmp !== 0 ? $cmp : ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
});
$recentIncome = array_slice($recentIncome, 0, 10);

$avgInvoice = $summary['billed_count'] > 0
    ? invoice_money($summary['billed'] / $summary['billed_count'])
    : 0.0;

$pageTitle = 'Business reports';
$activeNav = 'reports';

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Business summary</h1>
</div>

<div class="admin-card reports-filter-card">
  <form method="get" action="/admin/reports" class="reports-filter-form" id="reports-filter-form">
    <div class="reports-date-pickers">
      <div class="admin-field">
        <label for="report-from">From</label>
        <input type="date" id="report-from" name="from" value="<?= e($period['from']) ?>" required>
      </div>
      <div class="admin-field">
        <label for="report-to">To</label>
        <input type="date" id="report-to" name="to" value="<?= e($period['to']) ?>" required>
      </div>
      <button type="submit" class="admin-btn admin-btn-primary">Apply</button>
    </div>
  </form>
</div>

<div class="md-kpi-row reports-kpi-row">
  <div class="md-kpi-card">
    <div class="md-kpi-body">
      <div class="md-kpi-value"><?= e(invoice_format_money($summary['income'])) ?></div>
      <div class="md-kpi-label">Income</div>
      <div class="md-kpi-sub">Received: advances + payments (incl. partial) · <?= number_format($summary['income_count']) ?> invoice<?= $summary['income_count'] === 1 ? '' : 's' ?></div>
    </div>
  </div>
  <div class="md-kpi-card">
    <div class="md-kpi-body">
      <div class="md-kpi-value"><?= e(invoice_format_money($summary['billed'])) ?></div>
      <div class="md-kpi-label">Billed</div>
      <div class="md-kpi-sub">Approved/sent/paid invoices · <?= e(invoice_format_money($summary['outstanding_total'])) ?> outstanding</div>
    </div>
  </div>
  <div class="md-kpi-card">
    <div class="md-kpi-body">
      <div class="md-kpi-value"><?= number_format($summary['clients_served']) ?></div>
      <div class="md-kpi-label">Clients served</div>
      <div class="md-kpi-sub"><?= number_format($newClients) ?> new · <?= number_format($totalClients) ?> total directory</div>
    </div>
  </div>
  <div class="md-kpi-card">
    <div class="md-kpi-body">
      <div class="md-kpi-value"><?= e(invoice_format_money($avgInvoice)) ?></div>
      <div class="md-kpi-label">Average invoice</div>
      <div class="md-kpi-sub"><?= number_format($summary['approved_count']) ?> approved · <?= number_format($summary['sent_count']) ?> sent · <?= number_format($summary['paid_count']) ?> paid</div>
    </div>
  </div>
</div>

<div class="reports-grid">
  <section class="admin-card reports-panel">
    <h2 class="admin-card-title">Income over time · <?= (int) $chartYear ?></h2>
    <?php if ($series === [] || $maxSeriesIncome <= 0): ?>
      <p class="admin-empty-state-text">No income received on invoices dated in <?= (int) $chartYear ?>.</p>
    <?php else: ?>
      <div class="reports-year-chart" role="img" aria-label="Monthly income for <?= (int) $chartYear ?>">
        <div class="reports-year-chart-plot">
          <?php foreach ($series as $bucket):
            $pct = $maxSeriesIncome > 0 ? max(2, round(((float) $bucket['income'] / $maxSeriesIncome) * 100)) : 0;
            if ((float) $bucket['income'] <= 0) {
                $pct = 0;
            }
            $monthLabel = DateTimeImmutable::createFromFormat('!Y-m', $bucket['key']);
            $short = $monthLabel ? $monthLabel->format('M') : $bucket['label'];
            ?>
            <div class="reports-year-col" title="<?= e($bucket['label'] . ': ' . invoice_format_money($bucket['income']) . ' · ' . (int) $bucket['invoice_count'] . ' invoice(s)') ?>">
              <div class="reports-year-col-value"><?= (float) $bucket['income'] > 0 ? e(invoice_format_money($bucket['income'])) : '' ?></div>
              <div class="reports-year-col-bar-wrap">
                <div class="reports-year-col-bar<?= (float) $bucket['income'] > 0 ? ' is-active' : '' ?>" style="height: <?= (int) $pct ?>%;"></div>
              </div>
              <div class="reports-year-col-label"><?= e($short) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-card reports-panel">
    <h2 class="admin-card-title">Invoice status</h2>
    <ul class="reports-status-list">
      <?php foreach (invoice_status_options() as $status):
        $row = $summary['by_status'][$status] ?? ['count' => 0, 'income' => 0.0, 'total' => 0.0];
        ?>
        <li>
          <div class="reports-status-head">
            <span class="admin-badge <?= e(match ($status) {
                'approved' => 'admin-badge-success',
                'sent' => 'admin-badge-info',
                'paid' => 'admin-badge-paid',
                default => '',
            }) ?>"><?= e(invoice_status_label($status)) ?></span>
            <strong><?= number_format((int) $row['count']) ?></strong>
          </div>
          <div class="reports-status-meta">
            Received <?= e(invoice_format_money($row['income'])) ?>
            · Gross <?= e(invoice_format_money($row['total'])) ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="reports-side-stats">
      <div>
        <span class="admin-field-hint">Advances received</span>
        <strong><?= e(invoice_format_money($summary['advance_total'])) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">Payments recorded</span>
        <strong><?= e(invoice_format_money($summary['payments_total'])) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">Outstanding</span>
        <strong><?= e(invoice_format_money($summary['outstanding_total'])) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">Walk-in invoices</span>
        <strong><?= number_format($summary['walk_in_invoices']) ?></strong>
      </div>
    </div>
  </section>
</div>

<div class="reports-grid">
  <section class="admin-card reports-panel">
    <h2 class="admin-card-title">Workload</h2>
    <div class="reports-side-stats reports-side-stats--wide">
      <div>
        <span class="admin-field-hint">Submissions received</span>
        <strong><?= number_format($submissionStats['received']) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">Completed</span>
        <strong><?= number_format($submissionStats['completed']) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">Still pending</span>
        <strong><?= number_format($submissionStats['pending']) ?></strong>
      </div>
      <div>
        <span class="admin-field-hint">New clients</span>
        <strong><?= number_format($newClients) ?></strong>
      </div>
    </div>
  </section>

  <section class="admin-card reports-panel">
    <h2 class="admin-card-title">Top clients by income</h2>
    <?php if ($topClients === []): ?>
      <p class="admin-empty-state-text">No billed clients in this period.</p>
    <?php else: ?>
      <div class="admin-table-scroll">
        <table class="admin-table reports-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Invoices</th>
              <th>Income</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topClients as $client): ?>
              <tr>
                <td>
                  <?php if (!empty($client['client_id'])): ?>
                    <a href="/admin/client?id=<?= (int) $client['client_id'] ?>"><?= e($client['name']) ?></a>
                  <?php else: ?>
                    <?= e($client['name']) ?>
                  <?php endif; ?>
                </td>
                <td><?= number_format((int) $client['invoice_count']) ?></td>
                <td><strong><?= e(invoice_format_money($client['income'])) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="admin-card reports-panel">
  <div class="reports-panel-header">
    <h2 class="admin-card-title">Recent invoices</h2>
    <a href="/admin/invoices" class="admin-btn admin-btn-secondary admin-btn-sm">All invoices</a>
  </div>
  <?php if ($recentIncome === []): ?>
    <p class="admin-empty-state-text">No billed or paid invoices in this period.</p>
  <?php else: ?>
    <div class="admin-table-scroll">
      <table class="admin-table reports-table">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Client</th>
            <th>Date</th>
            <th>Status</th>
            <th>Billed</th>
            <th>Received</th>
            <th>Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentIncome as $inv):
            $name = trim((string) ($inv['client_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($inv['bill_to_name'] ?? ''));
            }
            if ($name === '') {
                $name = trim((string) ($inv['bill_to_company'] ?? '')) ?: '—';
            }
            $st = (string) ($inv['status'] ?? 'draft');
            $amounts = report_invoice_amounts($inv);
            ?>
            <tr>
              <td><a href="/admin/invoice-view?id=<?= (int) $inv['id'] ?>">#<?= e((string) $inv['invoice_number']) ?></a></td>
              <td><?= e($name) ?></td>
              <td><?= e(invoice_format_date((string) ($inv['invoice_date'] ?? ''))) ?></td>
              <td><?= e(invoice_status_label($st)) ?></td>
              <td><?= e(invoice_format_money($amounts['billed'])) ?></td>
              <td><strong><?= e(invoice_format_money($amounts['income'])) ?></strong></td>
              <td><?= e(invoice_format_money($amounts['outstanding'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
