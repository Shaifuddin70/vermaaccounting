<?php

declare(strict_types=1);

/**
 * Recurring invoices: clone a template invoice on a day-of-month for selected months,
 * optionally email the client automatically.
 */

/** @return list<int> months 1–12 */
function invoice_recurring_normalize_months(mixed $raw): array
{
    $months = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
    }
    if (!is_array($raw)) {
        return range(1, 12);
    }
    foreach ($raw as $m) {
        $n = (int) $m;
        if ($n >= 1 && $n <= 12) {
            $months[$n] = $n;
        }
    }
    if ($months === []) {
        return range(1, 12);
    }
    sort($months, SORT_NUMERIC);
    return array_values($months);
}

/** @param list<int>|array<int, mixed> $months */
function invoice_recurring_months_to_json(array $months): string
{
    $normalized = invoice_recurring_normalize_months($months);
    return json_encode($normalized, JSON_THROW_ON_ERROR);
}

function invoice_recurring_month_label(int $month): string
{
    $labels = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
    return $labels[$month] ?? (string) $month;
}

/**
 * Effective run day for a month (clamps 29–31 to last day of month).
 */
function invoice_recurring_effective_day(int $year, int $month, int $dayOfMonth): int
{
    $dayOfMonth = max(1, min(31, $dayOfMonth));
    $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    return min($dayOfMonth, $daysInMonth);
}

/**
 * Next scheduled date (Y-m-d) on or after $from, or null if disabled / no months.
 *
 * @param array<string, mixed> $schedule
 */
function invoice_recurring_next_date(array $schedule, ?DateTimeImmutable $from = null): ?string
{
    if (empty($schedule['enabled'])) {
        return null;
    }
    $tz = app_timezone();
    $from = $from ?? new DateTimeImmutable('now', $tz);
    $dayOfMonth = max(1, min(31, (int) ($schedule['day_of_month'] ?? 1)));
    $months = invoice_recurring_normalize_months($schedule['months_json'] ?? []);
    $cursor = $from->setTime(0, 0);

    for ($i = 0; $i < 24; $i++) {
        $year = (int) $cursor->format('Y');
        $month = (int) $cursor->format('n');
        if (in_array($month, $months, true)) {
            $day = invoice_recurring_effective_day($year, $month, $dayOfMonth);
            $candidate = DateTimeImmutable::createFromFormat(
                'Y-m-d',
                sprintf('%04d-%02d-%02d', $year, $month, $day),
                $tz
            );
            if ($candidate !== false && $candidate >= $cursor) {
                return $candidate->format('Y-m-d');
            }
        }
        $cursor = $cursor->modify('first day of next month');
    }
    return null;
}

/**
 * @param array<string, mixed> $schedule
 */
function invoice_recurring_is_due_now(array $schedule, ?DateTimeImmutable $now = null): bool
{
    if (empty($schedule['enabled'])) {
        return false;
    }
    $tz = app_timezone();
    $now = $now ?? new DateTimeImmutable('now', $tz);
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');
    $day = (int) $now->format('j');
    $months = invoice_recurring_normalize_months($schedule['months_json'] ?? []);
    if (!in_array($month, $months, true)) {
        return false;
    }
    $effectiveDay = invoice_recurring_effective_day($year, $month, (int) ($schedule['day_of_month'] ?? 1));
    if ($day !== $effectiveDay) {
        return false;
    }
    $runYm = $now->format('Y-m');
    if ((string) ($schedule['last_run_ym'] ?? '') === $runYm) {
        return false;
    }
    return true;
}

/**
 * Build create() payload from a template invoice + its items.
 *
 * @param array<string, mixed> $template
 * @param list<array<string, mixed>> $items
 * @return array{data: array<string, mixed>, items: list<array<string, mixed>>}
 */
