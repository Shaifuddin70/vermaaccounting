<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$clientId = (int) ($_GET['id'] ?? 0);
$yearParam = (string) ($_GET['year'] ?? 'all');
$yearFilter = ($yearParam !== '' && $yearParam !== 'all') ? (int) $yearParam : null;
if ($yearFilter !== null && $yearFilter < 1) {
    $yearFilter = null;
}

$clientRepo = new ClientRepository();
$client = $clientRepo->find($clientId);

if (!$client) {
    header('Location: /admin/clients');
    exit;
}

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$yearCounts = $clientRepo->submissionYearCountsForClient($clientId);
$submissionTotal = $clientRepo->countSubmissionsForClient($clientId, $yearFilter);
$pagination = pagination_meta($submissionTotal, $page, $perPage);
$submissions = $clientRepo->submissionsForClient(
    $clientId,
    $yearFilter,
    $pagination['per_page'],
    $pagination['offset']
);

$pageTitle = $client['name'];
$activeNav = 'clients';

$paginationPath = '/admin/client';
$paginationQuery = array_filter([
    'id' => $clientId,
    'year' => $yearParam !== 'all' ? $yearParam : null,
], fn ($v) => $v !== null && $v !== '');
$paginationLabel = 'submissions';
$paginationAriaLabel = 'Client submission pages';
$paginationUrl = fn (int $p) => client_page_url($clientId, $yearParam, $p, $pagination['per_page']);

function client_page_url(int $clientId, string $year = 'all', int $page = 1, ?int $perPage = null): string
{
    $params = ['id' => $clientId];
    if ($year !== 'all') {
        $params['year'] = $year;
    }
    return pagination_url('/admin/client', $params, $page, $perPage);
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($client['name']) ?></h1>
  <div class="admin-header-actions">
    <span class="client-id-badge">Client ID: <?= (int) $client['id'] ?></span>
    <a href="/admin/clients" class="admin-btn admin-btn-secondary">← All clients</a>
  </div>
</div>

<div class="admin-card client-profile-card">
  <div class="client-profile-grid">
    <div>
      <span class="submission-meta-label">Client ID</span>
      <strong class="client-id-value"><?= (int) $client['id'] ?></strong>
      <p class="admin-field-hint" style="margin:0.35rem 0 0;">Share this number for document uploads. Clients can also use their email on file.</p>
    </div>
    <div>
      <span class="submission-meta-label">SIN</span>
      <strong><?= ($client['sin'] ?? '') !== '' ? e($client['sin']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Email</span>
      <strong><?= ($client['email'] ?? '') !== '' ? e($client['email']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Phone</span>
      <strong><?= ($client['phone'] ?? '') !== '' ? e($client['phone']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Birthday</span>
      <strong><?= !empty($client['date_of_birth']) ? e((string) $client['date_of_birth']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Company</span>
      <strong><?= ($client['company'] ?? '') !== '' ? e($client['company']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Submissions</span>
      <strong><?= array_sum($yearCounts) ?></strong>
    </div>
  </div>
</div>

<?php if ($yearCounts): ?>
  <form method="get" action="/admin/client" class="submissions-year-filter">
    <input type="hidden" name="id" value="<?= $clientId ?>">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
    <label for="client-year-filter" class="submissions-year-filter-label">Year</label>
    <select name="year" id="client-year-filter" class="submissions-year-select" onchange="this.form.submit()">
      <option value="all" <?= $yearFilter === null ? 'selected' : '' ?>>
        All years (<?= array_sum($yearCounts) ?>)
      </option>
      <?php foreach ($yearCounts as $y => $cnt): ?>
        <option value="<?= (int) $y ?>" <?= $yearFilter === $y ? 'selected' : '' ?>>
          <?= (int) $y ?> (<?= $cnt ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<div class="admin-card">
  <?php if ($submissionTotal === 0): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      </span>
      <h2 class="admin-empty-state-title">
        <?= $yearFilter !== null ? 'No submissions for ' . (int) $yearFilter : 'No submissions yet' ?>
      </h2>
      <?php if ($yearFilter !== null): ?>
        <p class="admin-empty-state-text">This client has submissions in other years.</p>
        <a href="<?= e(client_page_url($clientId)) ?>" class="admin-btn admin-btn-secondary">View all years</a>
      <?php else: ?>
        <p class="admin-empty-state-text">Form submissions linked to this client will appear here.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
    <table class="admin-table client-submissions-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Year</th>
          <th>Form</th>
          <th>Tax year</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $sub):
          $status = $sub['status'] ?? 'pending';
          $taxYear = submission_tax_year_label($sub);
          $displayYear = submission_display_year($sub);
        ?>
          <tr>
            <td class="clients-col-date"><?= e(substr((string) $sub['created_at'], 0, 16)) ?></td>
            <td><?= (int) $displayYear ?></td>
            <td><?= e($sub['form_title'] ?? 'Form') ?></td>
            <td><?= $taxYear !== '' ? e($taxYear) : '—' ?></td>
            <td>
              <span class="submission-status-badge submission-status-badge--<?= e($status) ?>">
                <?= e(submission_status_label($status)) ?>
              </span>
            </td>
            <td class="admin-table-actions">
              <a href="/admin/submission?id=<?= (int) $sub['id'] ?>&form_id=<?= (int) $sub['form_id'] ?>"
                class="admin-btn admin-btn-primary admin-btn-sm">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
