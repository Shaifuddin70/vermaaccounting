<?php

declare(strict_types=1);

function invoice_money(float $amount): float
{
    return round($amount, 2);
}

function invoice_percent(float $percent): float
{
    return round(max(0, min(100, $percent)), 4);
}

function invoice_parse_discount_percent_input(mixed $raw): float
{
    if ($raw === null) {
        return 0.0;
    }
    $text = trim((string) $raw);
    if ($text === '') {
        return 0.0;
    }

    return invoice_percent((float) $text);
}

function invoice_parse_discount_flat_input(mixed $raw): float
{
    if ($raw === null) {
        return 0.0;
    }
    $text = trim((string) $raw);
    if ($text === '') {
        return 0.0;
    }

    return invoice_money(max(0, (float) $text));
}

function invoice_parse_signed_money_input(mixed $raw): float
{
    if ($raw === null) {
        return 0.0;
    }
    $text = trim((string) $raw);
    if ($text === '' || $text === '-' || $text === '.' || $text === '-.') {
        return 0.0;
    }

    return invoice_money((float) $text);
}

function invoice_format_discount_percent_input(float $percent): string
{
    $percent = invoice_percent($percent);
    if (abs($percent) < 0.0000001) {
        return '0';
    }

    return rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.');
}

function invoice_format_discount_flat_input(float $amount): string
{
    $amount = invoice_money(max(0, $amount));
    if (abs($amount) < 0.0000001) {
        return '0';
    }

    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
}

function invoice_format_signed_money_input(float $amount): string
{
    $amount = invoice_money($amount);
    if (abs($amount) < 0.0000001) {
        return '0';
    }

    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0';
}

function invoice_format_money(float|string|null $amount, string $currency = 'CAD'): string
{
    $value = invoice_money((float) ($amount ?? 0));
    $formatted = number_format(abs($value), 2, '.', ',');
    $prefix = $value < 0 ? '-$' : '$';
    return $prefix . $formatted;
}

