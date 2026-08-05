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
 * @return array{subtotal: float, discount_amount: float, total: float, items: list<array<string, mixed>>}
 */
function invoice_calculate_totals(array $items, float $discountPercent = 0.0): array
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
    $discountPercent = invoice_percent($discountPercent);
    $discountAmount = invoice_money($subtotal * ($discountPercent / 100));
    $total = invoice_money(max(0, $subtotal - $discountAmount));

    return [
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'total' => $total,
        'items' => $normalized,
    ];
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
