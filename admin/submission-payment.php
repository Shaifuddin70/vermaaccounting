<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireCapability('invoices.edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$formId = (int) ($_POST['form_id'] ?? 0);
$submissionId = (int) ($_POST['submission_id'] ?? 0);
$redirect = '/admin/submission?id=' . $submissionId . '&form_id=' . $formId . '#submission-payments';

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: ' . $redirect);
    exit;
}

$formRepo = new FormRepository();
$form = $formRepo->find($formId);
$submission = $form ? $formRepo->findSubmissionForForm($submissionId, $formId) : null;
$paymentRepo = new SubmissionPaymentRepository();
$payment = $paymentId > 0 ? $paymentRepo->find($paymentId) : null;

if (!$form || !$submission || !$payment || (int) $payment['submission_id'] !== $submissionId) {
    $_SESSION['flash_error'] = 'Payment not found.';
    header('Location: ' . $redirect);
    exit;
}
assert_submission_access($form, $submission);

$action = (string) ($_POST['action'] ?? '');
$current = (string) $payment['status'];
$transitions = [
    'verify' => [['pending', 'rejected'], 'verified'],
    'reject' => [['pending', 'verified'], 'rejected'],
    'reopen' => [['verified', 'rejected'], 'pending'],
];

if (!isset($transitions[$action]) || !in_array($current, $transitions[$action][0], true)) {
    $_SESSION['flash_error'] = $current === 'applied'
        ? 'This payment is already applied to an invoice. Untick it under “Form advance payments” when editing the invoice first.'
        : 'That action isn’t available for this payment.';
    header('Location: ' . $redirect);
    exit;
}

$user = Auth::currentUser();
$newStatus = $transitions[$action][1];
$paymentRepo->setStatus($paymentId, $newStatus, $user);
ActivityLog::record('submission.payment_' . $newStatus, 'submission', $submissionId, [
    'payment_id' => $paymentId,
    'amount' => (float) $payment['amount'],
]);

$message = 'Payment of ' . invoice_format_money((float) $payment['amount']) . ' marked '
    . strtolower(submission_payment_status_label($newStatus)) . '.';

if ($newStatus === 'verified') {
    $invoice = (new InvoiceRepository())->openInvoiceForSubmission($submissionId);
    if ($invoice) {
        $result = submission_payments_add_to_invoice_advance(
            (int) $invoice['id'],
            [$paymentRepo->find($paymentId)]
        );
        if ($result['count'] > 0) {
            $message = 'Payment verified and ' . invoice_format_money($result['total'])
                . ' deducted from invoice #' . $invoice['invoice_number'] . ' as an advance.'
                . ($result['marked_paid'] ? ' Invoice is now fully paid.' : '');
        }
    } else {
        $message = 'Payment verified. It will be applied automatically when you create an invoice for this submission or client.';
    }
}

$_SESSION['flash_success'] = $message;
header('Location: ' . $redirect);
exit;
