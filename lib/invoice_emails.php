<?php

declare(strict_types=1);

/**
 * Resolve the best recipient email for an invoice.
 */
function invoice_recipient_email(array $invoice): string
{
    $clientEmail = trim((string) ($invoice['client_email'] ?? ''));
    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        return strtolower($clientEmail);
    }
    return '';
}

/**
 * @return array{ok: bool, error?: string, to?: string}
 */
function invoice_send_to_client(array $invoice, array $items, string $toEmail, ?string $message = null): array
{
    $toEmail = strtolower(trim($toEmail));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid client email address.'];
    }

    $mailer = Mailer::fromAppConfig();
    if ($mailer === null) {
        return ['ok' => false, 'error' => 'Mail is not available. Check Email settings or try again from the live site.'];
    }

    try {
        $pdf = invoice_build_pdf($invoice, $items);
    } catch (Throwable $e) {
        error_log('Invoice PDF build failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not generate the invoice PDF.'];
    }

    $number = (string) ($invoice['invoice_number'] ?? '');
    $company = invoice_company_settings();
    $currency = (string) ($invoice['currency'] ?? 'CAD');
    $total = invoice_format_money($invoice['total'] ?? 0);
    $due = invoice_format_date((string) ($invoice['due_date'] ?? ''));
    $billName = trim((string) ($invoice['bill_to_name'] ?? ''));
    if ($billName === '') {
        $billName = trim((string) ($invoice['bill_to_company'] ?? ''));
    }
    if ($billName === '') {
        $billName = 'there';
    }

    $extra = trim((string) ($message ?? ''));
    $paymentEmail = $company['payment_email'] ?? 'info@vermaaccounting.ca';

    $bodyHtml = '<p style="margin:0 0 16px;color:#334155;">Hi ' . e($billName) . ',</p>'
        . '<p style="margin:0 0 16px;color:#334155;">Please find invoice <strong>#'
        . e($number) . '</strong> attached as a PDF.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:40%;">Amount due</td>'
        . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#1e3a8a;font-weight:700;text-align:right;">'
        . e($total) . ' ' . e($currency) . '</td></tr>'
        . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Payment due</td>'
        . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#334155;text-align:right;">'
        . e($due) . '</td></tr>'
        . '</table>';

    if ($extra !== '') {
        $bodyHtml .= '<p style="margin:0 0 16px;color:#334155;">' . nl2br(e($extra)) . '</p>';
    }

    $bodyHtml .= '<p style="margin:0;color:#64748b;font-size:14px;">Please make payment to '
        . '<a href="mailto:' . e($paymentEmail) . '" style="color:#f97316;">' . e($paymentEmail) . '</a>.</p>';

    $html = submission_email_layout(
        'Invoice #' . $number,
        $bodyHtml,
        'This invoice was sent by Verma Accounting & Financial Services.'
    );

    $text = "Hi {$billName},\n\n"
        . "Please find invoice #{$number} attached as a PDF.\n\n"
        . "Amount due: {$total} {$currency}\n"
        . "Payment due: {$due}\n\n";
    if ($extra !== '') {
        $text .= $extra . "\n\n";
    }
    $text .= "Please make payment to {$paymentEmail}.\n";

    $ok = $mailer->sendWithAttachments(
        $toEmail,
        'Invoice #' . $number . ' from Verma Accounting',
        $html,
        [[
            'filename' => $pdf['filename'],
            'content' => $pdf['bytes'],
            'mime' => $pdf['mime'],
        ]],
        $text
    );

    if (!$ok) {
        return [
            'ok' => false,
            'error' => $mailer->getLastError() ?: 'Failed to send invoice email.',
        ];
    }

    return ['ok' => true, 'to' => $toEmail];
}
