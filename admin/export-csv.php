<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
Auth::requireLogin();

if (Auth::userRole() === 'partner') {
    http_response_code(403);
    exit('Export is not available for partner accounts.');
}

$formId = (int) ($_GET['form_id'] ?? 0);
$repo = new FormRepository();
$form = $repo->find($formId);

if (!$form) {
    http_response_code(404);
    exit('Form not found.');
}

assert_form_submissions_access($form);

$schema = $repo->decodeSchema($form);
$taxYearOn = form_tax_year_enabled($schema);
$yearParam = $_GET['year'] ?? '';
$taxYearFilter = ($yearParam !== '' && $yearParam !== 'all') ? (int) $yearParam : null;
if ($taxYearFilter !== null && $taxYearFilter < 1) {
    $taxYearFilter = null;
}
$partnerId = partner_user_id();
$submissions = $repo->submissionsForForm($formId, null, $taxYearFilter, null, null, $partnerId);
$filesBySubmission = $repo->filesGroupedBySubmission($formId);

$inputFields = array_values(array_filter(
    $schema['fields'],
    static fn ($f) => !in_array($f['type'], ['heading', 'paragraph'], true)
));

$filename = slugify($form['slug']) . '-responses';
if ($taxYearFilter) {
    $filename .= '-' . $taxYearFilter;
}
$filename .= '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Could not open output.');
}

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

$headers = ['Submission ID', 'Submitted At'];
if ($taxYearOn) {
    $headers[] = 'Tax Year';
}
$headers[] = 'IP Address';
foreach ($inputFields as $field) {
    $headers[] = $field['label'];
    if ($field['type'] === 'yes_no' && !empty($field['reasonWhen'])) {
        $headers[] = ($field['reasonLabel'] ?? 'Reason') . ' (' . $field['label'] . ')';
    }
}
$headers[] = 'Uploaded Files';
fputcsv($out, $headers);

foreach ($submissions as $sub) {
    $data = json_decode($sub['data_json'], true) ?: [];
    $row = [
        (string) $sub['id'],
        $sub['created_at'],
    ];
    if ($taxYearOn) {
        $row[] = submission_tax_year_label($sub) ?: '';
    }
    $row[] = $sub['ip'] ?? '';

    foreach ($inputFields as $field) {
        $key = $field['name'];
        $val = $data[$key] ?? '';
        if (is_array($val)) {
            $val = implode('; ', $val);
        }
        $row[] = (string) $val;
        if ($field['type'] === 'yes_no' && !empty($field['reasonWhen'])) {
            $row[] = (string) ($data[$key . '_reason'] ?? '');
        }
    }

    $fileParts = [];
    foreach ($filesBySubmission[(int) $sub['id']] ?? [] as $file) {
        $fileParts[] = $file['original_name'] . ' (admin download: /admin/download?file_id=' . $file['id'] . ')';
    }
    $row[] = implode(' | ', $fileParts);

    fputcsv($out, $row);
}

fclose($out);
exit;
