<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('invoices.settings');

if (partner_user_id() !== null) {
    $_SESSION['flash_error'] = 'This area is only available to admins.';
    header('Location: /admin/');
    exit;
}

$templates = invoice_templates();
$csrf = Auth::csrfToken();
$pageTitle = 'Invoice templates';
$activeNav = 'invoices';
require __DIR__ . '/includes/layout-start.php';

/**
 * @param 'notes'|'emails' $kind
 * @param array<string, mixed> $tpl
 */
$renderTemplate = static function (string $kind, int $index, array $tpl, bool $isNew) {
    $prefix = $kind . '[' . $index . ']';
    $idBase = $kind . '-' . $index;
    ?>
    <div class="invoice-template-card<?= $isNew ? ' is-new' : '' ?>">
      <input type="hidden" name="<?= $prefix ?>[id]" value="<?= e((string) ($tpl['id'] ?? '')) ?>">
      <div class="invoice-template-card-head">
        <strong><?= $isNew ? 'Add a new template' : e((string) $tpl['name']) ?></strong>
        <div class="invoice-template-card-actions">
          <label class="admin-checkbox-label">
            <input type="radio" name="<?= $kind ?>_default" value="<?= $index ?>" <?= !empty($tpl['is_default']) ? 'checked' : '' ?>>
            <span>Default</span>
          </label>
          <?php if (!$isNew): ?>
            <label class="admin-checkbox-label invoice-template-delete">
              <input type="checkbox" name="<?= $prefix ?>[delete]" value="1">
              <span>Delete</span>
            </label>
          <?php endif; ?>
        </div>
      </div>
      <div class="admin-field">
        <label for="<?= $idBase ?>-name">Template name</label>
        <input type="text" id="<?= $idBase ?>-name" name="<?= $prefix ?>[name]" maxlength="80"
          value="<?= e((string) ($tpl['name'] ?? '')) ?>" placeholder="e.g. Personal tax">
      </div>
      <?php if ($kind === 'emails'): ?>
        <div class="admin-field">
          <label for="<?= $idBase ?>-subject">Subject</label>
          <input type="text" id="<?= $idBase ?>-subject" name="<?= $prefix ?>[subject]" maxlength="200"
            value="<?= e((string) ($tpl['subject'] ?? '')) ?>" placeholder="Invoice #{invoice_number} from Verma Accounting">
        </div>
      <?php endif; ?>
      <div class="admin-field">
        <label for="<?= $idBase ?>-body"><?= $kind === 'emails' ? 'Message' : 'Notes / Terms' ?></label>
        <textarea id="<?= $idBase ?>-body" name="<?= $prefix ?>[body]" rows="<?= $kind === 'emails' ? 7 : 4 ?>"><?= e((string) ($tpl['body'] ?? '')) ?></textarea>
      </div>
    </div>
    <?php
};
?>
<div class="admin-header">
  <h1>Invoice templates</h1>
  <a href="/admin/invoices" class="admin-btn admin-btn-secondary">← Invoices</a>
</div>

<p class="admin-field-hint" style="margin-top:0;">
  The default notes template fills <strong>Notes / Terms</strong> on new invoices. The default email template fills the
  subject and message when you send an invoice. Other templates can be picked from a dropdown in both places.
</p>

<div class="admin-card invoice-templates-help">
  <h2 class="invoice-templates-title">Placeholders</h2>
  <p class="admin-field-hint">Type these anywhere in a template. They are replaced with the invoice’s details.</p>
  <dl class="invoice-templates-placeholders">
    <?php foreach (invoice_template_placeholders() as $tag => $label): ?>
      <div>
        <dt><code><?= e($tag) ?></code></dt>
        <dd><?= e($label) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
</div>

<form method="post" action="/admin/invoice-templates-save" class="invoice-templates-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

  <div class="invoice-templates-columns">
    <section class="admin-card" id="note-templates">
      <h2 class="invoice-templates-title">Notes templates</h2>
      <?php foreach ($templates['notes'] as $i => $tpl) { $renderTemplate('notes', $i, $tpl, false); } ?>
      <?php $renderTemplate('notes', count($templates['notes']), [], true); ?>
    </section>

    <section class="admin-card" id="email-templates">
      <h2 class="invoice-templates-title">Email templates</h2>
      <?php foreach ($templates['emails'] as $i => $tpl) { $renderTemplate('emails', $i, $tpl, false); } ?>
      <?php $renderTemplate('emails', count($templates['emails']), [], true); ?>
    </section>
  </div>

  <div class="admin-form-actions">
    <button type="submit" class="admin-btn admin-btn-primary">Save templates</button>
  </div>
</form>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
