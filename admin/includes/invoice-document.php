<?php
/** @var array $invoice */
/** @var list<array> $items */
/** @var array $company */
/** @var list<string> $billLines */
/** @var string $currency */
/** @var float $discountPercent */
/** @var float $subtotal */
/** @var float $discountAmount */
/** @var float $total */
/** @var string $discountLabel */

$notesText = trim((string) ($invoice['notes'] ?? ''));
$thankYou = 'Thank you for choosing our service!';
$notesBody = $notesText;
if ($notesText !== '') {
    $notesBody = trim(preg_replace(
        '/(?:\r?\n)?\s*' . preg_quote($thankYou, '/') . '\s*$/iu',
        '',
        $notesText
    ) ?? $notesText);
}
$logoUrl = brand_logo_url();
?>
<article class="invoice-doc" aria-label="Invoice <?= e((string) $invoice['invoice_number']) ?>">
  <header class="invoice-doc-header">
    <div class="invoice-doc-logo-wrap">
      <img
        src="<?= e($logoUrl) ?>"
        alt="Verma Accounting"
        class="invoice-doc-logo"
        width="300"
        height="72">
    </div>
    <div class="invoice-doc-header-right">
      <h1 class="invoice-doc-title">INVOICE</h1>
      <div class="invoice-doc-company">
        <div class="invoice-doc-company-name"><?= e($company['name']) ?></div>
        <?php if ($company['street'] !== ''): ?><div><?= e($company['street']) ?></div><?php endif; ?>
        <?php if ($company['city_line'] !== ''): ?><div><?= e($company['city_line']) ?></div><?php endif; ?>
        <?php if ($company['country'] !== ''): ?><div><?= e($company['country']) ?></div><?php endif; ?>
        <?php if ($company['phone'] !== ''): ?><div><?= e($company['phone']) ?></div><?php endif; ?>
        <?php if ($company['website'] !== ''): ?><div><?= e($company['website']) ?></div><?php endif; ?>
      </div>
    </div>
  </header>

  <div class="invoice-doc-meta-grid">
    <div class="invoice-doc-billto">
      <h2>Bill To</h2>
      <?php if ($billLines === []): ?>
        <p class="invoice-doc-billto-body">—</p>
      <?php else: ?>
        <p class="invoice-doc-billto-body">
          <?php foreach ($billLines as $i => $line): ?>
            <?= $i > 0 ? '<br>' : '' ?><?= e($line) ?>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>
    <div class="invoice-doc-meta">
      <table class="invoice-doc-meta-table">
        <tr>
          <th>Invoice Number:</th>
          <td><?= e((string) $invoice['invoice_number']) ?></td>
        </tr>
        <tr>
          <th>Invoice Date:</th>
          <td><?= e(invoice_format_date((string) ($invoice['invoice_date'] ?? ''))) ?></td>
        </tr>
        <tr>
          <th>Payment Due:</th>
          <td><?= e(invoice_format_date((string) ($invoice['due_date'] ?? ''))) ?></td>
        </tr>
        <tr class="invoice-doc-amount-due-row">
          <th>Amount Due (<?= e($currency) ?>):</th>
          <td><?= e(invoice_format_money($total)) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <table class="invoice-doc-table">
    <thead>
      <tr>
        <th class="col-services">Services</th>
        <th class="col-price">Price</th>
        <th class="col-amount">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td class="col-services">
            <div class="invoice-doc-item-name"><?= e((string) ($item['name'] ?? '')) ?></div>
            <?php if (trim((string) ($item['description'] ?? '')) !== ''): ?>
              <div class="invoice-doc-item-desc"><?= nl2br(e((string) $item['description'])) ?></div>
            <?php endif; ?>
          </td>
          <td class="col-price"><?= e(invoice_format_money($item['unit_price'] ?? 0)) ?></td>
          <td class="col-amount"><?= e(invoice_format_money($item['amount'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="invoice-doc-lower">
    <div class="invoice-doc-notes-col">
      <?php if ($notesBody !== ''): ?>
        <section class="invoice-doc-notes">
          <h2>Notes / Terms</h2>
          <p><?= nl2br(e($notesBody)) ?></p>
        </section>
      <?php endif; ?>
    </div>
    <div class="invoice-doc-totals">
      <table class="invoice-doc-totals-table">
        <tr>
          <th>Subtotal:</th>
          <td><?= e(invoice_format_money($subtotal)) ?></td>
        </tr>
        <?php if ($discountPercent > 0 || $discountAmount > 0): ?>
          <tr>
            <th><?= e($discountLabel) ?>% Discount:</th>
            <td>(<?= e(invoice_format_money($discountAmount)) ?>)</td>
          </tr>
        <?php endif; ?>
        <tr class="invoice-doc-totals-total">
          <th>Total:</th>
          <td><?= e(invoice_format_money($total)) ?></td>
        </tr>
        <tr class="invoice-doc-totals-due">
          <th>Amount Due (<?= e($currency) ?>):</th>
          <td><?= e(invoice_format_money($total)) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <p class="invoice-doc-thanks"><?= e($thankYou) ?></p>
</article>
