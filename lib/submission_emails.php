<?php

declare(strict_types=1);

/**
 * Send admin + client emails after a successful form submission.
 * Failures are logged but do not block the submission response.
 */
function send_submission_notification_emails(
    array $form,
    array $schema,
    int $submissionId,
    array $data,
    ?int $taxYear
): void {
    $mailer = Mailer::fromAppConfig();
    if ($mailer === null) {
        return;
    }

    $mail = mail_config();
    $clientInfo = extract_client_from_submission([
        'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
    ], $schema);

    try {
        $adminEmails = normalize_admin_emails($mail['admin_emails'] ?? $mail['admin_email'] ?? '');
        if (!empty($mail['admin_notification']['enabled']) && $adminEmails !== []) {
            $adminSubject = submission_email_replace_tokens(
                (string) ($mail['admin_notification']['subject'] ?? 'New submission: {form_title} (#{submission_id})'),
                $form,
                $submissionId,
                $clientInfo,
                $taxYear
            );
            [$adminHtml, $adminText] = build_submission_admin_email(
                $form,
                $schema,
                $submissionId,
                $data,
                $taxYear,
                $clientInfo
            );
            $replyTo = $clientInfo['email'] !== '' ? $clientInfo['email'] : null;
            foreach ($adminEmails as $adminTo) {
                if (!$mailer->send($adminTo, $adminSubject, $adminHtml, $adminText, $replyTo)) {
                    error_log('Submission email: admin notification failed for submission #' . $submissionId . ' to ' . $adminTo);
                }
            }
        }

        if (!empty($mail['client_confirmation']['enabled']) && $clientInfo['email'] !== '') {
            $clientSubject = submission_email_replace_tokens(
                (string) ($mail['client_confirmation']['subject'] ?? 'We received your submission — {form_title}'),
                $form,
                $submissionId,
                $clientInfo,
                $taxYear
            );
            [$clientHtml, $clientText] = build_submission_client_email(
                $form,
                $schema,
                $submissionId,
                $data,
                $taxYear,
                $clientInfo
            );
            if (!$mailer->send($clientInfo['email'], $clientSubject, $clientHtml, $clientText)) {
                error_log('Submission email: client confirmation failed for submission #' . $submissionId);
            }
        }
    } catch (Throwable $e) {
        error_log('Submission email error for #' . $submissionId . ': ' . $e->getMessage());
    }
}

/** @return array{0: string, 1: string} */
function build_submission_admin_email(
    array $form,
    array $schema,
    int $submissionId,
    array $data,
    ?int $taxYear,
    array $clientInfo
): array {
    $formTitle = (string) ($form['title'] ?? 'Form');
    $adminUrl = app_base_url() . '/admin/submission?id=' . $submissionId . '&form_id=' . (int) $form['id'];
    $rows = submission_email_field_rows($schema, $data);
    $fileCount = submission_email_file_count($schema, $data);

    $details = '';
    foreach ($rows as $row) {
        $details .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#64748b;width:35%;vertical-align:top;">'
            . e($row['label']) . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#0f172a;">'
            . nl2br(e($row['value'])) . '</td>'
            . '</tr>';
    }

    $metaRows = [
        ['Submission ID', '#' . $submissionId],
        ['Form', $formTitle],
    ];
    if ($taxYear !== null && $taxYear > 0) {
        $metaRows[] = ['Tax year', (string) $taxYear];
    }
    if ($clientInfo['name'] !== '') {
        $metaRows[] = ['Client name', $clientInfo['name']];
    }
    if ($clientInfo['sin'] !== '') {
        $metaRows[] = ['SIN', $clientInfo['sin']];
    }
    if ($clientInfo['email'] !== '') {
        $metaRows[] = ['Client email', $clientInfo['email']];
    }
    if ($clientInfo['phone'] !== '') {
        $metaRows[] = ['Client phone', $clientInfo['phone']];
    }
    if ($fileCount > 0) {
        $metaRows[] = ['Files uploaded', (string) $fileCount];
    }

    $metaHtml = '';
    foreach ($metaRows as [$label, $value]) {
        $metaHtml .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#64748b;width:35%;">' . e($label) . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:600;">' . e($value) . '</td>'
            . '</tr>';
    }

    $html = submission_email_layout(
        'New form submission',
        '<p style="margin:0 0 16px;color:#334155;">A new submission was received on <strong>' . e($formTitle) . '</strong>.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">' . $metaHtml . '</table>'
        . ($details !== '' ? '<h3 style="margin:0 0 10px;font-size:15px;color:#1e3a8a;">Submitted answers</h3>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">' . $details . '</table>' : '')
        . submission_email_button('View submission', $adminUrl),
        'You are receiving this because you are configured as the admin notification recipient.'
    );

    $text = "New submission on {$formTitle}\n\n";
    foreach ($metaRows as [$label, $value]) {
        $text .= "{$label}: {$value}\n";
    }
    if ($rows) {
        $text .= "\nSubmitted answers:\n";
        foreach ($rows as $row) {
            $text .= "- {$row['label']}: {$row['value']}\n";
        }
    }
    $text .= "\nView submission: {$adminUrl}\n";

    return [$html, $text];
}

