<?php

declare(strict_types=1);

/**
 * Seed demo submissions for testing pagination and admin views.
 *
 * Usage:
 *   php scripts/seed-demo-submissions.php [count] [form_id]
 */

require_once __DIR__ . '/../lib/bootstrap.php';

$count = max(1, (int) ($argv[1] ?? 30));
$formId = isset($argv[2]) ? (int) $argv[2] : 0;

$formRepo = new FormRepository();
$clientRepo = new ClientRepository();

if ($formId < 1) {
    foreach ($formRepo->all() as $form) {
        if (($form['status'] ?? '') === 'published') {
            $formId = (int) $form['id'];
            break;
        }
    }
    if ($formId < 1) {
        $all = $formRepo->all();
        $formId = $all !== [] ? (int) $all[0]['id'] : 0;
    }
}

$form = $formRepo->find($formId);
if (!$form) {
    fwrite(STDERR, "Form #{$formId} not found.\n");
    exit(1);
}

$schema = $formRepo->decodeSchema($form);
$taxYears = form_tax_year_options($schema);
if ($taxYears === []) {
    $taxYears = [(int) date('Y'), (int) date('Y') - 1];
}

$firstNames = ['James', 'Maria', 'David', 'Priya', 'Michael', 'Sarah', 'Raj', 'Emily', 'Ahmed', 'Lisa',
    'Robert', 'Anita', 'Carlos', 'Jennifer', 'Wei', 'Olivia', 'Mohammed', 'Sophie', 'Daniel', 'Aisha',
    'Thomas', 'Grace', 'Kevin', 'Nadia', 'Brian', 'Elena', 'Jason', 'Fatima', 'Andrew', 'Mei'];
$lastNames = ['Singh', 'Patel', 'Johnson', 'Williams', 'Chen', 'Kaur', 'Brown', 'Garcia', 'Ali', 'Nguyen',
    'Martinez', 'Taylor', 'Khan', 'Anderson', 'Lee', 'Wilson', 'Sharma', 'Thompson', 'Hassan', 'Clark',
    'Robinson', 'Lewis', 'Walker', 'Hall', 'Young', 'King', 'Wright', 'Scott', 'Green', 'Baker'];

$pdo = Database::instance()->pdo();
$insert = $pdo->prepare('
    INSERT INTO submissions (form_id, data_json, status, tax_year, ip, user_agent, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
');

$created = 0;
$baseCin = 800000000;

for ($i = 0; $i < $count; $i++) {
    $first = $firstNames[$i % count($firstNames)];
    $last = $lastNames[$i % count($lastNames)];
    $fullName = $first . ' ' . $last . ' (Demo)';
    $slug = strtolower($first . '.' . $last);
    $cin = (string) ($baseCin + $i + 1);
    $email = 'demo.' . ($i + 1) . '.' . $slug . '@example.test';
    $phone = '416555' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);
    $dobYear = 1970 + ($i % 30);
    $dobMonth = str_pad((string) (1 + ($i % 12)), 2, '0', STR_PAD_LEFT);
    $dobDay = str_pad((string) (1 + ($i % 28)), 2, '0', STR_PAD_LEFT);
    $yesNo = ($i % 3 === 0) ? 'no' : 'yes';

    $data = [
        'f_164d2a28' => $cin,
        'f_414b9b5a' => "{$dobYear}-{$dobMonth}-{$dobDay}",
        'f_fb45fde1' => $fullName,
        'f_03bbb81b' => $phone,
        'f_469f7ea2' => $email,
        'f_466be14a' => $yesNo,
    ];
    if ($yesNo === 'no') {
        $data['f_466be14a_reason'] = 'Demo submission — sample reason for testing.';
    }

    $taxYear = $taxYears[$i % count($taxYears)];
    $status = ($i % 4 === 0) ? 'complete' : 'pending';
    $daysAgo = $count - $i;
    $createdAt = gmdate('Y-m-d H:i:s', strtotime("-{$daysAgo} days -" . ($i % 12) . ' hours'));

    $insert->execute([
        $formId,
        json_encode($data, JSON_UNESCAPED_UNICODE),
        $status,
        $taxYear,
        '127.0.0.1',
        'DemoSeeder/1.0',
        $createdAt,
        $createdAt,
    ]);

    $submissionId = (int) $pdo->lastInsertId();
    $clientRepo->linkFromSubmission([
        'id' => $submissionId,
        'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
    ], $schema);

    $created++;
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM submissions WHERE form_id = ' . (int) $formId)->fetchColumn();

echo "Added {$created} demo submission(s) to form \"{$form['title']}\" (#{$formId}).\n";
echo "Total submissions for this form: {$total}\n";
