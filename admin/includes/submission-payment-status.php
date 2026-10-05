<?php
/** @var array<string, mixed> $payRecord */
$payStatus = (string) $payRecord['status'];
$payActions = match ($payStatus) {
    'pending' => ['verify' => 'Verify payment', 'reject' => 'Reject'],
    'verified' => ['reject' => 'Reject', 'reopen' => 'Mark pending'],
    'rejected' => ['verify' => 'Verify payment', 'reopen' => 'Mark pending'],
    default => [],
};
?>
<div class="pay-record pay-record--<?= e($payStatus) ?>" id="submission-payments">
  <div class="pay-record-head">
    <span class="pay-record-amount"><?= e(invoice_format_money((float) $payRecord['amount'])) ?></span>
    <span class="pay-status pay-status--<?= e($payStatus) ?>"><?= e(submission_payment_status_label($payStatus)) ?></span>
  </div>
  <p class="pay-record-meta">
    Recorded as <?= e(invoice_payment_methods()[(string) ($payRecord['method'] ?? '')] ?? 'Other') ?>
    on <?= e(invoice_format_date((string) $payRecord['payment_date'])) ?>
    <?php if (!empty($payRecord['reviewed_by_name']) && $payStatus !== 'pending'): ?>
      · reviewed by <?= e((string) $payRecord['reviewed_by_name']) ?>
    <?php endif; ?>
  </p>
  <?php if ($payStatus === 'applied' && !empty($payRecord['invoice_id'])): ?>
    <p class="pay-record-meta">
      <?= e(invoice_format_money((float) ($payRecord['applied_amount'] ?? $payRecord['amount']))) ?> applied to
      <?php if (Auth::can('invoices.view')): ?>
        <a href="/admin/invoice-view?id=<?= (int) $payRecord['invoice_id'] ?>#payments">invoice #<?= e((string) ($payRecord['invoice_number'] ?? $payRecord['invoice_id'])) ?></a>
      <?php else: ?>
        invoice #<?= e((string) ($payRecord['invoice_number'] ?? $payRecord['invoice_id'])) ?>
      <?php endif; ?>
    </p>
  <?php elseif ($payStatus === 'pending'): ?>
    <p class="pay-record-meta">Check that the money arrived, then verify it. Verified payments are applied to this client’s next invoice.</p>
  <?php endif; ?>
  <?php if ($payActions && Auth::can('invoices.edit')): ?>
    <div class="pay-record-actions">
      <?php foreach ($payActions as $payAction => $payActionLabel): ?>
        <form method="post" action="/admin/submission-payment" class="inline-form"
          <?= $payAction === 'reject' ? 'onsubmit="return confirm(\'Reject this payment? It will not be applied to any invoice.\');"' : '' ?>>
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="payment_id" value="<?= (int) $payRecord['id'] ?>">
          <input type="hidden" name="submission_id" value="<?= (int) $submissionId ?>">
          <input type="hidden" name="form_id" value="<?= (int) $formId ?>">
          <input type="hidden" name="action" value="<?= e($payAction) ?>">
          <button type="submit" class="admin-btn admin-btn-sm <?= $payAction === 'verify' ? 'admin-btn-primary' : ($payAction === 'reject' ? 'admin-btn-danger' : 'admin-btn-secondary') ?>"><?= e($payActionLabel) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