function invoice_recurring_clone_payload(
    array $template,
    array $items,
    DateTimeImmutable $invoiceDate,
    int $dueDays
): array {
    $due = $invoiceDate->modify('+' . max(0, $dueDays) . ' days');
    $data = [
        'invoice_number' => (new InvoiceRepository())->nextInvoiceNumber(),
        'client_id' => $template['client_id'] ?? null,
        'bill_to_company' => (string) ($template['bill_to_company'] ?? ''),
        'bill_to_name' => (string) ($template['bill_to_name'] ?? ''),
        'bill_to_email' => (string) ($template['bill_to_email'] ?? ''),
        'bill_to_phone' => (string) ($template['bill_to_phone'] ?? ''),
        'bill_to_street' => (string) ($template['bill_to_street'] ?? ''),
        'bill_to_city' => (string) ($template['bill_to_city'] ?? ''),
        'bill_to_province' => (string) ($template['bill_to_province'] ?? ''),
        'bill_to_postal' => (string) ($template['bill_to_postal'] ?? ''),
        'bill_to_country' => (string) ($template['bill_to_country'] ?? 'Canada'),
        'invoice_date' => $invoiceDate->format('Y-m-d'),
        'due_date' => $due->format('Y-m-d'),
        'currency' => (string) ($template['currency'] ?? 'CAD'),
        'discount_percent' => (float) ($template['discount_percent'] ?? 0),
        'discount_flat' => (float) ($template['discount_flat'] ?? 0),
        'discount_percent_label' => (string) ($template['discount_percent_label'] ?? ''),
        'discount_flat_label' => (string) ($template['discount_flat_label'] ?? ''),
        // Do not carry forward advances/adjustments onto recurring copies.
        'advance_amount' => 0.0,
        'due_adjustment' => 0.0,
        'advance_label' => '',
        'due_adjustment_label' => '',
        'notes' => (string) ($template['notes'] ?? ''),
        'status' => 'approved',
        'created_by_user_id' => null,
        'created_by_name' => 'Recurring invoice',
    ];

    $clonedItems = [];
    foreach ($items as $i => $item) {
        $qty = (float) ($item['quantity'] ?? 1);
        $price = (float) ($item['unit_price'] ?? 0);
        $clonedItems[] = [
            'service_id' => $item['service_id'] ?? null,
            'name' => (string) ($item['name'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'unit_price' => $price,
            'quantity' => $qty,
            'amount' => invoice_money($price * $qty),
            'sort_order' => (int) ($item['sort_order'] ?? $i),
        ];
    }

    return ['data' => $data, 'items' => $clonedItems];
}

/**
 * Generate one recurring invoice from a schedule if the template still exists.
 *
 * @param array<string, mixed> $schedule
 * @return array{ok: bool, invoice_id?: int, emailed?: bool, error?: string}
 */
function invoice_recurring_generate(array $schedule, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now', app_timezone());
    $templateId = (int) ($schedule['template_invoice_id'] ?? 0);
    $repo = new InvoiceRepository();
    $template = $templateId > 0 ? $repo->find($templateId) : null;
    if ($template === null) {
        return ['ok' => false, 'error' => 'Template invoice not found.'];
    }

    $items = $repo->itemsForInvoice($templateId);
    if ($items === []) {
        return ['ok' => false, 'error' => 'Template invoice has no line items.'];
    }

    $dueDays = max(0, min(120, (int) ($schedule['due_days'] ?? 14)));
    $payload = invoice_recurring_clone_payload($template, $items, $now, $dueDays);
    $newId = $repo->create($payload['data'], $payload['items']);

    $emailed = false;
    if (!empty($schedule['auto_email'])) {
        $fresh = $repo->find($newId);
        $freshItems = $repo->itemsForInvoice($newId);
        $to = $fresh ? invoice_recipient_email($fresh) : '';
        if ($fresh && $to !== '') {
            $send = invoice_send_to_client($fresh, $freshItems, $to);
            if ($send['ok']) {
                $repo->updateStatus($newId, 'sent');
                $emailed = true;
            } else {
                error_log(
                    'Recurring invoice #' . $newId . ' created but email failed: '
                    . ($send['error'] ?? 'unknown')
                );
            }
        } else {
            error_log('Recurring invoice #' . $newId . ' created but no client email to send to.');
        }
    }

    return ['ok' => true, 'invoice_id' => $newId, 'emailed' => $emailed];
}

/**
 * @return array{checked: int, created: int, emailed: int, errors: list<string>, invoice_ids: list<int>}
 */
function process_due_invoice_recurrences(?DateTimeImmutable $now = null): array
{
    $out = ['checked' => 0, 'created' => 0, 'emailed' => 0, 'errors' => [], 'invoice_ids' => []];
    $now = $now ?? new DateTimeImmutable('now', app_timezone());
    $schedules = (new InvoiceRecurringRepository())->allEnabled();

    foreach ($schedules as $schedule) {
        $out['checked']++;
        if (!invoice_recurring_is_due_now($schedule, $now)) {
            continue;
        }
        $id = (int) ($schedule['id'] ?? 0);
        try {
            $result = invoice_recurring_generate($schedule, $now);
            if (!$result['ok']) {
                $out['errors'][] = 'schedule #' . $id . ': ' . ($result['error'] ?? 'failed');
                continue;
            }
            $newId = (int) ($result['invoice_id'] ?? 0);
            (new InvoiceRecurringRepository())->markRun($id, $now->format('Y-m'), $newId);
            ActivityLog::record('invoice.recurring_generated', 'invoice', $newId, [
                'schedule_id' => $id,
                'template_invoice_id' => (int) ($schedule['template_invoice_id'] ?? 0),
                'emailed' => !empty($result['emailed']),
            ]);
            $out['created']++;
            $out['invoice_ids'][] = $newId;
            if (!empty($result['emailed'])) {
                $out['emailed']++;
            }
        } catch (Throwable $e) {
            $out['errors'][] = 'schedule #' . $id . ': ' . $e->getMessage();
            error_log('invoice_recurring schedule #' . $id . ': ' . $e->getMessage());
        }
    }

    return $out;
}
