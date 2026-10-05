<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$formId = (int) ($_GET['form_id'] ?? 0);
$submissionId = (int) ($_GET['id'] ?? 0);
$editMode = isset($_GET['edit']) && Auth::can('submissions.edit');

$repo = new FormRepository();
$form = $repo->find($formId);
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$form || !$submission) {
    header('Location: /admin/' . (Auth::can('forms.view') ? 'forms' : 'reviewer-submissions'));
    exit;
}

assert_submission_access($form, $submission);

$schema = $repo->decodeSchema($form);
$data = json_decode($submission['data_json'], true) ?: [];
$files = $repo->filesForSubmission($submissionId);
$filesByField = files_by_field_id($files);
$submissionPayments = (new SubmissionPaymentRepository())->forSubmission($submissionId);
$linkedClient = (new ClientRepository())->findBySubmissionId($submissionId);
$sourceInvoices = Auth::can('invoices.view') ? (new InvoiceRepository())->forSourceSubmission($submissionId) : [];
$status = $submission['status'] ?? 'pending';
$csrf = Auth::csrfToken();
$editErrors = $_SESSION['submission_edit_errors'] ?? [];
unset($_SESSION['submission_edit_errors']);

$pageTitle = 'Submission #' . $submissionId;
$activeNav = Auth::can('forms.view') ? 'forms' : 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Submission #<?= $submissionId ?></h1>
  <div class="admin-header-actions">
    <?php if ($editMode): ?>
      <a href="/admin/submission?id=<?= $submissionId ?>&form_id=<?= $formId ?>" class="admin-btn admin-btn-secondary">Cancel</a>
    <?php else: ?>
      <?php if (Auth::can('submissions.edit')): ?>
      <a href="/admin/submission?id=<?= $submissionId ?>&form_id=<?= $formId ?>&edit=1" class="admin-btn admin-btn-primary">Edit</a>
      <?php endif; ?>
      <?php if (Auth::can('submissions.delete')): ?>
      <form method="post" action="/admin/submission-delete" class="inline-form"
        onsubmit="return confirm('Delete submission #<?= $submissionId ?>? This cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
        <input type="hidden" name="form_id" value="<?= $formId ?>">
        <input type="hidden" name="redirect" value="/admin/submissions?form_id=<?= $formId ?>">
        <button type="submit" class="admin-btn admin-btn-danger">Delete</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    <a href="/admin/submissions?form_id=<?= $formId ?>" class="admin-btn admin-btn-secondary">← All responses</a>
  </div>
</div>

<?php if ($editErrors): ?>
  <div class="admin-alert admin-alert-error">
    <ul style="margin:0;padding-left:1.25rem;">
      <?php foreach ($editErrors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($editMode): ?>
<div class="admin-card submission-detail-card">
  <form method="post" action="/admin/submission-save" enctype="multipart/form-data" class="submission-edit-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <?php require __DIR__ . '/includes/submission-fields-edit.php'; ?>
    <div class="submission-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary">Save changes</button>
      <a href="/admin/submission?id=<?= $submissionId ?>&form_id=<?= $formId ?>" class="admin-btn admin-btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php else: ?>
<div class="sub-layout">
  <aside class="sub-side">
    <section class="admin-card sub-side-card">
      <div class="sub-side-status">
        <span class="submission-status-badge submission-status-badge--<?= e($status) ?>"><?= e(submission_status_label($status)) ?></span>
        <?php if (Auth::can('submissions.complete')): ?>
          <form method="post" action="/admin/submission-status" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
            <input type="hidden" name="form_id" value="<?= $formId ?>">
            <input type="hidden" name="status" value="<?= $status === 'pending' ? 'complete' : 'pending' ?>">
            <input type="hidden" name="redirect_view" value="1">
            <button type="submit" class="admin-btn admin-btn-sm <?= $status === 'pending' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">
              <?= $status === 'pending' ? 'Mark complete' : 'Mark pending' ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($linkedClient): ?>
        <div class="sub-side-client">
          <?php if (Auth::can('clients.view')): ?>
            <a href="/admin/client?id=<?= (int) $linkedClient['id'] ?>" class="sub-side-client-name"><?= e((string) $linkedClient['name']) ?></a>
          <?php else: ?>
            <strong class="sub-side-client-name"><?= e((string) $linkedClient['name']) ?></strong>
          <?php endif; ?>
          <?php if (!empty($linkedClient['email'])): ?><span><?= e((string) $linkedClient['email']) ?></span><?php endif; ?>
          <?php if (!empty($linkedClient['phone'])): ?><span><?= e((string) $linkedClient['phone']) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
      <dl class="sub-side-list">
        <div><dt>Form</dt><dd><?= e($form['title']) ?></dd></div>
        <?php if (form_tax_year_enabled($schema) && submission_tax_year_label($submission) !== ''): ?>
          <div><dt>Tax year</dt><dd><?= e(submission_tax_year_label($submission)) ?></dd></div>
        <?php endif; ?>
        <div><dt>Submitted</dt><dd><?= e(app_format_datetime((string) $submission['created_at'], false)) ?></dd></div>
        <?php if (!empty($submission['updated_at'])): ?>
          <div><dt>Updated</dt><dd><?= e(app_format_datetime((string) $submission['updated_at'], false)) ?></dd></div>
        <?php endif; ?>
      </dl>
    </section>

    <?php if (Auth::can('invoices.view') || Auth::can('invoices.create')): ?>
      <section class="admin-card sub-side-card">
        <h2 class="sub-side-title">Invoices</h2>
        <?php if ($sourceInvoices): ?>
          <ul class="sub-side-invoices">
            <?php foreach ($sourceInvoices as $si):
              $siPaid = ($si['status'] ?? '') === 'paid';
            ?>
              <li>
                <a href="/admin/invoice-view?id=<?= (int) $si['id'] ?>">#<?= e((string) $si['invoice_number']) ?></a>
                <span><?= e(invoice_format_money((float) $si['total'])) ?></span>
                <span class="admin-badge <?= $siPaid ? 'admin-badge-paid' : (($si['status'] ?? '') === 'sent' ? 'admin-badge-info' : '') ?>"><?= e(invoice_status_label((string) $si['status'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="sub-side-muted">No invoice yet.</p>
        <?php endif; ?>
        <?php if (Auth::can('invoices.create')): ?>
          <a href="<?= e(invoice_edit_url_from_submission($submissionId, $formId)) ?>" class="admin-btn admin-btn-secondary admin-btn-sm sub-side-btn">Create invoice</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/submission-payment-card.php'; ?>

    <?php if (count($files) > 0): ?>
      <section class="admin-card sub-side-card">
        <h2 class="sub-side-title">Files</h2>
        <p class="sub-side-muted"><?= count($files) ?> file<?= count($files) === 1 ? '' : 's' ?> uploaded</p>
        <form method="post" action="/admin/submission-files-zip">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="form_id" value="<?= $formId ?>">
          <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
          <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm sub-side-btn">Download all files</button>
        </form>
      </section>
    <?php endif; ?>
  </aside>

  <div class="sub-main">
    <?php require __DIR__ . '/includes/submission-fields-view.php'; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