/** @return array{0: string, 1: string} */
function build_submission_client_email(
    array $form,
    array $schema,
    int $submissionId,
    array $data,
    ?int $taxYear,
    array $clientInfo
): array {
    $formTitle = (string) ($form['title'] ?? 'Form');
    $successMessage = trim((string) ($schema['settings']['successMessage'] ?? ''));
    if ($successMessage === '') {
        $successMessage = 'Thank you for your submission. Our team will review it and get back to you if needed.';
    }

    $greetingName = $clientInfo['name'] !== '' ? $clientInfo['name'] : 'there';
    $mail = mail_config();
    $contactEmail = $mail['admin_emails'][0] ?? trim((string) ($mail['admin_email'] ?? 'info@vermaaccounting.ca'));

    $summaryItems = [];
    if ($taxYear !== null && $taxYear > 0) {
        $summaryItems[] = ['Tax year', (string) $taxYear];
    }
    if ($clientInfo['sin'] !== '') {
        $summaryItems[] = ['SIN', $clientInfo['sin']];
    }
    $summaryItems[] = ['Reference', '#' . $submissionId];

    $summaryHtml = '';
    foreach ($summaryItems as [$label, $value]) {
        $summaryHtml .= '<tr>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#64748b;width:40%;">' . e($label) . '</td>'
            . '<td style="padding:8px 12px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:600;">' . e($value) . '</td>'
            . '</tr>';
    }

    $html = submission_email_layout(
        'Submission received',
        '<p style="margin:0 0 16px;color:#334155;">Hi ' . e($greetingName) . ',</p>'
        . '<p style="margin:0 0 16px;color:#334155;">We have received your submission for <strong>' . e($formTitle) . '</strong>.</p>'
        . '<p style="margin:0 0 20px;color:#334155;">' . nl2br(e($successMessage)) . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">' . $summaryHtml . '</table>'
        . '<p style="margin:0;color:#64748b;font-size:14px;">If you have any questions, reply to this email or contact us at '
        . '<a href="mailto:' . e($contactEmail) . '" style="color:#f97316;">' . e($contactEmail) . '</a>.</p>',
        'This is an automated confirmation from Verma Accounting.'
    );

    $text = "Hi {$greetingName},\n\n"
        . "We have received your submission for {$formTitle}.\n\n"
        . "{$successMessage}\n\n";
    foreach ($summaryItems as [$label, $value]) {
        $text .= "{$label}: {$value}\n";
    }
    $text .= "\nQuestions? Contact {$contactEmail}\n";

    return [$html, $text];
}

function submission_email_layout(string $title, string $bodyHtml, string $footerNote): string
{
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f8fafc;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="padding:24px 24px 16px;text-align:center;background:#ffffff;border-bottom:1px solid #e2e8f0;">'
        . brand_logo_email_html()
        . '</td></tr>'
        . '<tr><td style="background:#1e3a8a;padding:14px 24px;text-align:center;">'
        . '<div style="font-size:15px;font-weight:600;color:#ffffff;">' . e($title) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:24px;">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;">'
        . e($footerNote)
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function submission_email_button(string $label, string $url): string
{
    return '<p style="margin:0;">'
        . '<a href="' . e($url) . '" style="display:inline-block;background:#f97316;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">'
        . e($label) . '</a></p>';
}

/** @return list<array{label: string, value: string}> */
function submission_email_field_rows(array $schema, array $data): array
{
    $rows = [];
    foreach ($schema['fields'] ?? [] as $field) {
        $type = (string) ($field['type'] ?? '');
        if (in_array($type, ['heading', 'paragraph', 'file', 'image'], true)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '' || !array_key_exists($name, $data)) {
            continue;
        }
        $raw = $data[$name];
        if ($type === 'checkbox') {
            $value = is_array($raw) ? implode(', ', array_map('strval', $raw)) : trim((string) $raw);
        } elseif ($type === 'partners') {
            $value = format_partner_submission_value($raw);
        } else {
            $value = is_array($raw) ? implode(', ', array_map('strval', $raw)) : trim((string) $raw);
        }
        if ($value === '') {
            continue;
        }
        $reasonWhen = (string) ($field['reasonWhen'] ?? '');
        if ($reasonWhen !== '' && $value === $reasonWhen) {
            $reasonKey = $name . '_reason';
            $reasonVal = trim((string) ($data[$reasonKey] ?? ''));
            if ($reasonVal !== '') {
                $value .= ' — ' . ($field['reasonLabel'] ?? 'Reason') . ': ' . $reasonVal;
            }
        }
        $rows[] = [
            'label' => (string) ($field['label'] ?? $name),
            'value' => $value,
        ];
    }
    return $rows;
}

function submission_email_file_count(array $schema, array $data): int
{
    $count = 0;
    foreach ($schema['fields'] ?? [] as $field) {
        $type = (string) ($field['type'] ?? '');
        if (!in_array($type, ['file', 'image'], true)) {
            continue;
        }
        $name = (string) ($field['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $value = $data[$name] ?? '';
        if (is_array($value)) {
            $count += count(array_filter($value, static fn($v) => trim((string) $v) !== ''));
        } elseif (trim((string) $value) !== '') {
            $count++;
        }
    }
    return $count;
}

function submission_email_replace_tokens(
    string $template,
    array $form,
    int $submissionId,
    array $clientInfo,
    ?int $taxYear
): string {
    $replacements = [
        '{form_title}' => (string) ($form['title'] ?? 'Form'),
        '{submission_id}' => (string) $submissionId,
        '{client_name}' => (string) ($clientInfo['name'] ?? ''),
        '{client_email}' => (string) ($clientInfo['email'] ?? ''),
        '{tax_year}' => ($taxYear !== null && $taxYear > 0) ? (string) $taxYear : '',
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}
