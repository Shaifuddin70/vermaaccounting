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
$partnerId = partner_user_id();
$allForms = array_values(array_filter(
    $repo->all(),
    static fn (array $f): bool => !is_file_manager_form($f)
));
usort($allForms, static fn (array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));
$formSubmissionCounts = $repo->submissionCountsByFormId($partnerId);

if ($formId < 1 && $allForms !== []) {
    $formId = (int) $allForms[0]['id'];
}

$form = $repo->find($formId);

if (!$form || is_file_manager_form($form)) {
    if ($allForms === []) {
        header('Location: /admin/' . (Auth::userRole() === 'admin' ? 'forms' : 'reviewer-submissions'));
        exit;
    }
    header('Location: /admin/submissions?form_id=' . (int) $allForms[0]['id']);
    exit;
}

assert_form_submissions_access($form);

$schema = $repo->decodeSchema($form);
$taxYearOn = form_tax_year_enabled($schema);
$availableYears = $taxYearOn ? form_tax_year_options($schema) : [];
$submissionYears = $repo->submissionTaxYearsForForm($formId);
$yearOptions = $taxYearOn
    ? array_values(array_unique(array_merge($availableYears, $submissionYears)))
    : [];
rsort($yearOptions, SORT_NUMERIC);

$counts = $repo->submissionStatusCounts($formId, $taxYearFilter, $partnerId);
$statusFilter = $tab === 'all' ? null : $tab;
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$submissionTotal = $repo->countSubmissionsForForm($formId, $statusFilter, $taxYearFilter, $partnerId);
$pagination = pagination_meta($submissionTotal, $page, $perPage);
$submissions = $repo->submissionsForForm(
    $formId,
    $statusFilter,
    $taxYearFilter,
    $pagination['per_page'],
    $pagination['offset'],
    $partnerId
);
$inputFields = array_filter($schema['fields'], fn ($f) => !in_array($f['type'], ['heading', 'paragraph', 'page_break'], true));
$csrf = Auth::csrfToken();

$paginationPath = '/admin/submissions';
$paginationQuery = array_filter([
    'form_id' => $formId,
    'tab' => $tab,
    'year' => $taxYearOn ? ($taxYearFilter !== null ? (string) $taxYearFilter : 'all') : null,
], fn ($v) => $v !== null && $v !== '');
$paginationLabel = 'submissions';
$paginationAriaLabel = 'Submission list pages';
$paginationUrl = fn (int $p) => submissions_list_url($formId, $tab, $taxYearFilter, $taxYearOn, $p, $pagination['per_page']);

function submissions_list_url(int $formId, string $tab, ?int $year, bool $includeYear = false, int $page = 1, ?int $perPage = null): string
{
    $params = ['form_id' => $formId, 'tab' => $tab];
    if ($includeYear) {
        $params['year'] = $year !== null ? (string) $year : 'all';
    }
    return pagination_url('/admin/submissions', $params, $page, $perPage);
}

$pageTitle = 'Responses: ' . $form['title'];
$activeNav = Auth::userRole() === 'admin' ? 'forms' : 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Responses: <?= e($form['title']) ?></h1>
  <div class="admin-header-actions">
    <?php if ($counts['all'] > 0 && Auth::userRole() !== 'partner'): ?>
      <?php
        $exportQs = 'form_id=' . $formId;
        if ($taxYearFilter) {
            $exportQs .= '&year=' . $taxYearFilter;
        }
      ?>
      <a href="/admin/export-csv?<?= e($exportQs) ?>" class="admin-btn admin-btn-secondary">Export CSV</a>
    <?php endif; ?>
    <?php if (Auth::userRole() === 'admin'): ?>
      <a href="/admin/form-builder?id=<?= $formId ?>" class="admin-btn admin-btn-secondary">← Edit form</a>
    <?php else: ?>
      <a href="/admin/reviewer-submissions" class="admin-btn admin-btn-secondary">← All forms</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($allForms !== []): ?>
  <nav class="admin-tabs submissions-form-tabs" aria-label="Forms">
    <?php foreach ($allForms as $formTab):
      $tabFormId = (int) $formTab['id'];
      $tabCount = (int) ($formSubmissionCounts[$tabFormId]['all'] ?? 0);
    ?>
      <a href="<?= e(submissions_list_url($tabFormId, 'all', $taxYearFilter, $taxYearOn, 1, $pagination['per_page'])) ?>"
        class="admin-tab <?= $formId === $tabFormId ? 'is-active' : '' ?>">
        <?= e((string) $formTab['title']) ?>
        <span class="admin-tab-count"><?= number_format($tabCount) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if ($taxYearOn): ?>
  <form method="get" action="/admin/submissions" class="submissions-year-filter">
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
      <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
    <?php endif; ?>
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

