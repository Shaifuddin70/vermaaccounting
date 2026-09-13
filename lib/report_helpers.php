<?php

declare(strict_types=1);

/**
 * Resolve a report date window from date pickers.
 *
 * @return array{from: string, to: string, label: string, grain: 'day'|'week'|'month'}
 */
function report_resolve_period(?string $fromInput = null, ?string $toInput = null): array
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $today = new DateTimeImmutable('today', $tz);

    $from = report_parse_ymd($fromInput);
    $to = report_parse_ymd($toInput);

    if ($from === null && $to === null) {
        $from = $today->modify('first day of this month')->format('Y-m-d');
        $to = $today->format('Y-m-d');
    } elseif ($from === null) {
        $from = $to;
    } elseif ($to === null) {
        $to = $from;
    }

    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }

    $days = (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
    $grain = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');

    return [
        'from' => $from,
        'to' => $to,
        'label' => report_format_range_label($from, $to),
        'grain' => $grain,
    ];
}

function report_parse_ymd(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($dt === false || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

function report_format_range_label(string $from, string $to): string
{
    $a = DateTimeImmutable::createFromFormat('Y-m-d', $from) ?: null;
    $b = DateTimeImmutable::createFromFormat('Y-m-d', $to) ?: null;
    if (!$a || !$b) {
        return $from . ' – ' . $to;
    }
    if ($from === $to) {
        return $a->format('M j, Y');
    }
    if ($a->format('Y') === $b->format('Y')) {
        if ($a->format('m') === $b->format('m')) {
            return $a->format('M j') . ' – ' . $b->format('j, Y');
        }

        return $a->format('M j') . ' – ' . $b->format('M j, Y');
    }

    return $a->format('M j, Y') . ' – ' . $b->format('M j, Y');
}

/**
 * Income counts once an invoice is approved (approved + sent).
 */
function report_invoice_counts_as_income(string $status): bool
{
    $status = strtolower(trim($status));
    return in_array($status, ['approved', 'sent', 'paid'], true);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{
 *   invoice_count: int,
 *   sent_count: int,
 *   approved_count: int,
 *   draft_count: int,
 *   income_count: int,
 *   income_due: float,
 *   income_total: float,
 *   advance_total: float,
 *   clients_served: int,
 *   walk_in_invoices: int,
 *   by_status: array<string, array{count: int, amount_due: float, total: float}>
 * }
 */
function report_summarize_invoices(array $rows): array
{
    $byStatus = [];
    foreach (invoice_status_options() as $status) {
        $byStatus[$status] = ['count' => 0, 'amount_due' => 0.0, 'total' => 0.0];
    }

    $clientIds = [];
    $walkIn = 0;
    $incomeDue = 0.0;
    $incomeTotal = 0.0;
    $advanceTotal = 0.0;
    $incomeCount = 0;

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? 'draft');
        if ($status === 'paid') {
            $status = 'sent';
        } elseif ($status === 'void') {
            $status = 'draft';
        }
        if (!isset($byStatus[$status])) {
            $byStatus[$status] = ['count' => 0, 'amount_due' => 0.0, 'total' => 0.0];
        }

        $amountDue = invoice_money((float) ($row['amount_due'] ?? $row['total'] ?? 0));
        $total = invoice_money((float) ($row['total'] ?? 0));
        $advance = invoice_money(max(0, (float) ($row['advance_amount'] ?? 0)));

        $byStatus[$status]['count']++;
        $byStatus[$status]['amount_due'] = invoice_money($byStatus[$status]['amount_due'] + $amountDue);
        $byStatus[$status]['total'] = invoice_money($byStatus[$status]['total'] + $total);

        $clientId = (int) ($row['client_id'] ?? 0);
        if (report_invoice_counts_as_income($status)) {
            $incomeCount++;
            $incomeDue = invoice_money($incomeDue + $amountDue);
            $incomeTotal = invoice_money($incomeTotal + $total);
            $advanceTotal = invoice_money($advanceTotal + $advance);
            if ($clientId > 0) {
                $clientIds[$clientId] = true;
            } else {
                $walkIn++;
            }
        }
    }

    return [
        'invoice_count' => count($rows),
        'sent_count' => (int) ($byStatus['sent']['count'] ?? 0),
        'approved_count' => (int) ($byStatus['approved']['count'] ?? 0),
        'draft_count' => (int) ($byStatus['draft']['count'] ?? 0),
        'income_count' => $incomeCount,
        'income_due' => $incomeDue,
        'income_total' => $incomeTotal,
        'advance_total' => $advanceTotal,
        'clients_served' => count($clientIds),
        'walk_in_invoices' => $walkIn,
        'by_status' => $byStatus,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{key: string, label: string, income_due: float, income_total: float, invoice_count: int}>
 */
function report_build_series(array $rows, string $grain, ?string $from, ?string $to): array
{
    $grain = in_array($grain, ['day', 'week', 'month'], true) ? $grain : 'month';
    $buckets = [];

    if ($from !== null && $to !== null) {
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        while ($cursor <= $end) {
            $key = report_bucket_key($cursor, $grain);
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'key' => $key,
                    'label' => report_bucket_label($cursor, $grain),
                    'income_due' => 0.0,
                    'income_total' => 0.0,
                    'invoice_count' => 0,
                ];
            }
            $cursor = match ($grain) {
                'day' => $cursor->modify('+1 day'),
                'week' => $cursor->modify('+1 week'),
                default => $cursor->modify('first day of next month'),
            };
        }
    }

    foreach ($rows as $row) {
        if (!report_invoice_counts_as_income((string) ($row['status'] ?? ''))) {
            continue;
        }
        $date = report_parse_ymd((string) ($row['invoice_date'] ?? ''));
        if ($date === null) {
            continue;
        }
        $dt = new DateTimeImmutable($date);
        $key = report_bucket_key($dt, $grain);
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'key' => $key,
                'label' => report_bucket_label($dt, $grain),
                'income_due' => 0.0,
                'income_total' => 0.0,
                'invoice_count' => 0,
            ];
        }
        $buckets[$key]['income_due'] = invoice_money(
            $buckets[$key]['income_due'] + (float) ($row['amount_due'] ?? $row['total'] ?? 0)
        );
        $buckets[$key]['income_total'] = invoice_money(
            $buckets[$key]['income_total'] + (float) ($row['total'] ?? 0)
        );
        $buckets[$key]['invoice_count']++;
    }

    ksort($buckets);

    return array_values($buckets);
}

