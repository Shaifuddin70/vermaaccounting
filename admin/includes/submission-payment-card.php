<?php
/**
 * Sidebar card for each advance payment field on the submission.
 *
 * @var array $schema
 * @var array $data
 * @var array $filesByField
 * @var array<string, array<string, mixed>> $submissionPayments
 */
foreach ($schema['fields'] as $field):
  if (($field['type'] ?? '') !== 'payment') {
      continue;
  }
  $name = (string) $field['name'];
  $id = (string) $field['id'];
  $payValue = trim((string) ($data[$name] ?? ''));
  $payKeys = payment_storage_keys($name);
  $payReference = trim((string) ($data[$payKeys['reference']] ?? ''));
  $payRecord = $submissionPayments[$id] ?? null;
  $payFiles = [];
  foreach ($filesByField as $fileFieldId => $filesForId) {
      if (payment_file_belongs_to_field($field, (string) $fileFieldId)) {
          $payFiles = array_merge($payFiles, $filesForId);
      }
  }
?>
  <section class="admin-card sub-side-card">
    <h2 class="sub-side-title"><?= e((string) $field['label']) ?></h2>
    <?php if ($payValue === ''): ?>
      <p class="sub-side-muted">Not paid through the form.</p>
    <?php else: ?>
      <dl class="sub-side-list">
        <div><dt>Method</dt><dd><?= e(payment_method_label($field, $payValue)) ?></dd></div>
        <?php if (!$payRecord && payment_field_amount($field) > 0): ?>
          <div><dt>Amount due</dt><dd><?= e(invoice_format_money(payment_field_amount($field))) ?></dd></div>
        <?php endif; ?>
        <?php if ($payReference !== ''): ?>
          <div><dt><?= e((string) ($field['referenceLabel'] ?? 'Reference')) ?></dt><dd><?= e($payReference) ?></dd></div>
        <?php endif; ?>
      </dl>
      <?php if ($payFiles):
        $fieldFiles = $payFiles;
        $galleryFieldId = (string) ($payFiles[0]['field_id'] ?? $id);
      ?>
        <div class="sub-side-files">
          <?php include __DIR__ . '/submission-files-gallery.php'; ?>
        </div>
      <?php endif; ?>
      <?php if ($payRecord): include __DIR__ . '/submission-payment-status.php'; endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
