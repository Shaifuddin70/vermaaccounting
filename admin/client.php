<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('clients.view');

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
assert_client_access($client);
$partnerId = partner_user_id();

$page = pagination_page_from_request();
$perPage = pagination_per_page_from_request();
$yearCounts = $clientRepo->submissionYearCountsForClient($clientId, $partnerId);
$submissionTotal = $clientRepo->countSubmissionsForClient($clientId, $yearFilter, $partnerId);
$pagination = pagination_meta($submissionTotal, $page, $perPage);
$submissions = $clientRepo->submissionsForClient(
    $clientId,
    $yearFilter,
    $pagination['per_page'],
    $pagination['offset'],
    $partnerId
);

$pageTitle = $client['name'];
$activeNav = 'clients';
$csrf = Auth::csrfToken();
$deleteConfirm = 'Delete “' . $client['name'] . '”? Linked form submissions stay in the system, but this client record will be removed. This cannot be undone.';

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

function client_profile_date(?string $value, bool $withTime = false): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format($withTime ? 'M j, Y · g:i A' : 'M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

$meta = client_notes_meta($client['notes'] ?? '');
$statusTone = client_status_tone($meta['status']);
$source = (string) ($client['source'] ?? 'submission');
$email = trim((string) ($client['email'] ?? ''));
$phone = trim((string) ($client['phone'] ?? ''));
$address = trim((string) ($client['address'] ?? ''));
$sin = trim((string) ($client['sin'] ?? ''));
$company = trim((string) ($client['company'] ?? ''));
$locality = client_address_locality($address);
$age = client_age($client['date_of_birth'] ?? null);
$dobLabel = client_profile_date($client['date_of_birth'] ?? null);
$isUnsubscribed = trim((string) ($client['email_unsubscribed_at'] ?? '')) !== '';
$submissionCount = array_sum($yearCounts);
$telHref = preg_replace('/[^\d+]/', '', $phone) ?? '';
$isActive = (int) ($client['is_active'] ?? 1) === 1;

require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Client profile</h1>
  <div class="admin-header-actions">
    <?php if (Auth::can('clients.edit')): ?>
    <a href="/admin/client-edit?id=<?= (int) $client['id'] ?>" class="admin-btn">Edit</a>
    <form method="post" action="/admin/client-action" class="inline-form">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
      <input type="hidden" name="action" value="set_active">
      <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
      <button type="submit" class="admin-btn admin-btn-secondary"><?= $isActive ? 'Mark inactive' : 'Mark active' ?></button>
    </form>
    <?php endif; ?>
    <?php if (Auth::can('clients.delete')): ?>
    <form method="post" action="/admin/client-action" class="inline-form"
      onsubmit="return confirm(<?= e(json_encode($deleteConfirm)) ?>);">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="admin-btn admin-btn-danger">Delete</button>
    </form>
    <?php endif; ?>
    <a href="/admin/clients" class="admin-btn admin-btn-secondary">← All clients</a>
  </div>
</div>

<section class="admin-card client-hero<?= $isActive ? '' : ' client-hero--inactive' ?>">
  <div class="client-hero-main">
    <span class="client-avatar client-avatar--lg" aria-hidden="true"><?= e(client_initials((string) $client['name'])) ?></span>
    <div class="client-hero-text">
      <h2 class="client-hero-name"><?= e($client['name']) ?></h2>
      <div class="client-hero-chips">
        <span class="client-id-badge">ID <?= (int) $client['id'] ?></span>
        <span class="client-active-badge client-active-badge--<?= $isActive ? 'on' : 'off' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
        <?php if ($meta['status'] !== ''): ?>
          <span class="client-status-badge client-status-badge--<?= e($statusTone) ?>"><?= e($meta['status']) ?></span>
        <?php endif; ?>
        <span class="clients-source-badge clients-source-badge--<?= e($source) ?>"><?= e(client_source_label($source)) ?></span>
        <?php if ($isUnsubscribed): ?>
          <span class="client-status-badge client-status-badge--muted">Unsubscribed from emails</span>
        <?php endif; ?>
      </div>
      <?php
        $heroFacts = array_filter([
            $age !== null ? $age . ' years old' : '',
            $locality,
            $company,
        ], static fn (string $v): bool => $v !== '');
      ?>
      <?php if ($heroFacts): ?>
        <p class="client-hero-facts"><?= e(implode(' · ', $heroFacts)) ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="client-detail-grid">
  <section class="admin-card client-detail-card">
    <h2 class="client-detail-title">Personal details</h2>
    <dl class="client-detail-list">
      <div>
        <dt>Full name</dt>
        <dd><?= e($client['name']) ?></dd>
      </div>
      <div>
        <dt>Date of birth</dt>
        <dd>
          <?php if ($dobLabel !== ''): ?>
            <?= e($dobLabel) ?><?php if ($age !== null): ?> <span class="client-detail-muted">(<?= $age ?> yrs)</span><?php endif; ?>
          <?php else: ?>
            <span class="client-detail-muted">Not provided</span>
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt>SIN</dt>
        <dd>
          <?php if ($sin !== ''): ?>
            <span class="client-sin" data-sin="<?= e($sin) ?>" data-masked="<?= e(client_mask_sin($sin)) ?>"><?= e(client_mask_sin($sin)) ?></span>
            <button type="button" class="client-sin-toggle" aria-pressed="false">Show</button>
          <?php else: ?>
            <span class="client-detail-muted">Not provided</span>
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt>Company</dt>
        <dd><?= $company !== '' ? e($company) : '<span class="client-detail-muted">—</span>' ?></dd>
      </div>
    </dl>
  </section>

  <section class="admin-card client-detail-card">
    <h2 class="client-detail-title">Contact</h2>
    <dl class="client-detail-list">
      <div>
        <dt>Email</dt>
        <dd>
          <?php if ($email !== ''): ?>
            <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
          <?php else: ?>
            <span class="client-detail-muted">Not provided</span>
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt>Phone</dt>
        <dd>
          <?php if ($phone !== ''): ?>
            <a href="tel:<?= e($telHref) ?>"><?= e($phone) ?></a>
          <?php else: ?>
            <span class="client-detail-muted">Not provided</span>
          <?php endif; ?>
        </dd>
      </div>
      <?php if ($meta['other_phone'] !== ''): ?>
        <div>
          <dt>Other phone</dt>
          <dd><?= e($meta['other_phone']) ?></dd>
        </div>
      <?php endif; ?>
      <div>
        <dt>Address</dt>
        <dd>
          <?php if ($address !== ''): ?>
            <?= e($address) ?>
            <a class="client-detail-link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($address)) ?>" target="_blank" rel="noopener">Open map</a>
          <?php else: ?>
            <span class="client-detail-muted">Not provided</span>
          <?php endif; ?>
        </dd>
      </div>
    </dl>
  </section>

  <section class="admin-card client-detail-card">
    <h2 class="client-detail-title">Account &amp; filing</h2>
    <dl class="client-detail-list">
      <div>
        <dt>Filing status</dt>
        <dd>
          <?php if ($meta['status'] !== ''): ?>
            <span class="client-status-badge client-status-badge--<?= e($statusTone) ?>"><?= e($meta['status']) ?></span>
          <?php else: ?>
            <span class="client-detail-muted">—</span>
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt>Last activity</dt>
        <dd><?= $meta['last_activity'] !== '' ? e(client_profile_date($meta['last_activity'])) : '<span class="client-detail-muted">—</span>' ?></dd>
      </div>
      <div>
        <dt>Submissions</dt>
        <dd><?= (int) $submissionCount ?></dd>
      </div>
      <div>
        <dt>Email marketing</dt>
        <dd><?= $isUnsubscribed ? 'Unsubscribed' : ($email !== '' ? 'Subscribed' : '<span class="client-detail-muted">No email</span>') ?></dd>
      </div>
      <div>
        <dt>Added</dt>
        <dd><?= e(client_profile_date($client['created_at'] ?? null)) ?> <span class="client-detail-muted"><?= e(match ($source) {
          'import' => 'via CSV import',
          'manual' => 'added manually',
          default => 'via form submission',
        }) ?></span></dd>
      </div>
      <div>
        <dt>Last updated</dt>
        <dd><?= e(client_profile_date($client['updated_at'] ?? null, true)) ?></dd>
      </div>
    </dl>
    <p class="client-detail-hint">Share client ID <strong><?= (int) $client['id'] ?></strong> for document uploads. Clients can also use their email on file.</p>
  </section>
</div>

<?php if ($meta['notes'] !== ''): ?>
  <section class="admin-card client-detail-card client-notes-card">
    <h2 class="client-detail-title">Notes</h2>
    <p class="client-notes-text"><?= e($meta['notes']) ?></p>
  </section>
<?php endif; ?>

<h2 class="client-section-heading">Submissions</h2>

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
    <div class="admin-table-scroll">
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
    </div>
    <?php $paginationShow = 'nav'; require __DIR__ . '/includes/pagination.php'; ?>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.client-sin-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var sin = btn.previousElementSibling;
    if (!sin) return;
    var show = btn.getAttribute('aria-pressed') !== 'true';
    sin.textContent = show ? sin.dataset.sin : sin.dataset.masked;
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.textContent = show ? 'Hide' : 'Show';
  });
});
</script>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
