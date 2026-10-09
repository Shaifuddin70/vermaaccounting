<?php

declare(strict_types=1);

/**
 * Resolve the best recipient email for an invoice.
 */
function invoice_recipient_email(array $invoice): string
{
    $billEmail = trim((string) ($invoice['bill_to_email'] ?? ''));
    if ($billEmail !== '' && filter_var($billEmail, FILTER_VALIDATE_EMAIL)) {
        return strtolower($billEmail);
    }

    $clientEmail = trim((string) ($invoice['client_email'] ?? ''));
    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        return strtolower($clientEmail);
    }
    return '';
}

/**
 * Email templates filled in for one invoice, ready to edit in the send form.
 *
 * @return array{subject: string, body: string, templates: list<array{id: string, name: string, subject: string, body: string, is_default: bool}>}
 */
function invoice_email_draft(array $invoice): array
{
    $templates = [];
    $default = null;
    foreach (invoice_templates()['emails'] as $template) {
        $filled = [
            'id' => (string) $template['id'],
            'name' => (string) $template['name'],
            'subject' => invoice_template_fill((string) $template['subject'], $invoice),
            'body' => invoice_template_fill((string) $template['body'], $invoice),
            'is_default' => !empty($template['is_default']),
        ];
        $templates[] = $filled;
        if ($filled['is_default'] && $default === null) {
            $default = $filled;
        }
    }
    $default ??= $templates[0] ?? null;
    $number = (string) ($invoice['invoice_number'] ?? '');
    return [
        'subject' => ($default['subject'] ?? '') !== '' ? $default['subject'] : 'Invoice #' . $number . ' from Verma Accounting',
        'body' => (string) ($default['body'] ?? ''),
        'templates' => $templates,
    ];
}

/**
 * @return array{ok: bool, error?: string, to?: string}
 */
function invoice_send_to_client(array $invoice, array $items, string $toEmail, ?string $message = null, ?string $subject = null): array
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
    $currency = (string) ($invoice['currency'] ?? 'CAD');
    $amountDue = invoice_format_money(invoice_amount_due($invoice));
    $due = invoice_format_date((string) ($invoice['due_date'] ?? ''));

    $draft = invoice_email_draft($invoice);
    $message = trim(str_replace("\r\n", "\n", (string) ($message ?? '')));
    $body = invoice_template_fill($message !== '' ? $message : $draft['body'], $invoice);
    $subject = trim((string) ($subject ?? ''));
    $subject = invoice_template_fill($subject !== '' ? $subject : $draft['subject'], $invoice);

    $bodyHtml = '';
    foreach (preg_split('/\n{2,}/', $body) ?: [] as $paragraph) {
        if (trim($paragraph) !== '') {
            $bodyHtml .= '<p style="margin:0 0 16px;color:#334155;">' . nl2br(e(trim($paragraph))) . '</p>';
        }
    }
    $bodyHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;width:40%;">Amount due</td>'
        . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#1e3a8a;font-weight:700;text-align:right;">'
        . e($amountDue) . ' ' . e($currency) . '</td></tr>'
        . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Payment due</td>'
        . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#334155;text-align:right;">'
        . e($due) . '</td></tr>'
        . '</table>';

    $html = submission_email_layout(
        'Invoice #' . $number,
        $bodyHtml,
        'This invoice was sent by Verma Accounting & Financial Services.'
    );

    $text = $body . "\n\n"
        . "Amount due: {$amountDue} {$currency}\n"
        . "Payment due: {$due}\n";

    $ok = $mailer->sendWithAttachments(
        $toEmail,
        $subject,
        $html,
        [[
            'filename' => $pdf['filename'],
            'content' => $pdf['bytes'],
            'mime' => $pdf['mime'],
        ]],
        $text,
        null,
        [
            'kind' => 'invoice',
            'enabled' => false,
            'ref_type' => 'invoice',
            'ref_id' => (int) ($invoice['id'] ?? 0),
            'client_id' => !empty($invoice['client_id']) ? (int) $invoice['client_id'] : null,
        ]
    );

    if (!$ok) {
        return [
            'ok' => false,
            'error' => $mailer->getLastError() ?: 'Failed to send invoice email.',
        ];
    }

    return ['ok' => true, 'to' => $toEmail];
}
