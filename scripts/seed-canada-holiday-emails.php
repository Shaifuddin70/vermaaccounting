<?php
/**
 * Seed / refresh Canadian holiday email templates.
 *
 * Usage:
 *   php scripts/seed-canada-holiday-emails.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$result = seed_canada_holiday_emails('CLI seed');

echo "Canada holiday emails seeded.\n";
echo '  Created: ' . $result['created'] . "\n";
echo '  Updated: ' . $result['updated'] . "\n";
echo '  Total:   ' . $result['total'] . "\n";

$year = (int) (new DateTimeImmutable('now', app_timezone()))->format('Y');
echo "\nResolved dates for {$year}:\n";
foreach ((new HolidayScheduleRepository())->all() as $row) {
    $resolved = holiday_resolve_date($row, $year);
    printf(
        "  - %-18s %s (%s)\n",
        (string) $row['name'],
        sprintf('%04d-%02d-%02d', $year, $resolved['month'], $resolved['day']),
        holiday_rule_label($row)
    );
}
