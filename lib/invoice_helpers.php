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
 *   items: list<array<string, mixed>>
 * }
 */
function invoice_calculate_totals(array $items, float $discountPercent = 0.0, float $discountFlat = 0.0): array
{
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

    return [
        'subtotal' => $subtotal,
        'discount_percent_amount' => $breakdown['percent_amount'],
        'discount_flat_amount' => $breakdown['flat_amount'],
        'discount_amount' => $breakdown['total_discount'],
        'total' => $total,
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

/** @return list<string> */
function invoice_status_options(): array
{
    return ['draft', 'sent', 'paid', 'void'];
}

function invoice_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'sent' => 'Sent',
        'paid' => 'Paid',
        'void' => 'Void',
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

/** Default notes matching the PDF template (thank-you is rendered separately). */
function invoice_default_notes(): string
{
    $email = invoice_company_settings()['payment_email'] ?? 'info@vermaaccounting.ca';
    return "Please make all payments to {$email}";
}

/** @return array{street: string, city: string, province: string, postal: string, country: string} */
function invoice_extract_address_from_submission(array $submission, array $schema): array
{
    $data = json_decode((string) ($submission['data_json'] ?? '{}'), true) ?: [];
    $street = '';
    $city = '';
    $province = '';
    $postal = '';
    $country = '';

    foreach ($schema['fields'] ?? [] as $field) {
        $fieldName = (string) ($field['name'] ?? '');
        $label = strtolower((string) ($field['label'] ?? ''));
        $haystack = strtolower($fieldName) . ' ' . $label;
        $raw = $data[$fieldName] ?? '';
        if (is_array($raw)) {
            $val = trim(implode(', ', array_map('strval', $raw)));
        } else {
            $val = trim((string) $raw);
        }
        if ($val === '') {
            continue;
        }

        if ($street === '' && preg_match('/\b(street|address\s*line|addr|address)\b/', $haystack)
            && !preg_match('/\b(email|e-mail)\b/', $haystack)) {
            $street = $val;
        } elseif ($city === '' && preg_match('/\b(city|town|municipality)\b/', $haystack)) {
            $city = $val;
        } elseif ($province === '' && preg_match('/\b(province|state|territory)\b/', $haystack)) {
            $province = $val;
        } elseif ($postal === '' && preg_match('/\b(postal|postcode|zip)\b/', $haystack)) {
            $postal = $val;
        } elseif ($country === '' && preg_match('/\b(country)\b/', $haystack)) {
            $country = $val;
        }
    }

    return [
        'street' => $street,
        'city' => $city,
        'province' => $province !== '' ? $province : 'Ontario',
        'postal' => $postal,
        'country' => $country !== '' ? $country : 'Canada',
    ];
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
