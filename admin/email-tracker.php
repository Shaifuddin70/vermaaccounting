<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$view = trim((string) ($_GET['view'] ?? 'batches'));
$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 0], true)) {
    $days = 30;
}
$kindFilter = trim((string) ($_GET['kind'] ?? 'all'));
$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();

$kinds = email_tracking_distinct_kinds();
$statsAll = email_tracking_overall_stats(null);
$statsPeriod = $days > 0 ? email_tracking_overall_stats($days) : $statsAll;

$pageTitle = 'Email Tracker';
$activeNav = 'email-tracker';

// —— Detail: recipients for one batch ——
if ($view === 'batch') {
    $campaignId = (int) ($_GET['campaign_id'] ?? 0);
    $batchKind = trim((string) ($_GET['kind'] ?? ''));
    $batchDay = trim((string) ($_GET['day'] ?? ''));
    $batchSubject = (string) ($_GET['subject'] ?? '');
    $openedFilter = trim((string) ($_GET['opened'] ?? 'all'));
    $q = trim((string) ($_GET['q'] ?? ''));

    $detailFilters = [
        'campaign_id' => $campaignId > 0 ? $campaignId : null,
        'kind' => $campaignId > 0 ? null : $batchKind,
        'day' => $campaignId > 0 ? null : $batchDay,
        'subject' => $campaignId > 0 ? null : $batchSubject,
        'opened' => $openedFilter,
        'q' => $q,
    ];

    $batchStats = email_tracking_batch_stats($detailFilters);
    $total = email_tracking_batch_sends_count($detailFilters);
    $pagination = pagination_meta($total, $page, $perPage);
    $rows = email_tracking_batch_sends($detailFilters, $pagination['per_page'], $pagination['offset']);

    $backParams = array_filter([
        'days' => $days > 0 ? $days : null,
        'kind' => $kindFilter !== 'all' ? $kindFilter : null,
    ], static fn ($v) => $v !== null && $v !== '');

    $queryParams = array_filter([
        'view' => 'batch',
        'campaign_id' => $campaignId > 0 ? $campaignId : null,
        'kind' => $campaignId > 0 ? null : ($batchKind !== '' ? $batchKind : null),
        'day' => $campaignId > 0 ? null : ($batchDay !== '' ? $batchDay : null),
        'subject' => $campaignId > 0 ? null : $batchSubject,
        'opened' => $openedFilter !== 'all' ? $openedFilter : null,
        'q' => $q !== '' ? $q : null,
        'days' => $days > 0 ? $days : null,
    ], static fn ($v) => $v !== null && $v !== '');

    $paginationPath = '/admin/email-tracker';
    $paginationQuery = $queryParams;
    $paginationUrl = fn (int $p) => pagination_url('/admin/email-tracker', $queryParams, $p, $pagination['per_page']);
    $paginationLabel = 'recipients';
    $paginationAriaLabel = 'Email tracker recipient pages';

    $heading = 'Send details';
    if ($campaignId > 0) {
        $campaign = (new EmailCampaignRepository())->find($campaignId);
        $heading = $campaign
            ? (string) ($campaign['name'] ?? ('Campaign #' . $campaignId))
            : ('Campaign #' . $campaignId);
    } elseif ($batchKind !== '') {
        $heading = email_tracking_kind_label($batchKind);
        if ($batchDay !== '') {
            $heading .= ' · ' . $batchDay;
        }
    }

    require __DIR__ . '/includes/layout-start.php';
    ?>
    <div class="admin-header">
      <h1><?= e($heading) ?></h1>
      <div class="admin-header-actions">
        <a href="<?= e(email_tracker_url($backParams)) ?>" class="admin-btn admin-btn-secondary">← Email Tracker</a>
        <?php if ($campaignId > 0): ?>
          <a href="/admin/campaign-view?id=<?= $campaignId ?>" class="admin-btn admin-btn-secondary">Campaign</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-card" style="margin-bottom:1rem;">
      <div class="campaign-meta-grid">
        <div>
          <span class="submission-meta-label">Sent</span>
          <strong><?= number_format($batchStats['tracked']) ?></strong>
        </div>
        <div>
          <span class="submission-meta-label">Opened</span>
          <strong><?= number_format($batchStats['opened']) ?></strong>
        </div>
        <div>
          <span class="submission-meta-label">Open rate</span>
          <strong><?= e((string) $batchStats['open_rate']) ?>%</strong>
        </div>
      </div>
      <?php if ($batchSubject !== '' && $campaignId < 1): ?>
        <p class="admin-field-hint" style="margin:0.75rem 0 0;"><strong>Subject:</strong> <?= e($batchSubject) ?></p>
      <?php endif; ?>
    </div>

    <div class="admin-card email-tracker-card">
      <div class="email-tracker-card-top">
        <form method="get" action="/admin/email-tracker" class="email-tracker-filters">
          <input type="hidden" name="view" value="batch">
          <?php if ($campaignId > 0): ?>
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
          <?php else: ?>
            <input type="hidden" name="kind" value="<?= e($batchKind) ?>">
            <input type="hidden" name="day" value="<?= e($batchDay) ?>">
            <input type="hidden" name="subject" value="<?= e($batchSubject) ?>">
          <?php endif; ?>
          <?php if ($days > 0): ?>
            <input type="hidden" name="days" value="<?= $days ?>">
          <?php endif; ?>

          <div class="admin-field">
            <label for="tracker-q">Search recipient</label>
            <input type="search" id="tracker-q" name="q" value="<?= e($q) ?>" placeholder="Email or subject…">
          </div>
          <div class="admin-field">
            <label for="tracker-opened">Open status</label>
            <select id="tracker-opened" name="opened">
              <option value="all" <?= $openedFilter === 'all' ? 'selected' : '' ?>>All</option>
              <option value="yes" <?= $openedFilter === 'yes' ? 'selected' : '' ?>>Opened</option>
              <option value="no" <?= $openedFilter === 'no' ? 'selected' : '' ?>>Not opened</option>
            </select>
          </div>
          <div class="admin-field email-tracker-filters-actions">
            <label for="tracker-apply-detail">Action</label>
            <button type="submit" id="tracker-apply-detail" class="admin-btn admin-btn-primary email-tracker-apply-btn">Apply</button>
          </div>
        </form>
        <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
      </div>

      <?php if ($rows === []): ?>
        <p class="admin-field-hint email-tracker-empty">No recipients match these filters.</p>
      <?php else: ?>
        <div class="admin-table-wrap email-tracker-table-wrap">
          <table class="admin-table email-tracker-table email-tracker-table--detail">
            <colgroup>
              <col class="email-tracker-col-recipient">
              <col class="email-tracker-col-sent">
              <col class="email-tracker-col-opened">
              <col class="email-tracker-col-count">
            </colgroup>
            <thead>
              <tr>
                <th>Recipient</th>
                <th>Sent</th>
                <th>Opened</th>
                <th>Opens</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <?php $openedAt = (string) ($row['opened_at'] ?? ''); ?>
                <tr>
                  <td class="email-tracker-cell-main">
                    <strong><?= e((string) ($row['to_email'] ?? '')) ?></strong>
                    <?php if (trim((string) ($row['subject'] ?? '')) !== '' && $campaignId > 0): ?>
                      <div class="admin-field-hint"><?= e((string) $row['subject']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="email-tracker-cell-nowrap"><?= e(campaign_format_datetime((string) ($row['sent_at'] ?? ''))) ?></td>
                  <td>
                    <?php if ($openedAt !== ''): ?>
                      <span class="campaign-status-badge campaign-status-badge--sent">Opened</span>
                      <div class="admin-field-hint"><?= e(campaign_format_datetime($openedAt)) ?></div>
                    <?php else: ?>
                      <span class="admin-field-hint">Not opened</span>
                    <?php endif; ?>
                  </td>
                  <td class="email-tracker-cell-num"><?= number_format((int) ($row['open_count'] ?? 0)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="email-tracker-card-footer">
          <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/includes/layout-end.php';
    exit;
}

// —— Index: aggregated batches ——
$filters = [
    'days' => $days > 0 ? $days : null,
    'kind' => $kindFilter,
];
$total = email_tracking_batches_count($filters);
$pagination = pagination_meta($total, $page, $perPage);
$batches = email_tracking_batches($filters, $pagination['per_page'], $pagination['offset']);

$queryParams = array_filter([
    'days' => $days > 0 ? $days : null,
    'kind' => $kindFilter !== 'all' ? $kindFilter : null,
], static fn ($v) => $v !== null && $v !== '');

$paginationPath = '/admin/email-tracker';
$paginationQuery = $queryParams;
$paginationUrl = fn (int $p) => pagination_url('/admin/email-tracker', $queryParams, $p, $pagination['per_page']);
$paginationLabel = 'send groups';
$paginationAriaLabel = 'Email tracker pages';

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Email Tracker</h1>
  <div class="admin-header-actions">
    <a href="/admin/campaigns" class="admin-btn admin-btn-secondary">Campaigns</a>
  </div>
</div>

<div class="admin-card" style="margin-bottom:1rem;">
  <div class="campaign-meta-grid">
    <div>
      <span class="submission-meta-label"><?= $days > 0 ? 'Last ' . $days . ' days · sent' : 'All time · sent' ?></span>
      <strong><?= number_format($statsPeriod['tracked']) ?></strong>
    </div>
    <div>
      <span class="submission-meta-label"><?= $days > 0 ? 'Last ' . $days . ' days · opened' : 'All time · opened' ?></span>
      <strong><?= number_format($statsPeriod['opened']) ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Open rate</span>
      <strong><?= e((string) $statsPeriod['open_rate']) ?>%</strong>
    </div>
    <div>
      <span class="submission-meta-label">All-time sends</span>
      <strong><?= number_format($statsAll['tracked']) ?></strong>
    </div>
  </div>
</div>

<div class="admin-card email-tracker-card">
  <div class="email-tracker-card-top">
    <form method="get" action="/admin/email-tracker" class="email-tracker-filters">
      <div class="admin-field">
        <label for="tracker-days">Period</label>
        <select id="tracker-days" name="days">
          <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
          <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
          <option value="90" <?= $days === 90 ? 'selected' : '' ?>>Last 90 days</option>
          <option value="0" <?= $days === 0 ? 'selected' : '' ?>>All time</option>
        </select>
      </div>
      <div class="admin-field">
        <label for="tracker-kind">Type</label>
        <select id="tracker-kind" name="kind">
          <option value="all" <?= $kindFilter === 'all' ? 'selected' : '' ?>>All types</option>
          <?php foreach ($kinds as $kind): ?>
            <option value="<?= e($kind) ?>" <?= $kindFilter === $kind ? 'selected' : '' ?>>
              <?= e(email_tracking_kind_label($kind)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-field email-tracker-filters-actions">
        <label for="tracker-apply">Action</label>
        <button type="submit" id="tracker-apply" class="admin-btn admin-btn-primary email-tracker-apply-btn">Apply filters</button>
      </div>
    </form>
    <?php $paginationShow = 'per_page'; require __DIR__ . '/includes/pagination.php'; ?>
  </div>

  <?php if ($batches === []): ?>
    <p class="admin-field-hint email-tracker-empty">No tracked sends in this period. New emails appear here after they are sent.</p>
  <?php else: ?>
    <div class="admin-table-wrap email-tracker-table-wrap">
      <table class="admin-table email-tracker-table">
        <colgroup>
          <col class="email-tracker-col-group">
          <col class="email-tracker-col-type">
          <col class="email-tracker-col-when">
          <col class="email-tracker-col-sent">
          <col class="email-tracker-col-opened">
          <col class="email-tracker-col-rate">
          <col class="email-tracker-col-action">
        </colgroup>
        <thead>
          <tr>
            <th>Send group</th>
            <th>Type</th>
            <th>Last sent</th>
            <th>Sent</th>
            <th>Opened</th>
            <th>Rate</th>
            <th class="email-tracker-th-action">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php
              $campaignId = (int) ($batch['campaign_id'] ?? 0);
              $batchKind = (string) ($batch['kind'] ?? '');
              $sendDay = (string) ($batch['send_day'] ?? '');
              $subject = (string) ($batch['subject'] ?? '');
              $title = trim((string) ($batch['title'] ?? ''));
              if ($title === '') {
                  $title = $subject !== '' ? $subject : email_tracking_kind_label($batchKind);
              }
              $detailParams = $campaignId > 0
                  ? ['view' => 'batch', 'campaign_id' => $campaignId, 'days' => $days > 0 ? $days : null]
                  : [
                      'view' => 'batch',
                      'kind' => $batchKind,
                      'day' => $sendDay,
                      'subject' => $subject,
                      'days' => $days > 0 ? $days : null,
                  ];
            ?>
            <tr>
              <td class="email-tracker-cell-main">
                <strong><?= e($title) ?></strong>
                <?php if ($campaignId > 0 && $subject !== '' && $subject !== $title): ?>
                  <div class="admin-field-hint"><?= e($subject) ?></div>
                <?php elseif ($campaignId < 1 && $sendDay !== ''): ?>
                  <div class="admin-field-hint">Day: <?= e($sendDay) ?></div>
                <?php endif; ?>
              </td>
              <td class="email-tracker-cell-nowrap"><?= e(email_tracking_kind_label($batchKind)) ?></td>
              <td class="email-tracker-cell-nowrap"><?= e(campaign_format_datetime((string) ($batch['last_sent'] ?? ''))) ?></td>
              <td class="email-tracker-cell-num"><?= number_format((int) ($batch['tracked'] ?? 0)) ?></td>
              <td class="email-tracker-cell-num"><?= number_format((int) ($batch['opened'] ?? 0)) ?></td>
              <td class="email-tracker-cell-num"><?= e((string) ($batch['open_rate'] ?? 0)) ?>%</td>
              <td class="email-tracker-cell-action">
                <a href="<?= e(email_tracker_url($detailParams)) ?>" class="admin-btn admin-btn-secondary">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="email-tracker-card-footer">
      <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
