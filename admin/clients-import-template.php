<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('admin');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="client-import-template.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Could not generate file.');
}

fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Name', 'SIN', 'Email', 'Phone', 'Company', 'Notes']);
fputcsv($out, ['Jane Verma', '100001', 'jane@example.com', '613-555-0100', 'Verma Consulting', 'Existing client from 2024']);
fputcsv($out, ['John Smith', '100002', 'john@example.com', '', '', '']);
fclose($out);
exit;
