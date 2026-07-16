<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$repo = new FormRepository();
$userRepo = new UserRepository();
$role = Auth::userRole();
$scopePartnerId = partner_user_id();

$tab = (string) ($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'pending', 'complete'], true)) {
    $tab = 'all';
}

$formId = isset($_GET['form_id']) && $_GET['form_id'] !== '' ? (int) $_GET['form_id'] : 0;
$partnerFilterId = isset($_GET['partner_id']) && $_GET['partner_id'] !== '' ? (int) $_GET['partner_id'] : 0;
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

// Partners can only see their own referenced submissions; hide partner filter for them.
$showPartnerFilter = $role !== 'partner';
if (!$showPartnerFilter) {
    $partnerFilterId = 0;
}

$allForms = array_values(array_filter(
    $repo->all(),
    static fn(array $f): bool => !is_file_manager_form($f)
));
usort($allForms, static fn(array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));

$partners = $showPartnerFilter ? $userRepo->activePartners() : [];

$filters = [
    'form_id' => $formId > 0 ? $formId : null,
    'status' => $tab === 'all' ? null : $tab,
    'date_from' => $dateFrom !== '' ? $dateFrom : null,
    'date_to' => $dateTo !== '' ? $dateTo : null,
    'partner_id' => $partnerFilterId > 0 ? $partnerFilterId : null,
    'search' => $search !== '' ? $search : null,
    'scope_partner_id' => $scopePartnerId,
];

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$total = $repo->countReviewerSubmissions($filters);
$pagination = pagination_meta($total, $page, $perPage);
$submissions = $repo->listReviewerSubmissions($filters, $pagination['per_page'], $pagination['offset']);

$partnerNames = $repo->partnerNamesBySubmissionIds(array_map(
    static fn(array $row): int => (int) $row['id'],
    $submissions
));

$statusCounts = [
    'all' => $repo->countReviewerSubmissions(array_merge($filters, ['status' => null])),
    'pending' => $repo->countReviewerSubmissions(array_merge($filters, ['status' => 'pending'])),
    'complete' => $repo->countReviewerSubmissions(array_merge($filters, ['status' => 'complete'])),
];

$hasActiveFilters = $formId > 0 || $partnerFilterId > 0 || $dateFrom !== '' || $dateTo !== '' || $search !== '';

