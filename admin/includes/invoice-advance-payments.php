<?php
/**
 * @var list<array<string, mixed>> $advancePayments unapplied form payments (verified + pending)
 * @var list<array<string, mixed>> $appliedAdvancePayments form payments already in this invoice's advance
 * @var list<int>|null $selectedAdvanceIds ticked ids after a failed save, or null on first load
 * @var array<string, mixed>|null $invoice
 * @var float $advanceIncluded sum of ticked payments already folded into the Advance field
 */
if (!$advancePayments && !$appliedAdvancePayments) {
    return;
}
?>
<div class="invoice-advance-payments">
  <input type="hidden" name="advance_payments_listed" value="1">
  <input type="hidden" name="advance_payments_included" id="advance-payments-included"
    value="<?= e(number_format((float) ($advanceIncluded ?? 0), 2, '.', '')) ?>">
  <?php foreach (array_merge($appliedAdvancePayments, $advancePayments) as $listedPayment): ?>
    <input type="hidden" name="listed_submission_payments[]" value="<?= (int) $listedPayment['id'] ?>">
  <?php endforeach; ?>
  <span class="invoice-advance-title">Form advance payments</span>
  <small class="admin-field-hint">Ticked payments are included in the Advance and deducted from the total.</small>
  <ul class="invoice-advance-list">
    <?php foreach ($appliedAdvancePayments as $ap):
      $apAmount = (float) ($ap['applied_amount'] ?? $ap['amount']);
      $apChecked = $selectedAdvanceIds === null || in_array((int) $ap['id'], $selectedAdvanceIds, true);
    ?>
      <li class="invoice-advance-item">
        <input type="checkbox" name="apply_submission_payments[]" value="<?= (int) $ap['id'] ?>"
          id="advance-pay-<?= (int) $ap['id'] ?>" data-advance-pay="<?= e(number_format($apAmount, 2, '.', '')) ?>"
          <?= $apChecked ? 'checked' : '' ?>>
        <label for="advance-pay-<?= (int) $ap['id'] ?>">
          <span class="invoice-advance-line"><strong><?= e(invoice_format_money($apAmount)) ?></strong> · <?= e((string) $ap['method_label']) ?></span>
          <?php if (!empty($ap['reference'])): ?><span class="invoice-advance-line">Ref <?= e((string) $ap['reference']) ?></span><?php endif; ?>
          <small>Included in this invoice · <a href="/admin/submission?id=<?= (int) $ap['submission_id'] ?>&form_id=<?= (int) $ap['form_id'] ?>#submission-payments" target="_blank">submission #<?= (int) $ap['submission_id'] ?></a></small>
        </label>
      </li>
    <?php endforeach; ?>
    <?php foreach ($advancePayments as $ap):
      $apVerified = $ap['status'] === 'verified';
      $apChecked = $apVerified && ($selectedAdvanceIds === null ? !$invoice : in_array((int) $ap['id'], $selectedAdvanceIds, true));
    ?>
      <li class="invoice-advance-item">
        <input type="checkbox" name="apply_submission_payments[]" value="<?= (int) $ap['id'] ?>"
          id="advance-pay-<?= (int) $ap['id'] ?>"
          <?= $apVerified ? 'data-advance-pay="' . e(number_format((float) $ap['amount'], 2, '.', '')) . '"' : '' ?>
          <?= $apChecked ? 'checked' : '' ?> <?= $apVerified ? '' : 'disabled' ?>>
        <label for="advance-pay-<?= (int) $ap['id'] ?>">
          <span class="invoice-advance-line"><strong><?= e(invoice_format_money((float) $ap['amount'])) ?></strong> · <?= e((string) $ap['method_label']) ?></span>
          <?php if (!empty($ap['reference'])): ?><span class="invoice-advance-line">Ref <?= e((string) $ap['reference']) ?></span><?php endif; ?>
          <small>
            <?= $apVerified ? 'Verified' : 'Pending verification — verify it on the submission to apply' ?>
            · <a href="/admin/submission?id=<?= (int) $ap['submission_id'] ?>&form_id=<?= (int) $ap['form_id'] ?>#submission-payments" target="_blank">submission #<?= (int) $ap['submission_id'] ?></a>
          </small>
        </label>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
