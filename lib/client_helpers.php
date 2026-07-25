<?php

declare(strict_types=1);

/** @return array{name: string, sin: string, email: string, phone: string, company: string, date_of_birth: string} */
function extract_client_from_submission(array $submission, array $schema): array
{
    $data = json_decode((string) ($submission['data_json'] ?? '{}'), true) ?: [];
    $name = '';
    $sin = '';
    $email = '';
    $phone = '';
    $company = '';
    $dob = '';

    foreach ($schema['fields'] ?? [] as $field) {
        $fieldName = (string) ($field['name'] ?? '');
        $type = (string) ($field['type'] ?? '');
        $n = strtolower($fieldName);
        $label = strtolower((string) ($field['label'] ?? ''));
        $haystack = $n . ' ' . $label;

        $raw = $data[$fieldName] ?? '';
        if (is_array($raw)) {
            $val = trim(implode(', ', array_map('strval', $raw)));
        } else {
            $val = trim((string) $raw);
        }
        if ($val === '') {
            continue;
        }

        if ($type === 'email' || str_contains($haystack, 'email')) {
            $email = $email !== '' ? $email : $val;
        } elseif (
            preg_match('/\b(cin|sin|ssn|social\s*insurance)\b/', $haystack)
            || (string) ($field['numberFormat'] ?? '') === '###-###-###'
        ) {
            $sin = $sin !== '' ? $sin : $val;
        } elseif (
            $type === 'date'
            || preg_match('/\b(dob|birth\s*date|date\s*of\s*birth|birthday|birth)\b/', $haystack)
        ) {
            $normalized = birthday_normalize_dob($val);
            if ($normalized !== null) {
                $dob = $dob !== '' ? $dob : $normalized;
            }
        } elseif ($type === 'tel' || preg_match('/\b(phone|tel|mobile|cell|fax|contact\s*number)\b/', $haystack)
            || (preg_match('/\bnumber\b/', $haystack) && !preg_match('/\b(cin|sin|ssn|tax|account|invoice|order|id)\b/', $haystack))) {
            $phone = $phone !== '' ? $phone : $val;
        } elseif (preg_match('/\b(company|business|organization|organisation|firm)\b/', $haystack)) {
            $company = $company !== '' ? $company : $val;
        } elseif (preg_match('/\b(full|client|contact|customer)\s+name\b|\b(client|customer)\b|\bname\b/', $haystack)
            && !preg_match('/\b(user|user\s*name|file|image|form)\s*name\b/', $haystack)) {
            $name = $name !== '' ? $name : $val;
        }
    }

    if ($name === '') {
        foreach ($schema['fields'] ?? [] as $field) {
            if (!in_array($field['type'] ?? '', ['text', 'email', 'tel'], true)) {
                continue;
            }
            $val = trim((string) ($data[$field['name'] ?? ''] ?? ''));
            if ($val !== '' && !is_array($data[$field['name'] ?? ''] ?? '')) {
                $name = $val;
                break;
            }
        }
    }

    return [
        'name' => $name,
        'sin' => $sin,
        'email' => strtolower($email),
        'phone' => $phone,
        'company' => $company,
        'date_of_birth' => $dob,
    ];
}

function birthday_normalize_dob(mixed $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if ($y >= 1900 && $y <= 2100 && checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        return null;
    }

    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
        $a = (int) $m[1];
        $b = (int) $m[2];
        $y = (int) $m[3];
        if ($a > 12) {
            $d = $a;
            $mo = $b;
        } elseif ($b > 12) {
            $mo = $a;
            $d = $b;
        } else {
            $mo = $a;
            $d = $b;
        }
        if ($y >= 1900 && $y <= 2100 && checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }

    try {
        $dt = new DateTimeImmutable($raw);
        $y = (int) $dt->format('Y');
        if ($y < 1900 || $y > 2100) {
            return null;
        }
        return $dt->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function normalize_client_csv_header(string $header): string
{
    $h = strtolower(trim($header));
    $h = preg_replace('/[^a-z0-9]+/', '', $h) ?? '';
    return $h;
}

/** @return 'name'|'sin'|'email'|'phone'|'company'|'notes'|'date_of_birth'|null */
function map_client_csv_column(string $normalizedHeader): ?string
{
    return match (true) {
        in_array($normalizedHeader, ['name', 'fullname', 'clientname', 'contactname', 'customername', 'client'], true) => 'name',
        in_array($normalizedHeader, ['sin', 'cin', 'socialinsurancenumber', 'socialinsurance', 'clientid', 'clientnumber', 'clientno', 'idnumber'], true) => 'sin',
        in_array($normalizedHeader, ['email', 'emailaddress', 'mail'], true) => 'email',
        in_array($normalizedHeader, ['phone', 'phonenumber', 'telephone', 'tel', 'mobile', 'cell', 'cellphone', 'contactnumber'], true) => 'phone',
        in_array($normalizedHeader, ['company', 'business', 'organization', 'organisation', 'firm', 'businessname'], true) => 'company',
        in_array($normalizedHeader, ['dateofbirth', 'dob', 'birthdate', 'birthday', 'birth', 'datebirth'], true) => 'date_of_birth',
        in_array($normalizedHeader, ['notes', 'note', 'comments', 'comment', 'remarks'], true) => 'notes',
        default => null,
    };
}

function parse_client_csv_file(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not read the uploaded file.');
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        throw new RuntimeException('The file is empty.');
    }

    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headerRow = fgetcsv($handle, 0, $delimiter);
    if (!$headerRow) {
        fclose($handle);
        throw new RuntimeException('Could not read column headers.');
    }

    $columnMap = [];
    foreach ($headerRow as $idx => $col) {
        $mapped = map_client_csv_column(normalize_client_csv_header((string) $col));
        if ($mapped !== null && !isset($columnMap[$mapped])) {
            $columnMap[$mapped] = (int) $idx;
        }
    }

    if (!isset($columnMap['name'])) {
        fclose($handle);
        throw new RuntimeException('CSV must include a Name column (e.g. Name, Full Name, Client Name).');
    }

    $rows = [];
    $line = 1;
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line++;
        if ($data === [null] || $data === false) {
            continue;
        }
        $row = [
            'name' => trim((string) ($data[$columnMap['name']] ?? '')),
            'sin' => isset($columnMap['sin']) ? trim((string) ($data[$columnMap['sin']] ?? '')) : '',
            'email' => isset($columnMap['email']) ? strtolower(trim((string) ($data[$columnMap['email']] ?? ''))) : '',
            'phone' => isset($columnMap['phone']) ? trim((string) ($data[$columnMap['phone']] ?? '')) : '',
            'company' => isset($columnMap['company']) ? trim((string) ($data[$columnMap['company']] ?? '')) : '',
            'date_of_birth' => isset($columnMap['date_of_birth']) ? trim((string) ($data[$columnMap['date_of_birth']] ?? '')) : '',
            'notes' => isset($columnMap['notes']) ? trim((string) ($data[$columnMap['notes']] ?? '')) : '',
            '_line' => $line,
        ];
        if ($row['name'] === '' && $row['email'] === '' && $row['phone'] === '' && $row['sin'] === '') {
            continue;
        }
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}