function rs_list_url(
    string $tab,
    int $formId,
    int $partnerId,
    string $dateFrom,
    string $dateTo,
    string $search,
    int $page = 1,
    ?int $perPage = null
): string {
    $params = ['tab' => $tab];
    if ($formId > 0) {
        $params['form_id'] = $formId;
    }
    if ($partnerId > 0) {
        $params['partner_id'] = $partnerId;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    return pagination_url('/admin/reviewer-submissions', $params, $page, $perPage);
}

$paginationPath = '/admin/reviewer-submissions';
$paginationQuery = array_filter([
    'tab' => $tab,
    'form_id' => $formId > 0 ? $formId : null,
    'partner_id' => $partnerFilterId > 0 ? $partnerFilterId : null,
    'date_from' => $dateFrom !== '' ? $dateFrom : null,
    'date_to' => $dateTo !== '' ? $dateTo : null,
    'q' => $search !== '' ? $search : null,
], static fn($v) => $v !== null && $v !== '');
$paginationLabel = 'submissions';
$paginationAriaLabel = 'Submission list pages';
$paginationUrl = fn (int $p) => rs_list_url(
    $tab,
    $formId,
    $partnerFilterId,
    $dateFrom,
    $dateTo,
    $search,
    $p,
    $pagination['per_page']
);

$csrf = Auth::csrfToken();
$pageTitle = 'Submissions';
$activeNav = 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="admin-header">
  <h1><?= e($pageTitle) ?></h1>
</div>

<div class="rs-page">
  <div class="admin-card rs-filters-card">
    <form method="get" action="/admin/reviewer-submissions" class="rs-filter-form" id="rs-filter-form">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <?php if ($pagination['per_page'] !== pagination_default_per_page()): ?>
        <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
      <?php endif; ?>

      <div class="admin-field rs-filter-field">
        <label for="rs-form">Form</label>
        <select name="form_id" id="rs-form" onchange="this.form.submit()">
          <option value="">All forms</option>
          <?php foreach ($allForms as $form): ?>
            <option value="<?= (int) $form['id'] ?>" <?= $formId === (int) $form['id'] ? 'selected' : '' ?>>
              <?= e((string) $form['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($showPartnerFilter): ?>
        <div class="admin-field rs-filter-field">
          <label for="rs-partner">Reference</label>
          <select name="partner_id" id="rs-partner" onchange="this.form.submit()">
            <option value="">All references</option>
            <?php foreach ($partners as $partner):
              $label = trim((string) ($partner['name'] ?? ''));
              $code = trim((string) ($partner['reference_code'] ?? ''));
              if ($code !== '') {
                $label = $label !== '' ? $label . ' (' . $code . ')' : $code;
              }
            ?>
              <option value="<?= (int) $partner['id'] ?>" <?= $partnerFilterId === (int) $partner['id'] ? 'selected' : '' ?>>
                <?= e($label !== '' ? $label : 'Partner #' . (int) $partner['id']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="admin-field rs-filter-field">
        <label for="rs-date-from">From date</label>
        <input type="date" name="date_from" id="rs-date-from" value="<?= e($dateFrom) ?>">
      </div>

      <div class="admin-field rs-filter-field">
        <label for="rs-date-to">To date</label>
        <input type="date" name="date_to" id="rs-date-to" value="<?= e($dateTo) ?>">
      </div>

      <div class="admin-field rs-filter-field rs-filter-field--search">
        <label for="rs-search">Search</label>
        <input type="search" name="q" id="rs-search" value="<?= e($search) ?>"
          placeholder="Name, email, CIN, form, or #ID…">
      </div>

      <div class="rs-filter-actions">
        <button type="submit" class="admin-btn admin-btn-secondary">Apply</button>
        <?php if ($hasActiveFilters): ?>
          <a href="<?= e(rs_list_url($tab, 0, 0, '', '', '', 1, $pagination['per_page'])) ?>" class="admin-btn admin-btn-secondary">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <nav class="admin-tabs rs-tabs" aria-label="Filter by status">
    <a href="<?= e(rs_list_url('all', $formId, $partnerFilterId, $dateFrom, $dateTo, $search, 1, $pagination['per_page'])) ?>"
      class="admin-tab <?= $tab === 'all' ? 'is-active' : '' ?>">
      All <span class="admin-tab-count"><?= number_format($statusCounts['all']) ?></span>
    </a>
    <a href="<?= e(rs_list_url('pending', $formId, $partnerFilterId, $dateFrom, $dateTo, $search, 1, $pagination['per_page'])) ?>"
      class="admin-tab <?= $tab === 'pending' ? 'is-active' : '' ?>">
      Pending <span class="admin-tab-count"><?= number_format($statusCounts['pending']) ?></span>
    </a>
    <a href="<?= e(rs_list_url('complete', $formId, $partnerFilterId, $dateFrom, $dateTo, $search, 1, $pagination['per_page'])) ?>"
      class="admin-tab <?= $tab === 'complete' ? 'is-active' : '' ?>">
      Complete <span class="admin-tab-count"><?= number_format($statusCounts['complete']) ?></span>
    </a>
  </nav>

  <div class="admin-card rs-card">
    <?php if (!$submissions): ?>
      <div class="admin-empty-state">
        <span class="admin-empty-state-icon" aria-hidden="true">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        </span>
        <h2 class="admin-empty-state-title">No submissions found</h2>
        <p class="admin-empty-state-text">
          <?php if ($hasActiveFilters || $tab !== 'all'): ?>
            Try clearing filters or switching to the All tab.
          <?php else: ?>
            Responses will appear here when clients submit forms.
          <?php endif; ?>
        </p>
        <?php if ($hasActiveFilters || $tab !== 'all'): ?>
          <a href="/admin/reviewer-submissions" class="admin-btn admin-btn-secondary">Clear filters</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
      <div class="admin-table-wrap">
        <table class="admin-table rs-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Form</th>
              <th>Client</th>
              <th>Reference</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($submissions as $sub):
              $sid = (int) $sub['id'];
              $fid = (int) $sub['form_id'];
              $status = (string) ($sub['status'] ?? 'pending');
              $schemaRaw = json_decode((string) ($sub['schema_json'] ?? '{}'), true) ?: [];
              $schema = normalize_form_schema(is_array($schemaRaw) ? $schemaRaw : []);
              $client = extract_client_from_submission($sub, $schema);
              $clientLabel = $client['name'] !== '' ? $client['name'] : '—';
              if ($client['cin'] !== '') {
                $clientLabel .= ($client['name'] !== '' ? ' · ' : '') . $client['cin'];
              }
              $refLabel = $partnerNames[$sid] ?? '—';
            ?>
              <tr class="rs-row">
                <td class="rs-col-date"><?= e(app_format_datetime((string) ($sub['created_at'] ?? ''), false)) ?></td>
                <td>
                  <a href="/admin/submissions?form_id=<?= $fid ?>" class="rs-form-link"><?= e((string) ($sub['form_title'] ?? 'Form')) ?></a>
                </td>
                <td><?= e(mb_strimwidth($clientLabel, 0, 60, '…')) ?></td>
                <td class="rs-col-ref"><?= e(mb_strimwidth($refLabel, 0, 50, '…')) ?></td>
                <td>
                  <span class="submission-status-badge submission-status-badge--<?= e($status) ?>">
                    <?= e(submission_status_label($status)) ?>
                  </span>
                </td>
                <td class="admin-table-actions">
                  <a href="/admin/submission?id=<?= $sid ?>&form_id=<?= $fid ?>" class="admin-btn admin-btn-primary admin-btn-sm">View</a>
                  <?php if ($status === 'pending' && $role !== 'partner'): ?>
                    <form method="post" action="/admin/submission-status" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                      <input type="hidden" name="submission_id" value="<?= $sid ?>">
                      <input type="hidden" name="form_id" value="<?= $fid ?>">
                      <input type="hidden" name="status" value="complete">
                      <input type="hidden" name="redirect" value="<?= e(rs_list_url($tab, $formId, $partnerFilterId, $dateFrom, $dateTo, $search, $pagination['page'], $pagination['per_page'])) ?>">
                      <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Mark complete</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
