<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);
$repo = new InvoiceRepository();
$invoice = $id > 0 ? $repo->find($id) : null;

if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: /admin/invoices');
    exit;
}

$items = $repo->itemsForInvoice($id);

try {
    $pdf = invoice_build_pdf($invoice, $items);
} catch (Throwable $e) {
    error_log('Invoice PDF download failed: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not generate the invoice PDF.';
    header('Location: /admin/invoice-view?id=' . $id);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $pdf['filename'] . '"');
header('Content-Length: ' . (string) strlen($pdf['bytes']));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf['bytes'];
exit;
