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
    header('Location: /admin/clients.php');
    exit;
}

$allSubmissions = $clientRepo->submissionsForClient($clientId);
$groupedByYear = group_submissions_by_year($allSubmissions);
$availableYears = array_keys($groupedByYear);
rsort($availableYears, SORT_NUMERIC);

if ($yearFilter !== null) {
    $displayGroups = isset($groupedByYear[$yearFilter])
        ? [$yearFilter => $groupedByYear[$yearFilter]]
        : [];
} else {
    $displayGroups = $groupedByYear;
}

$pageTitle = $client['name'];
$activeNav = 'clients';

function client_page_url(int $clientId, string $year = 'all'): string
{
    $params = ['id' => $clientId];
    if ($year !== 'all') {
        $params['year'] = $year;
    }
    return '/admin/client.php?' . http_build_query($params);
}

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1><?= e($client['name']) ?></h1>
  <div class="admin-header-actions">
    <a href="/admin/clients.php" class="admin-btn admin-btn-secondary">← All clients</a>
  </div>
</div>

<div class="admin-card client-profile-card">
  <div class="client-profile-grid">
    <div>
      <span class="submission-meta-label">CIN</span>
      <strong><?= ($client['cin'] ?? '') !== '' ? e($client['cin']) : '—' ?></strong>
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
      <span class="submission-meta-label">Company</span>
      <strong><?= ($client['company'] ?? '') !== '' ? e($client['company']) : '—' ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Submissions</span>
      <strong><?= count($allSubmissions) ?></strong>
    </div>
  </div>
</div>

<?php if ($availableYears): ?>
  <form method="get" action="/admin/client.php" class="submissions-year-filter">
    <input type="hidden" name="id" value="<?= $clientId ?>">
    <label for="client-year-filter" class="submissions-year-filter-label">Year</label>
    <select name="year" id="client-year-filter" class="submissions-year-select" onchange="this.form.submit()">
      <option value="all" <?= $yearFilter === null ? 'selected' : '' ?>>
        All years (<?= count($allSubmissions) ?>)
      </option>
      <?php foreach ($availableYears as $y): ?>
        <option value="<?= (int) $y ?>" <?= $yearFilter === $y ? 'selected' : '' ?>>
          <?= (int) $y ?> (<?= count($groupedByYear[$y]) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<?php if (!$allSubmissions): ?>
  <div class="admin-card">
    <p class="client-no-submissions">No form submissions linked to this client yet.</p>
  </div>
<?php elseif (!$displayGroups): ?>
  <div class="admin-card">
    <p class="client-no-submissions">No submissions for <?= (int) $yearFilter ?>.</p>
    <p><a href="<?= e(client_page_url($clientId)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View all years</a></p>
  </div>
<?php else: ?>
  <?php foreach ($displayGroups as $year => $submissions): ?>
    <section class="client-year-section">
      <?php if ($yearFilter === null): ?>
        <h2 class="client-year-heading">
          <?= (int) $year ?>
          <span class="client-year-count"><?= count($submissions) ?> submission<?= count($submissions) === 1 ? '' : 's' ?></span>
        </h2>
      <?php endif; ?>

      <div class="admin-card">
        <table class="admin-table client-submissions-table">
          <thead>
            <tr>
              <th>Date</th>
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
            ?>
              <tr>
                <td class="clients-col-date"><?= e(substr((string) $sub['created_at'], 0, 16)) ?></td>
                <td><?= e($sub['form_title'] ?? 'Form') ?></td>
                <td><?= $taxYear !== '' ? e($taxYear) : e((string) $year) ?></td>
                <td>
                  <span class="submission-status-badge submission-status-badge--<?= e($status) ?>">
                    <?= e(submission_status_label($status)) ?>
                  </span>
                </td>
                <td class="admin-table-actions">
                  <a href="/admin/submission.php?id=<?= (int) $sub['id'] ?>&form_id=<?= (int) $sub['form_id'] ?>"
                    class="admin-btn admin-btn-primary admin-btn-sm">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
