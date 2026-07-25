<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

$clientRepo = new ClientRepository();
$rows = $clientRepo->exportRows();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="clients-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Could not open output.');
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Name', 'SIN', 'Email', 'Phone', 'Company', 'Notes', 'Source', 'Submissions', 'Added']);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['name'] ?? '',
        $row['sin'] ?? '',
        $row['email'] ?? '',
        $row['phone'] ?? '',
        $row['company'] ?? '',
        $row['notes'] ?? '',
        ($row['source'] ?? '') === 'import' ? 'Imported' : 'Form',
        (int) ($row['submission_count'] ?? 0),
        substr((string) ($row['created_at'] ?? ''), 0, 10),
    ]);
}

fclose($out);
exit;