function report_bucket_key(DateTimeImmutable $dt, string $grain): string
{
    return match ($grain) {
        'day' => $dt->format('Y-m-d'),
        'week' => $dt->modify('monday this week')->format('o-\WW'),
        default => $dt->format('Y-m'),
    };
}

function report_bucket_label(DateTimeImmutable $dt, string $grain): string
{
    return match ($grain) {
        'day' => $dt->format('M j'),
        'week' => 'Week of ' . $dt->modify('monday this week')->format('M j'),
        default => $dt->format('M Y'),
    };
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{client_id: ?int, name: string, invoice_count: int, income_due: float, income_total: float}>
 */
function report_top_clients(array $rows, int $limit = 8): array
{
    $map = [];
    foreach ($rows as $row) {
        if (!report_invoice_counts_as_income((string) ($row['status'] ?? ''))) {
            continue;
        }
        $clientId = (int) ($row['client_id'] ?? 0);
        $key = $clientId > 0 ? 'c:' . $clientId : 'w:' . md5(
            strtolower(trim((string) ($row['bill_to_name'] ?? '')) . '|' . strtolower(trim((string) ($row['bill_to_company'] ?? ''))))
        );
        if (!isset($map[$key])) {
            $name = trim((string) ($row['client_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['bill_to_name'] ?? ''));
            }
            if ($name === '') {
                $name = trim((string) ($row['bill_to_company'] ?? ''));
            }
            if ($name === '') {
                $name = 'Walk-in client';
            }
            $map[$key] = [
                'client_id' => $clientId > 0 ? $clientId : null,
                'name' => $name,
                'invoice_count' => 0,
                'income_due' => 0.0,
                'income_total' => 0.0,
            ];
        }
        $map[$key]['invoice_count']++;
        $map[$key]['income_due'] = invoice_money(
            $map[$key]['income_due'] + (float) ($row['amount_due'] ?? $row['total'] ?? 0)
        );
        $map[$key]['income_total'] = invoice_money(
            $map[$key]['income_total'] + (float) ($row['total'] ?? 0)
        );
    }

    usort($map, static fn (array $a, array $b): int => $b['income_due'] <=> $a['income_due']);

    return array_slice(array_values($map), 0, max(1, $limit));
}