function invoice_format_date(?string $ymd): string
{
    $ymd = trim((string) $ymd);
    if ($ymd === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if ($dt === false) {
        return $ymd;
    }
    return $dt->format('F j, Y');
}

/**
 * @param list<array<string, mixed>> $items
 * @return array{
 *   subtotal: float,
 *   discount_percent_amount: float,
 *   discount_flat_amount: float,
 *   discount_amount: float,
 *   total: float,
 *   advance_amount: float,
 *   due_adjustment: float,
 *   amount_due: float,
 *   items: list<array<string, mixed>>
 * }
 */
function invoice_calculate_totals(
    array $items,
    float $discountPercent = 0.0,
    float $discountFlat = 0.0,
    float $advanceAmount = 0.0,
    float $dueAdjustment = 0.0
): array {
    $normalized = [];
    $subtotal = 0.0;
    foreach ($items as $item) {
        $qty = max(0.01, (float) ($item['quantity'] ?? 1));
        $price = invoice_money((float) ($item['unit_price'] ?? 0));
        $amount = invoice_money($price * $qty);
        $subtotal += $amount;
        $normalized[] = array_merge($item, [
            'quantity' => $qty,
            'unit_price' => $price,
            'amount' => $amount,
        ]);
    }
    $subtotal = invoice_money($subtotal);
    $breakdown = invoice_discount_breakdown($subtotal, $discountPercent, $discountFlat);
    $total = invoice_money(max(0, $subtotal - $breakdown['total_discount']));
    $advance = invoice_money(max(0, $advanceAmount));
    $adjustment = invoice_money($dueAdjustment);
    $amountDue = invoice_money(max(0, $total - $advance + $adjustment));

    return [
        'subtotal' => $subtotal,
        'discount_percent_amount' => $breakdown['percent_amount'],
        'discount_flat_amount' => $breakdown['flat_amount'],
        'discount_amount' => $breakdown['total_discount'],
        'total' => $total,
        'advance_amount' => $advance,
        'due_adjustment' => $adjustment,
        'amount_due' => $amountDue,
        'items' => $normalized,
    ];
}

/** @return array{percent_amount: float, flat_amount: float, total_discount: float} */
function invoice_discount_breakdown(float $subtotal, float $discountPercent, float $discountFlat): array
{
    $subtotal = invoice_money($subtotal);
    $percentAmount = invoice_money($subtotal * (invoice_percent($discountPercent) / 100));
    $remaining = invoice_money(max(0, $subtotal - $percentAmount));
    $flatAmount = invoice_money(min(invoice_money(max(0, $discountFlat)), $remaining));
    $totalDiscount = invoice_money($percentAmount + $flatAmount);

    return [
        'percent_amount' => $percentAmount,
        'flat_amount' => $flatAmount,
        'total_discount' => $totalDiscount,
    ];
}

/** @return array{percent: float, flat: float, percent_amount: float, flat_amount: float, total_discount: float, percent_label: string, flat_label: string} */
function invoice_discount_state(array $invoice): array
{
    $subtotal = invoice_money((float) ($invoice['subtotal'] ?? 0));
    $percent = invoice_percent((float) ($invoice['discount_percent'] ?? 0));
    $flat = invoice_money(max(0, (float) ($invoice['discount_flat'] ?? 0)));
    $breakdown = invoice_discount_breakdown($subtotal, $percent, $flat);

    return [
        'percent' => $percent,
        'flat' => $flat,
        'percent_amount' => $breakdown['percent_amount'],
        'flat_amount' => $breakdown['flat_amount'],
        'total_discount' => $breakdown['total_discount'],
        'percent_label' => invoice_discount_percent_label($invoice),
        'flat_label' => invoice_discount_flat_label($invoice),
    ];
}

function invoice_sanitize_discount_label(string $label, int $maxLen = 120): string
{
    return mb_substr(trim($label), 0, $maxLen);
}

function invoice_default_percent_discount_label(float $percent): string
{
    $label = rtrim(rtrim(number_format(invoice_percent($percent), 4, '.', ''), '0'), '.');
    if ($label === '') {
        $label = '0';
    }

    return $label . '% Discount';
}

function invoice_default_flat_discount_label(): string
{
    return 'Flat Discount';
}

function invoice_discount_percent_label(array $invoice): string
{
    $custom = invoice_sanitize_discount_label((string) ($invoice['discount_percent_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return invoice_default_percent_discount_label((float) ($invoice['discount_percent'] ?? 0));
}

function invoice_discount_flat_label(array $invoice): string
{
    $custom = invoice_sanitize_discount_label((string) ($invoice['discount_flat_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return invoice_default_flat_discount_label();
}

function invoice_default_advance_label(): string
{
    return 'Advance';
}

function invoice_default_due_adjustment_label(): string
{
    return 'Due adjustment';
}

function invoice_advance_label(array $invoice): string
{
    $custom = invoice_sanitize_discount_label((string) ($invoice['advance_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return invoice_default_advance_label();
}

function invoice_due_adjustment_label(array $invoice): string
{
    $custom = invoice_sanitize_discount_label((string) ($invoice['due_adjustment_label'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    return invoice_default_due_adjustment_label();
}

/**
 * amount_due is the outstanding balance after recorded payments;
 * invoice_due is the amount owed before any payments.
 *
 * A Paid invoice is settled everywhere: amount_due is 0 and any balance without a recorded
 * payment counts as paid (unrecorded_paid). recorded_paid and raw_amount_due ignore status.
 *
 * @return array{advance_amount: float, due_adjustment: float, invoice_due: float, amount_paid: float, recorded_paid: float, unrecorded_paid: float, amount_due: float, raw_amount_due: float, is_settled: bool, advance_label: string, due_adjustment_label: string}
 */
function invoice_due_state(array $invoice): array
{
    $total = invoice_money((float) ($invoice['total'] ?? 0));
    $advance = invoice_money(max(0, (float) ($invoice['advance_amount'] ?? 0)));
    $adjustment = invoice_money((float) ($invoice['due_adjustment'] ?? 0));
    $storedDue = array_key_exists('amount_due', $invoice)
        ? invoice_money((float) $invoice['amount_due'])
        : null;
    $invoiceDue = $storedDue !== null
        ? $storedDue
        : invoice_money(max(0, $total - $advance + $adjustment));
    $paid = invoice_money(max(0, (float) ($invoice['amount_paid'] ?? 0)));
    $rawDue = invoice_money(max(0, $invoiceDue - $paid));
    $settled = strtolower(trim((string) ($invoice['status'] ?? ''))) === 'paid';
    $unrecorded = $settled ? $rawDue : 0.0;

    return [
        'advance_amount' => $advance,
        'due_adjustment' => $adjustment,
        'invoice_due' => $invoiceDue,
        'amount_paid' => invoice_money($paid + $unrecorded),
        'recorded_paid' => $paid,
        'unrecorded_paid' => $unrecorded,
        'amount_due' => $settled ? 0.0 : $rawDue,
        'raw_amount_due' => $rawDue,
        'is_settled' => $settled,
        'advance_label' => invoice_advance_label($invoice),
        'due_adjustment_label' => invoice_due_adjustment_label($invoice),
    ];
}

/** Outstanding balance from the figures alone, ignoring a Paid status. */
function invoice_raw_amount_due(array $invoice): float
{
    return invoice_due_state($invoice)['raw_amount_due'];
}

function invoice_amount_due(array $invoice): float
{
    return invoice_due_state($invoice)['amount_due'];
}

/** Format adjustment for display: credits in parentheses, charges plain. */
function invoice_format_adjustment_money(float $amount): string
{
    $amount = invoice_money($amount);
    if ($amount < 0) {
        return '(' . invoice_format_money(abs($amount)) . ')';
    }
    if ($amount > 0) {
        return invoice_format_money($amount);
    }

    return invoice_format_money(0);
}

/** @return array<string, string> */
function invoice_company_defaults(): array
{
    return [
        'name' => 'Verma Accounting and Financial Services',
        'street' => 'Vennecy Terrace',
        'city_line' => 'Orleans, Ontario K1W0N3',
        'country' => 'Canada',
        'phone' => '6133186478',
        'website' => 'www.vermaaccounting.ca',
        'payment_email' => 'info@vermaaccounting.ca',
    ];
}

/** @return array<string, string> */
function invoice_company_settings(): array
{
    $stored = (new SettingsRepository())->get('invoice_company', []);
    if (!is_array($stored)) {
        $stored = [];
    }
    $defaults = invoice_company_defaults();
    $out = [];
    foreach ($defaults as $key => $default) {
        $value = trim((string) ($stored[$key] ?? $default));
        $out[$key] = $value !== '' ? $value : $default;
    }
    return $out;
}

/** @param array<string, mixed> $data */
function invoice_save_company_settings(array $data): void
{
    $defaults = invoice_company_defaults();
    $clean = [];
    foreach ($defaults as $key => $default) {
        $value = trim((string) ($data[$key] ?? ''));
        $clean[$key] = $value !== '' ? $value : $default;
    }
    (new SettingsRepository())->set('invoice_company', $clean);
}

/** @return array<string, string> */
function invoice_payment_methods(): array
{
    return [
        'e_transfer' => 'Interac e-Transfer',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'debit' => 'Debit card',
        'credit_card' => 'Credit card',
        'bank_transfer' => 'Bank transfer',
        'other' => 'Other',
    ];
}

function invoice_payment_method_label(?string $method): string
{
    $method = (string) $method;
    return invoice_payment_methods()[$method] ?? ($method !== '' ? ucfirst(str_replace('_', ' ', $method)) : '—');
}

/** @return list<string> */
function invoice_status_options(): array
{
    return ['draft', 'approved', 'sent', 'paid'];
}

function invoice_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'approved' => 'Approved',
        'sent' => 'Sent',
        'paid' => 'Paid',
        // Legacy value mapped for display until migration runs.
        'void' => 'Draft',
        default => ucfirst($status),
    };
}

/**
 * Parse line items from the invoice edit form POST.
 *
 * @return list<array<string, mixed>>
 */
function invoice_parse_items_from_post(array $post): array
{
    $items = [];

    // Compact line-item rows (preferred).
    $lineNames = is_array($post['line_name'] ?? null) ? $post['line_name'] : [];
    if ($lineNames !== []) {
        $lineServiceIds = is_array($post['line_service_id'] ?? null) ? $post['line_service_id'] : [];
        $lineDescs = is_array($post['line_description'] ?? null) ? $post['line_description'] : [];
        $linePrices = is_array($post['line_price'] ?? null) ? $post['line_price'] : [];
        $lineQtys = is_array($post['line_qty'] ?? null) ? $post['line_qty'] : [];

        foreach ($lineNames as $idx => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $serviceId = (int) ($lineServiceIds[$idx] ?? 0);
            $items[] = [
                'service_id' => $serviceId > 0 ? $serviceId : null,
                'name' => $name,
                'description' => trim((string) ($lineDescs[$idx] ?? '')),
                'unit_price' => (float) ($linePrices[$idx] ?? 0),
                'quantity' => (float) ($lineQtys[$idx] ?? 1),
            ];
        }
        return $items;
    }

    // Legacy checkbox + custom fields.
    $serviceIds = $post['service_ids'] ?? [];
    if (!is_array($serviceIds)) {
        $serviceIds = [];
    }
    $servicePrices = is_array($post['service_price'] ?? null) ? $post['service_price'] : [];
    $serviceQtys = is_array($post['service_qty'] ?? null) ? $post['service_qty'] : [];
    $serviceDescs = is_array($post['service_description'] ?? null) ? $post['service_description'] : [];
    $serviceNames = is_array($post['service_name'] ?? null) ? $post['service_name'] : [];

    $repo = new InvoiceServiceRepository();
    foreach ($serviceIds as $rawId) {
        $id = (int) $rawId;
        if ($id < 1) {
            continue;
        }
        $service = $repo->find($id);
        if (!$service) {
            continue;
        }
        $name = trim((string) ($serviceNames[$id] ?? $service['name'] ?? ''));
        if ($name === '') {
            $name = (string) $service['name'];
        }
        $desc = trim((string) ($serviceDescs[$id] ?? $service['description'] ?? ''));
        $price = isset($servicePrices[$id])
            ? (float) $servicePrices[$id]
            : (float) ($service['unit_price'] ?? 0);
        $qty = isset($serviceQtys[$id]) ? (float) $serviceQtys[$id] : 1.0;
        $items[] = [
            'service_id' => $id,
            'name' => $name,
            'description' => $desc,
            'unit_price' => $price,
            'quantity' => $qty,
        ];
    }

    $customNames = is_array($post['custom_name'] ?? null) ? $post['custom_name'] : [];
    $customDescs = is_array($post['custom_description'] ?? null) ? $post['custom_description'] : [];
    $customPrices = is_array($post['custom_price'] ?? null) ? $post['custom_price'] : [];
    $customQtys = is_array($post['custom_qty'] ?? null) ? $post['custom_qty'] : [];

    foreach ($customNames as $idx => $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        $items[] = [
            'service_id' => null,
            'name' => $name,
            'description' => trim((string) ($customDescs[$idx] ?? '')),
            'unit_price' => (float) ($customPrices[$idx] ?? 0),
            'quantity' => (float) ($customQtys[$idx] ?? 1),
        ];
    }

    return $items;
}

/** @param array<string, mixed> $invoice */
function invoice_bill_to_lines(array $invoice): array
{
    $lines = [];
    $company = trim((string) ($invoice['bill_to_company'] ?? ''));
    $name = trim((string) ($invoice['bill_to_name'] ?? ''));
    $email = trim((string) ($invoice['bill_to_email'] ?? ''));
    $phone = trim((string) ($invoice['bill_to_phone'] ?? ''));
    $street = trim((string) ($invoice['bill_to_street'] ?? ''));
    $city = trim((string) ($invoice['bill_to_city'] ?? ''));
    $province = trim((string) ($invoice['bill_to_province'] ?? ''));
    $postal = trim((string) ($invoice['bill_to_postal'] ?? ''));
    $country = trim((string) ($invoice['bill_to_country'] ?? ''));

    if ($company !== '') {
        $lines[] = $company;
    }
    if ($name !== '') {
        $lines[] = $name;
    }
    if ($email !== '') {
        $lines[] = $email;
    }
    if ($phone !== '') {
        $lines[] = $phone;
    }
    if ($street !== '') {
        $lines[] = $street;
    }

    $cityLine = trim(implode(', ', array_filter([$city, $province])));
    if ($postal !== '') {
        $cityLine = trim($cityLine . ($cityLine !== '' ? ' ' : '') . $postal);
    }
    if ($cityLine !== '') {
        $lines[] = $cityLine;
    }
    if ($country !== '') {
        $lines[] = $country;
    }

    return $lines;
}

/** Notes from the default notes template, with company placeholders filled in. */
function invoice_default_notes(): string
{
    $template = invoice_default_template('notes');
    return $template ? invoice_template_fill((string) $template['body']) : '';
}

/**
 * Saved invoice note and email templates. Each list always has exactly one default.
 *
 * @return array{notes: list<array{id: string, name: string, body: string, is_default: bool}>, emails: list<array{id: string, name: string, subject: string, body: string, is_default: bool}>}
 */
function invoice_templates(): array
{
    $stored = (new SettingsRepository())->get('invoice_templates', null);
    if (!is_array($stored)) {
        $stored = [];
    }
    $out = [];
    foreach (['notes', 'emails'] as $kind) {
        $list = invoice_normalize_templates($kind, is_array($stored[$kind] ?? null) ? $stored[$kind] : []);
        $out[$kind] = $list !== [] ? $list : invoice_builtin_templates()[$kind];
    }
    return $out;
}

/** @return array{notes: list<array<string, mixed>>, emails: list<array<string, mixed>>} */
function invoice_builtin_templates(): array
{
    return [
        'notes' => [[
            'id' => 'default',
            'name' => 'Standard',
            'body' => 'Please make all payments to {payment_email}',
            'is_default' => true,
        ]],
        'emails' => [[
            'id' => 'default',
            'name' => 'Standard',
            'subject' => 'Invoice #{invoice_number} from Verma Accounting',
            'body' => "Hi {client_name},\n\nPlease find invoice #{invoice_number} attached as a PDF.\n\nPlease make payment to {payment_email}.",
            'is_default' => true,
        ]],
    ];
}

/**
 * @param 'notes'|'emails' $kind
 * @param array<int, mixed> $rows
 * @return list<array<string, mixed>>
 */
function invoice_normalize_templates(string $kind, array $rows): array
{
    $list = [];
    $seen = [];
    $hasDefault = false;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 80);
        $body = str_replace("\r\n", "\n", trim((string) ($row['body'] ?? '')));
        $subject = mb_substr(trim((string) ($row['subject'] ?? '')), 0, 200);
        if ($body === '' && ($kind === 'notes' || $subject === '')) {
            continue;
        }
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string) ($row['id'] ?? '')) ?: '';
        if ($id === '' || isset($seen[$id])) {
            $id = bin2hex(random_bytes(4));
        }
        $seen[$id] = true;
        $isDefault = !$hasDefault && !empty($row['is_default']);
        $hasDefault = $hasDefault || $isDefault;
        $item = [
            'id' => $id,
            'name' => $name !== '' ? $name : 'Template ' . (count($list) + 1),
            'body' => mb_substr($body, 0, 5000),
            'is_default' => $isDefault,
        ];
        if ($kind === 'emails') {
            $item = ['id' => $id, 'name' => $item['name'], 'subject' => $subject, 'body' => $item['body'], 'is_default' => $isDefault];
        }
        $list[] = $item;
    }
    if ($list !== [] && !$hasDefault) {
        $list[0]['is_default'] = true;
    }
    return $list;
}

/** @param array{notes?: array<int, mixed>, emails?: array<int, mixed>} $templates */
function invoice_save_templates(array $templates): void
{
    (new SettingsRepository())->set('invoice_templates', [
        'notes' => invoice_normalize_templates('notes', $templates['notes'] ?? []),
        'emails' => invoice_normalize_templates('emails', $templates['emails'] ?? []),
    ]);
}

/**
 * @param 'notes'|'emails' $kind
 * @return array<string, mixed>|null
 */
function invoice_default_template(string $kind): ?array
{
    foreach (invoice_templates()[$kind] as $template) {
        if (!empty($template['is_default'])) {
            return $template;
        }
    }
    return invoice_templates()[$kind][0] ?? null;
}

/** @return array<string, string> placeholder => description */
function invoice_template_placeholders(): array
{
    return [
        '{client_name}' => 'Client or company name',
        '{invoice_number}' => 'Invoice number',
        '{amount_due}' => 'Balance due, e.g. $250.00',
        '{due_date}' => 'Payment due date',
        '{invoice_date}' => 'Invoice date',
        '{payment_email}' => 'Payment email from company details',
        '{company_name}' => 'Your company name',
    ];
}

/**
 * Replace {placeholders}. Invoice placeholders are left as typed when no invoice is given.
 *
 * @param array<string, mixed>|null $invoice
 */
function invoice_template_fill(string $text, ?array $invoice = null): string
{
    $company = invoice_company_settings();
    $values = [
        '{payment_email}' => (string) ($company['payment_email'] ?? ''),
        '{company_name}' => (string) ($company['name'] ?? ''),
    ];
    if ($invoice !== null) {
        $client = trim((string) ($invoice['bill_to_name'] ?? ''));
        if ($client === '') {
            $client = trim((string) ($invoice['bill_to_company'] ?? ''));
        }
        $values += [
            '{client_name}' => $client !== '' ? $client : 'there',
            '{invoice_number}' => (string) ($invoice['invoice_number'] ?? ''),
            '{amount_due}' => invoice_format_money(invoice_amount_due($invoice)),
            '{due_date}' => invoice_format_date((string) ($invoice['due_date'] ?? '')),
            '{invoice_date}' => invoice_format_date((string) ($invoice['invoice_date'] ?? '')),
        ];
    }
    return strtr($text, $values);
}

/** @return array{street: string, city: string, province: string, postal: string, country: string} */
function invoice_extract_address_from_submission(array $submission, array $schema): array
{
    $address = client_extract_address_from_submission($submission, $schema);
    if ($address['province'] === '') {
        $address['province'] = 'Ontario';
    }
    if ($address['country'] === '') {
        $address['country'] = 'Canada';
    }
    return $address;
}

/**
 * Build invoice editor defaults from a form submission.
 *
 * @return array<string, mixed>|null
 */
function invoice_prefill_from_submission(int $submissionId, int $formId): ?array
{
    if ($submissionId < 1 || $formId < 1) {
        return null;
    }

    $formRepo = new FormRepository();
    $form = $formRepo->find($formId);
    $submission = $formRepo->findSubmissionForForm($submissionId, $formId);
    if (!$form || !$submission) {
        return null;
    }

    $schema = $formRepo->decodeSchema($form);
    $clientRepo = new ClientRepository();
    $clientRepo->linkFromSubmission($submission, $schema);
    $client = $clientRepo->findBySubmissionId($submissionId);
    $extracted = extract_client_from_submission($submission, $schema);
    $address = invoice_extract_address_from_submission($submission, $schema);
    $clientAddress = client_address_from_row($client);
    if ($address['street'] === '' && $address['city'] === '' && $clientAddress['street'] . $clientAddress['city'] !== '') {
        $address = array_merge($address, array_filter($clientAddress, static fn (string $v): bool => $v !== ''));
    }

    $billToCompany = trim((string) ($client['company'] ?? $extracted['company'] ?? ''));
    $billToName = trim((string) ($client['name'] ?? $extracted['name'] ?? ''));
    if ($billToCompany === '' && $billToName === '') {
        if ($extracted['email'] !== '') {
            $billToName = $extracted['email'];
        } elseif ($extracted['phone'] !== '') {
            $billToName = $extracted['phone'];
        }
    }

    $taxYear = submission_tax_year_label($submission);
    $notes = invoice_default_notes();
    $reference = 'Submission #' . $submissionId . ' (' . trim((string) ($form['title'] ?? 'Form')) . ')';
    if ($taxYear !== '') {
        $reference .= ' — tax year ' . $taxYear;
    }
    $submittedAt = trim((string) ($submission['created_at'] ?? ''));
    if ($submittedAt !== '') {
        $reference .= ' — submitted ' . $submittedAt;
    }
    $notes = $reference . "\n\n" . $notes;

    return [
        'submission_id' => $submissionId,
        'form_id' => $formId,
        'form_title' => (string) ($form['title'] ?? ''),
        'client_id' => $client ? (int) $client['id'] : null,
        'bill_to_company' => $billToCompany,
        'bill_to_name' => $billToName,
        'bill_to_email' => trim((string) ($client['email'] ?? $extracted['email'] ?? '')),
        'bill_to_phone' => trim((string) ($client['phone'] ?? $extracted['phone'] ?? '')),
        'bill_to_street' => $address['street'],
        'bill_to_city' => $address['city'],
        'bill_to_province' => $address['province'],
        'bill_to_postal' => $address['postal'],
        'bill_to_country' => $address['country'],
        'notes' => $notes,
    ];
}

function invoice_edit_url_from_submission(int $submissionId, int $formId): string
{
    return '/admin/invoice-edit?submission_id=' . max(0, $submissionId) . '&form_id=' . max(0, $formId);
}

/**
 * Ensure a clients-directory record exists for walk-in invoice bill-to details.
 *
 * @return array{client_id: ?int, created: bool}
 */
function invoice_ensure_client_from_bill_to(
    ?int $clientId,
    string $billToName,
    string $billToCompany,
    string $billToEmail = '',
    string $billToPhone = '',
    array $billToAddress = []
): array {
    if ($clientId !== null && $clientId > 0) {
        return ['client_id' => $clientId, 'created' => false];
    }

    $name = trim($billToName);
    $company = trim($billToCompany);
    if ($name === '' && $company === '') {
        return ['client_id' => null, 'created' => false];
    }

    $result = (new ClientRepository())->upsertFromContact([
        'name' => $name !== '' ? $name : $company,
        'company' => $company,
        'email' => strtolower(trim($billToEmail)),
        'phone' => trim($billToPhone),
        'address' => $billToAddress,
    ], 'manual');

    if ($result === null) {
        return ['client_id' => null, 'created' => false];
    }

    return [
        'client_id' => (int) $result['id'],
        'created' => !empty($result['is_new']),
    ];
}
