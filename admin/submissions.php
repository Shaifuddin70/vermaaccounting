<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$formId = (int) ($_GET['form_id'] ?? 0);
$tab = (string) ($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'pending', 'complete'], true)) {
    $tab = 'all';
}

$yearParam = $_GET['year'] ?? '';
$taxYearFilter = ($yearParam !== '' && $yearParam !== 'all') ? (int) $yearParam : null;
if ($taxYearFilter !== null && $taxYearFilter < 1) {
    $taxYearFilter = null;
}

$repo = new FormRepository();
$form = $repo->find($formId);

if (!$form) {
    header('Location: /admin/forms.php');
    exit;
}

$schema = $repo->decodeSchema($form);
$taxYearOn = form_tax_year_enabled($schema);
$availableYears = $taxYearOn ? form_tax_year_options($schema) : [];
$submissionYears = $repo->submissionTaxYearsForForm($formId);
$yearOptions = $taxYearOn
    ? array_values(array_unique(array_merge($availableYears, $submissionYears)))
    : [];
rsort($yearOptions, SORT_NUMERIC);

$counts = $repo->submissionStatusCounts($formId, $taxYearFilter);
$statusFilter = $tab === 'all' ? null : $tab;
$submissions = $repo->submissionsForForm($formId, $statusFilter, $taxYearFilter);
$inputFields = array_filter($schema['fields'], fn ($f) => !in_array($f['type'], ['heading', 'paragraph'], true));
$csrf = Auth::csrfToken();

function submissions_list_url(int $formId, string $tab, ?int $year, bool $includeYear = false): string
{
    $params = ['form_id' => $formId, 'tab' => $tab];
    if ($includeYear) {
        $params['year'] = $year !== null ? (string) $year : 'all';
    }
    return '/admin/submissions.php?' . http_build_query($params);
}

$pageTitle = 'Responses: ' . $form['title'];
$activeNav = Auth::userRole() === 'admin' ? 'forms' : 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Responses: <?= e($form['title']) ?></h1>
  <div class="admin-header-actions">
    <?php if ($counts['all'] > 0): ?>
      <?php
        $exportQs = 'form_id=' . $formId;
        if ($taxYearFilter) {
            $exportQs .= '&year=' . $taxYearFilter;
        }
      ?>
      <a href="/admin/export-csv.php?<?= e($exportQs) ?>" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php endif; ?>
    <?php if (Auth::userRole() === 'admin'): ?>
      <a href="/admin/form-builder.php?id=<?= $formId ?>" class="admin-btn admin-btn-secondary">← Edit form</a>
    <?php else: ?>
      <a href="/admin/reviewer-submissions.php" class="admin-btn admin-btn-secondary">← All forms</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($taxYearOn): ?>
  <form method="get" action="/admin/submissions.php" class="submissions-year-filter">
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <label for="year-filter" class="submissions-year-filter-label">Tax year</label>
    <select name="year" id="year-filter" class="submissions-year-select" onchange="this.form.submit()">
      <option value="all" <?= $taxYearFilter === null ? 'selected' : '' ?>>All years</option>
      <?php foreach ($yearOptions as $y):
        $yearCount = $repo->submissionStatusCounts($formId, $y)['all'];
      ?>
        <option value="<?= (int) $y ?>" <?= $taxYearFilter === $y ? 'selected' : '' ?>>
          <?= (int) $y ?><?= $yearCount > 0 ? ' (' . $yearCount . ')' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>

<nav class="admin-tabs" aria-label="Filter submissions">
  <a href="<?= e(submissions_list_url($formId, 'all', $taxYearFilter, $taxYearOn)) ?>"
    class="admin-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
    All <span class="admin-tab-count"><?= $counts['all'] ?></span>
  </a>
  <a href="<?= e(submissions_list_url($formId, 'pending', $taxYearFilter, $taxYearOn)) ?>"
    class="admin-tab <?= $tab === 'pending' ? 'is-active' : '' ?>">
    Pending <span class="admin-tab-count"><?= $counts['pending'] ?></span>
  </a>
  <a href="<?= e(submissions_list_url($formId, 'complete', $taxYearFilter, $taxYearOn)) ?>"
    class="admin-tab <?= $tab === 'complete' ? 'is-active' : '' ?>">
    Complete <span class="admin-tab-count"><?= $counts['complete'] ?></span>
  </a>
</nav>

<div class="admin-card">
  <?php if (!$submissions): ?>
    <p style="color:#64748b;">No <?= $tab === 'all' ? '' : e($tab) . ' ' ?>submissions<?= $taxYearFilter ? ' for ' . $taxYearFilter : '' ?><?= $tab === 'all' ? ' yet' : '' ?>.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Date</th>
          <?php if ($taxYearOn): ?><th>Tax year</th><?php endif; ?>
          <th>Status</th>
          <?php foreach (array_slice($inputFields, 0, $taxYearOn ? 3 : 4) as $field): ?>
            <th><?= e($field['label']) ?></th>
          <?php endforeach; ?>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($submissions as $sub):
          $data = json_decode($sub['data_json'], true) ?: [];
          $status = $sub['status'] ?? 'pending';
          $subYear = submission_tax_year_label($sub);
        ?>
          <tr>
            <td><?= e($sub['created_at']) ?></td>
            <?php if ($taxYearOn): ?>
              <td><?= $subYear !== '' ? e($subYear) : '—' ?></td>
            <?php endif; ?>
            <td>
              <span class="submission-status-badge submission-status-badge--<?= e($status) ?>">
                <?= e(submission_status_label($status)) ?>
              </span>
            </td>
            <?php foreach (array_slice($inputFields, 0, $taxYearOn ? 3 : 4) as $field):
              $key = $field['name'];
              $val = $data[$key] ?? '';
              if (is_array($val)) {
                  $val = implode(', ', $val);
              }
            ?>
              <td><?= e(mb_strimwidth((string) $val, 0, 50, '…')) ?></td>
            <?php endforeach; ?>
            <td class="admin-table-actions">
              <a href="/admin/submission.php?id=<?= (int) $sub['id'] ?>&form_id=<?= $formId ?>" class="admin-btn admin-btn-primary admin-btn-sm">View</a>
              <?php if ($status === 'pending'): ?>
                <form method="post" action="/admin/submission-status.php" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                  <input type="hidden" name="form_id" value="<?= $formId ?>">
                  <input type="hidden" name="status" value="complete">
                  <input type="hidden" name="tab" value="<?= e($tab) ?>">
                  <?php if ($taxYearOn): ?>
                    <input type="hidden" name="year" value="<?= $taxYearFilter !== null ? (string) $taxYearFilter : 'all' ?>">
                  <?php endif; ?>
                  <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Mark complete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
