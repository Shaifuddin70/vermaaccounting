<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

$formId = (int) ($_GET['form_id'] ?? 0);
$submissionId = (int) ($_GET['id'] ?? 0);
$editMode = isset($_GET['edit']) && Auth::userRole() === 'admin';

$repo = new FormRepository();
$form = $repo->find($formId);
$submission = $repo->findSubmissionForForm($submissionId, $formId);

if (!$form || !$submission) {
    header('Location: /admin/' . (Auth::userRole() === 'admin' ? 'forms' : 'reviewer-submissions'));
    exit;
}

assert_submission_access($form, $submission);

$schema = $repo->decodeSchema($form);
$data = json_decode($submission['data_json'], true) ?: [];
$files = $repo->filesForSubmission($submissionId);
$filesByField = files_by_field_id($files);
$status = $submission['status'] ?? 'pending';
$csrf = Auth::csrfToken();
$editErrors = $_SESSION['submission_edit_errors'] ?? [];
unset($_SESSION['submission_edit_errors']);

$pageTitle = 'Submission #' . $submissionId;
$activeNav = Auth::userRole() === 'admin' ? 'forms' : 'submissions';
require __DIR__ . '/includes/layout-start.php';
?>
<div class="admin-header">
  <h1>Submission #<?= $submissionId ?></h1>
  <div class="admin-header-actions">
    <?php if (!$editMode && Auth::userRole() === 'admin'): ?>
      <a href="/admin/submission?id=<?= $submissionId ?>&form_id=<?= $formId ?>&edit=1" class="admin-btn admin-btn-primary">Edit</a>
    <?php else: ?>
      <a href="/admin/submission?id=<?= $submissionId ?>&form_id=<?= $formId ?>" class="admin-btn admin-btn-secondary">Cancel</a>
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

<div class="submission-meta admin-card">
  <div class="submission-meta-grid">
    <div>
      <span class="submission-meta-label">Form</span>
      <strong><?= e($form['title']) ?></strong>
    </div>
    <div>
      <span class="submission-meta-label">Status</span>
      <span class="submission-status-badge submission-status-badge--<?= e($status) ?>">
        <?= e(submission_status_label($status)) ?>
      </span>
    </div>
    <?php if (form_tax_year_enabled($schema) && submission_tax_year_label($submission) !== ''): ?>
      <div>
        <span class="submission-meta-label">Tax year</span>
        <strong><?= e(submission_tax_year_label($submission)) ?></strong>
      </div>
    <?php endif; ?>
    <div>
      <span class="submission-meta-label">Submitted</span>
      <?= e($submission['created_at']) ?>
    </div>
    <?php if (!empty($submission['updated_at'])): ?>
      <div>
        <span class="submission-meta-label">Last updated</span>
        <?= e($submission['updated_at']) ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="submission-meta-actions">
    <?php if ($status === 'pending'): ?>
      <form method="post" action="/admin/submission-status" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
        <input type="hidden" name="form_id" value="<?= $formId ?>">
        <input type="hidden" name="status" value="complete">
        <input type="hidden" name="redirect_view" value="1">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Mark complete</button>
      </form>
    <?php else: ?>
      <form method="post" action="/admin/submission-status" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
        <input type="hidden" name="form_id" value="<?= $formId ?>">
        <input type="hidden" name="status" value="pending">
        <input type="hidden" name="redirect_view" value="1">
        <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm">Mark pending</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card submission-detail-card">
  <?php if ($editMode): ?>
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
  <?php else: ?>
    <?php require __DIR__ . '/includes/submission-fields-view.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