<nav class="admin-tabs submissions-status-tabs" aria-label="Filter submissions">
  <a href="<?= e(submissions_list_url($formId, 'all', $taxYearFilter, $taxYearOn, 1, $pagination['per_page'])) ?>"
    class="admin-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
    All
  </a>
  <a href="<?= e(submissions_list_url($formId, 'pending', $taxYearFilter, $taxYearOn, 1, $pagination['per_page'])) ?>"
    class="admin-tab <?= $tab === 'pending' ? 'is-active' : '' ?>">
    Pending
  </a>
  <a href="<?= e(submissions_list_url($formId, 'complete', $taxYearFilter, $taxYearOn, 1, $pagination['per_page'])) ?>"
    class="admin-tab <?= $tab === 'complete' ? 'is-active' : '' ?>">
    Complete
  </a>
</nav>

<div class="admin-card">
  <?php if (!$submissions): ?>
    <div class="admin-empty-state">
      <span class="admin-empty-state-icon" aria-hidden="true">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      </span>
      <h2 class="admin-empty-state-title">No <?= $tab === 'all' ? '' : e($tab) . ' ' ?>submissions<?= $taxYearFilter ? ' for ' . $taxYearFilter : '' ?><?= $tab === 'all' && !$taxYearFilter ? ' yet' : '' ?></h2>
      <?php if ($tab !== 'all' || $taxYearFilter): ?>
        <p class="admin-empty-state-text">Try the “All” tab or a different tax year.</p>
        <a href="<?= e(submissions_list_url($formId, 'all', null, $taxYearOn, 1, $pagination['per_page'])) ?>" class="admin-btn admin-btn-secondary">View all submissions</a>
      <?php elseif ($form['status'] === 'published'): ?>
        <p class="admin-empty-state-text">Share the form link to start collecting responses: <a href="/form/<?= e($form['slug']) ?>" target="_blank" rel="noopener">/form/<?= e($form['slug']) ?></a></p>
      <?php else: ?>
        <p class="admin-empty-state-text">This form is still a draft — publish it to start collecting responses.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
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
              <a href="/admin/submission?id=<?= (int) $sub['id'] ?>&form_id=<?= $formId ?>" class="admin-btn admin-btn-primary admin-btn-sm">View</a>
              <?php if (Auth::userRole() === 'admin'): ?>
                <a href="<?= e(invoice_edit_url_from_submission((int) $sub['id'], $formId)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Invoice</a>
              <?php endif; ?>
              <?php if ($status === 'pending'): ?>
                <form method="post" action="/admin/submission-status" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                  <input type="hidden" name="form_id" value="<?= $formId ?>">
                  <input type="hidden" name="status" value="complete">
                  <input type="hidden" name="tab" value="<?= e($tab) ?>">
                  <?php if ($taxYearOn): ?>
                    <input type="hidden" name="year" value="<?= $taxYearFilter !== null ? (string) $taxYearFilter : 'all' ?>">
                  <?php endif; ?>
                  <?php if ($pagination['page'] > 1): ?>
                    <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>">
                  <?php endif; ?>
                  <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
                    <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
                  <?php endif; ?>
                  <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Mark complete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
