<?php

declare(strict_types=1);

/**
 * Splits notes into the structured lines written by the importer and the free-form remainder.
 *
 * @return array{status: string, last_activity: string, other_phone: string, notes: string}
 */
function client_notes_meta(?string $notes): array
{
    $meta = ['status' => '', 'last_activity' => '', 'other_phone' => '', 'notes' => ''];
    $rest = [];
    foreach (preg_split('/\R/', (string) $notes) ?: [] as $line) {
        $trimmed = trim($line);
        if (preg_match('/^Processing status:\s*(.+)$/i', $trimmed, $m)) {
            $meta['status'] = trim($m[1]);
        } elseif (preg_match('/^Last activity:\s*(.+)$/i', $trimmed, $m)) {
            $meta['last_activity'] = trim($m[1]);
        } elseif (preg_match('/^Other phone:\s*(.+)$/i', $trimmed, $m)) {
            $meta['other_phone'] = trim($m[1]);
        } else {
            $rest[] = rtrim($line);
        }
    }
    $meta['notes'] = trim(implode("\n", $rest));
    return $meta;
}

/** @param array{status?: string, last_activity?: string, other_phone?: string, notes?: string} $meta */
function client_compose_notes(array $meta): string
{
    $lines = [];
    $free = trim((string) ($meta['notes'] ?? ''));
    if ($free !== '') {
        $lines[] = $free;
    }
    foreach ([
        'other_phone' => 'Other phone: ',
        'status' => 'Processing status: ',
        'last_activity' => 'Last activity: ',
    ] as $key => $prefix) {
        $value = trim(preg_replace('/\s+/', ' ', (string) ($meta[$key] ?? '')) ?? '');
        if ($value !== '') {
            $lines[] = $prefix . $value;
        }
    }
    return implode("\n", $lines);
}

/** @return list<string> */
function client_known_statuses(): array
{
    return [
        'EFILE acknowledgement OK',
        'Ready for EFILE',
        'Ready to print',
        'Printed',
        'Data OK',
        'Review required',
        'No return',
        'No data this year',
        'Inactive file',
        'Inactive by user',
        'No longer a client',
    ];
}

/** @return 'success'|'info'|'warning'|'danger'|'muted' */
function client_status_tone(string $status): string
{
    $s = strtolower(trim($status));
    return match (true) {
        $s === '' => 'muted',
        str_contains($s, 'acknowledgement ok'), $s === 'printed', $s === 'data ok' => 'success',
        str_contains($s, 'no longer'), str_contains($s, 'inactive') => 'muted',
        str_contains($s, 'review') => 'danger',
        str_contains($s, 'no return'), str_contains($s, 'no data') => 'warning',
        default => 'info',
    };
}

function client_source_label(string $source): string
{
    return match ($source) {
        'import' => 'Imported',
        'manual' => 'Manual',
        default => 'Form',
    };
}

/** Filing statuses that mean the client no longer works with the firm. */
function client_status_implies_inactive(string $status): bool
{
    $s = strtolower(trim($status));
    return $s !== '' && (str_contains($s, 'no longer a client') || str_contains($s, 'inactive'));
}

/** @return array<string, string> */
function client_provinces(): array
{
    return [
        'ON' => 'Ontario',
        'QC' => 'Quebec',
        'BC' => 'British Columbia',
        'AB' => 'Alberta',
        'MB' => 'Manitoba',
        'SK' => 'Saskatchewan',
        'NS' => 'Nova Scotia',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'PE' => 'Prince Edward Island',
        'YT' => 'Yukon',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
    ];
}

/** @return array<string, string> */
function client_contact_filters(): array
{
    return [
        'has_email' => 'Has email',
        'no_email' => 'Missing email',
        'no_phone' => 'Missing phone',
        'unsubscribed' => 'Unsubscribed',
    ];
}

/** @return array<string, string> */
function client_sort_options(): array
{
    return [
        'name' => 'Name (A–Z)',
        'name_desc' => 'Name (Z–A)',
        'newest' => 'Newest added',
        'oldest' => 'Oldest added',
        'updated' => 'Recently updated',
    ];
}

/**
 * Client list filters from the query string; invalid values are dropped.
 *
 * @return array{status: string, filing: string, province: string, contact: string, source: string, sort: string}
 */
function client_list_filters_from_request(array $query): array
{
    $pick = static function (string $key, array $allowed) use ($query): string {
        $value = trim((string) ($query[$key] ?? ''));
        return in_array($value, $allowed, true) ? $value : '';
    };

    return [
        'status' => $pick('status', ['active', 'inactive']),
        'filing' => $pick('filing', array_merge(client_known_statuses(), ['none'])),
        'province' => $pick('province', array_keys(client_provinces())),
        'contact' => $pick('contact', array_keys(client_contact_filters())),
        'source' => $pick('source', ['submission', 'import', 'manual']),
        'sort' => $pick('sort', array_keys(client_sort_options())),
    ];
}

function client_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    if ($parts === []) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function client_mask_sin(?string $sin): string
{
    $digits = preg_replace('/\D/', '', (string) $sin) ?? '';
    if ($digits === '') {
        return '';
    }
    return '•••-•••-' . substr($digits, -3);
}

/** "12 Main St, Ottawa, ON K1A 0B1" → "Ottawa, ON" (best effort for addresses built by the importer). */
function client_address_locality(?string $address): string
{
    $parts = array_values(array_filter(
        array_map('trim', explode(',', (string) $address)),
        static fn (string $p): bool => $p !== ''
    ));
    if (count($parts) < 2) {
        return '';
    }
    $region = $parts[count($parts) - 1];
    $city = $parts[count($parts) - 2];
    if (preg_match('/^([A-Z]{2})\b/', $region, $m)) {
        return $city . ', ' . $m[1];
    }
    return count($parts) >= 3 ? $city : $region;
}

/**
 * Split a one-line client address ("Street, City, ON A1A 1A1[, Country]") into parts.
 *
 * @return array{street: string, city: string, province: string, postal: string, country: string}
 */
function client_address_parts(?string $address): array
{
    $out = ['street' => '', 'city' => '', 'province' => '', 'postal' => '', 'country' => ''];
    $parts = array_values(array_filter(
        array_map('trim', preg_split('/\s*(?:,|\R)\s*/', trim((string) $address)) ?: []),
        static fn (string $p): bool => $p !== ''
    ));
    if ($parts === []) {
        return $out;
    }

    $countries = ['canada' => 'Canada', 'ca' => 'Canada', 'can' => 'Canada', 'usa' => 'USA', 'us' => 'USA', 'united states' => 'USA'];
    if (count($parts) > 1 && isset($countries[strtolower($parts[count($parts) - 1])])) {
        $out['country'] = $countries[strtolower(array_pop($parts))];
    }

    $provinces = client_provinces();
    $provinceIndex = null;
    for ($i = count($parts) - 1; $i >= 1; $i--) {
        $segment = $parts[$i];
        if (preg_match('/\b([A-Z]\d[A-Z])\s?(\d[A-Z]\d)\b/i', $segment, $pm)) {
            $out['postal'] = strtoupper($pm[1] . ' ' . $pm[2]);
            $segment = trim(str_replace($pm[0], '', $segment));
        }
        $province = '';
        if (preg_match('/^([A-Z]{2})$/i', $segment, $cm) && isset($provinces[strtoupper($cm[1])])) {
            $province = $provinces[strtoupper($cm[1])];
        } else {
            foreach ($provinces as $name) {
                if (strcasecmp($segment, $name) === 0) {
                    $province = $name;
                    break;
                }
            }
        }
        if ($province !== '' || $out['postal'] !== '') {
            $out['province'] = $province;
            $provinceIndex = $i;
            break;
        }
    }

    if ($provinceIndex !== null) {
        $parts = array_slice($parts, 0, $provinceIndex);
    }
    if (count($parts) >= 2 || ($provinceIndex !== null && count($parts) === 1 && !preg_match('/\d/', $parts[0]))) {
        $out['city'] = (string) array_pop($parts);
    }
    $out['street'] = implode(', ', $parts);
    if ($out['country'] === '' && ($out['province'] !== '' || $out['postal'] !== '')) {
        $out['country'] = 'Canada';
    }

    return $out;
}

/** Full province name from a code ("ON") or name ("ontario"); unknown values are returned trimmed. */
function client_province_name(?string $province): string
{
    $province = trim((string) $province);
    if ($province === '') {
        return '';
    }
    $provinces = client_provinces();
    if (isset($provinces[strtoupper($province)])) {
        return $provinces[strtoupper($province)];
    }
    foreach ($provinces as $name) {
        if (strcasecmp($province, $name) === 0) {
            return $name;
        }
    }
    return $province;
}

function client_province_code(?string $province): string
{
    $name = client_province_name($province);
    $code = array_search($name, client_provinces(), true);
    return $code === false ? '' : (string) $code;
}

function client_normalize_postal(?string $postal): string
{
    $postal = strtoupper(trim((string) $postal));
    if (preg_match('/^([A-Z]\d[A-Z])\s*-?\s*(\d[A-Z]\d)$/', $postal, $m)) {
        return $m[1] . ' ' . $m[2];
    }
    return $postal;
}

/**
 * Clean address parts as stored on the client record.
 *
 * @param array<string, mixed> $parts street/city/province/postal/country
 * @return array{street: string, city: string, province: string, postal: string, country: string}
 */
function client_address_normalize(array $parts): array
{
    $clean = static fn (mixed $v): string => trim(preg_replace('/\s+/', ' ', (string) $v) ?? '');
    $out = [
        'street' => $clean($parts['street'] ?? ''),
        'city' => $clean($parts['city'] ?? ''),
        'province' => client_province_name($clean($parts['province'] ?? '')),
        'postal' => client_normalize_postal($clean($parts['postal'] ?? '')),
        'country' => $clean($parts['country'] ?? ''),
    ];
    $hasLocation = $out['street'] !== '' || $out['city'] !== '' || $out['postal'] !== '';
    if (!$hasLocation) {
        return ['street' => '', 'city' => '', 'province' => '', 'postal' => '', 'country' => ''];
    }
    if ($out['country'] === '' && ($out['province'] !== '' || $out['postal'] !== '')) {
        $out['country'] = 'Canada';
    }
    return $out;
}

/** One-line address ("Street, City, ON A1A 1A1") used for display, search and filters. */
function client_address_compose(array $parts): string
{
    $parts = client_address_normalize($parts);
    $code = client_province_code($parts['province']);
    $region = trim(($code !== '' ? $code : $parts['province']) . ' ' . $parts['postal']);
    $country = in_array(strtolower($parts['country']), ['canada', 'ca', 'can'], true) ? '' : $parts['country'];

    return implode(', ', array_filter(
        [$parts['street'], $parts['city'], $region, $country],
        static fn (string $v): bool => $v !== ''
    ));
}

/**
 * Address parts for a client row, falling back to parsing the one-line address.
 *
 * @param array<string, mixed>|null $client
 * @return array{street: string, city: string, province: string, postal: string, country: string}
 */
function client_address_from_row(?array $client): array
{
    $parts = client_address_normalize([
        'street' => $client['address_street'] ?? '',
        'city' => $client['address_city'] ?? '',
        'province' => $client['address_province'] ?? '',
        'postal' => $client['address_postal'] ?? '',
        'country' => $client['address_country'] ?? '',
    ]);
    if ($parts['street'] !== '' || $parts['city'] !== '' || $parts['postal'] !== '') {
        return $parts;
    }
    return client_address_parts((string) ($client['address'] ?? ''));
}

/**
 * Address fields from a form submission (street/city/province/postal/country, or one combined field).
 *
 * @return array{street: string, city: string, province: string, postal: string, country: string}
 */
function client_extract_address_from_submission(array $submission, array $schema): array
{
    $data = json_decode((string) ($submission['data_json'] ?? '{}'), true) ?: [];
    $found = ['street' => '', 'city' => '', 'province' => '', 'postal' => '', 'country' => ''];

    foreach ($schema['fields'] ?? [] as $field) {
        $fieldName = (string) ($field['name'] ?? '');
        $haystack = strtolower($fieldName) . ' ' . strtolower((string) ($field['label'] ?? ''));
        if (preg_match('/\b(spouse|partner|employer|dependant|dependent)\b/', $haystack)) {
            continue;
        }
        $raw = $data[$fieldName] ?? '';
        $val = is_array($raw) ? trim(implode(', ', array_map('strval', $raw))) : trim((string) $raw);
        if ($val === '') {
            continue;
        }

        if ($found['street'] === '' && preg_match('/\b(street|address\s*line|addr|address)\b/', $haystack)
            && !preg_match('/\b(email|e-mail)\b/', $haystack)) {
            $found['street'] = $val;
        } elseif ($found['city'] === '' && preg_match('/\b(city|town|municipality)\b/', $haystack)) {
            $found['city'] = $val;
        } elseif ($found['province'] === '' && preg_match('/\b(province|state|territory)\b/', $haystack)) {
            $found['province'] = $val;
        } elseif ($found['postal'] === '' && preg_match('/\b(postal|postcode|zip)\b/', $haystack)) {
            $found['postal'] = $val;
        } elseif ($found['country'] === '' && preg_match('/\bcountry\b/', $haystack)) {
            $found['country'] = $val;
        }
    }

    if ($found['city'] === '' && $found['postal'] === '' && str_contains($found['street'], ',')) {
        $parsed = client_address_parts($found['street']);
        foreach ($parsed as $key => $value) {
            if ($found[$key] === '' || $key === 'street') {
                $found[$key] = $value;
            }
        }
    }

    return client_address_normalize($found);
}

function client_age(?string $dob): ?int
{
    $dob = birthday_normalize_dob($dob);
    if ($dob === null) {
        return null;
    }
    try {
        return (new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;
    } catch (Throwable) {
        return null;
    }
}

/** @return array{name: string, sin: string, email: string, phone: string, company: string, date_of_birth: string, address: array{street: string, city: string, province: string, postal: string, country: string}} */
function extract_client_from_submission(array $submission, array $schema): array
{
    $data = json_decode((string) ($submission['data_json'] ?? '{}'), true) ?: [];
    $name = '';
    $sin = '';
    $email = '';
    $phone = '';
    $company = '';
    $dob = '';
    $firstName = '';
    $middleName = '';
    $lastName = '';

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
        } elseif (preg_match('/\b(spouse|partner|dependant|dependent|child|employer)\b/', $haystack)) {
            continue;
        } elseif (preg_match('/\b(first|given)[\s_-]*name\b/', $haystack)) {
            $firstName = $firstName !== '' ? $firstName : $val;
        } elseif (preg_match('/\bmiddle[\s_-]*name\b/', $haystack)) {
            $middleName = $middleName !== '' ? $middleName : $val;
        } elseif (preg_match('/\b(last|family|sur)[\s_-]*name\b|\bsurname\b/', $haystack)) {
            $lastName = $lastName !== '' ? $lastName : $val;
        } elseif (preg_match('/\b(full|client|contact|customer)\s+name\b|\b(client|customer)\b|\bname\b/', $haystack)
            && !preg_match('/\b(user|user\s*name|file|image|form)\s*name\b/', $haystack)) {
            $name = $name !== '' ? $name : $val;
        }
    }

    if ($name === '' && ($firstName !== '' || $lastName !== '')) {
        $name = trim(implode(' ', array_filter([$firstName, $middleName, $lastName], static fn (string $v): bool => $v !== '')));
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
        'address' => client_extract_address_from_submission($submission, $schema),
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

function map_client_csv_column(string $normalizedHeader): ?string
{
    return match (true) {
        in_array($normalizedHeader, ['name', 'fullname', 'clientname', 'contactname', 'customername', 'client'], true) => 'name',
        in_array($normalizedHeader, ['firstname', 'givenname', 'fname'], true) => 'first_name',
        in_array($normalizedHeader, ['lastname', 'surname', 'familyname', 'lname'], true) => 'last_name',
        in_array($normalizedHeader, ['sin', 'cin', 'socialinsurancenumber', 'socialinsurance', 'clientid', 'clientnumber', 'clientno', 'idnumber'], true) => 'sin',
        in_array($normalizedHeader, ['email', 'emailaddress', 'mail'], true) => 'email',
        in_array($normalizedHeader, ['phone', 'phonenumber', 'telephone', 'tel', 'contactnumber'], true) => 'phone',
        in_array($normalizedHeader, ['mobile', 'cell', 'cellphone', 'cellphonenumber', 'mobilephone', 'mobilenumber'], true) => 'phone_cell',
        in_array($normalizedHeader, ['homephone', 'homephonenumber'], true) => 'phone_home',
        in_array($normalizedHeader, ['workphone', 'workphonenumber', 'businessphone'], true) => 'phone_work',
        in_array($normalizedHeader, ['company', 'business', 'organization', 'organisation', 'firm', 'businessname'], true) => 'company',
        in_array($normalizedHeader, ['dateofbirth', 'dob', 'birthdate', 'birthday', 'birth', 'datebirth'], true) => 'date_of_birth',
        in_array($normalizedHeader, ['address', 'fulladdress', 'mailingaddress'], true) => 'address',
        in_array($normalizedHeader, ['street', 'streetaddress', 'address1', 'addressline1'], true) => 'street',
        in_array($normalizedHeader, ['city', 'town'], true) => 'city',
        in_array($normalizedHeader, ['province', 'prov', 'pro', 'state', 'provincestate'], true) => 'province',
        in_array($normalizedHeader, ['postalcode', 'postal', 'postalc', 'postcode', 'zip', 'zipcode'], true) => 'postal_code',
        in_array($normalizedHeader, ['country', 'count'], true) => 'country',
        in_array($normalizedHeader, ['processingstatus', 'status', 'clientstatus'], true) => 'status',
        in_array($normalizedHeader, ['lastactivity', 'lastact', 'lastmodified'], true) => 'last_activity',
        in_array($normalizedHeader, ['notes', 'note', 'comments', 'comment', 'remarks'], true) => 'notes',
        default => null,
    };
}

/** Strips "H:" / "W:" / "C:" style prefixes that tax software puts on phone cells. */
function client_csv_clean_phone(string $raw): string
{
    $raw = trim($raw);
    $raw = preg_replace('/^[A-Za-z]{1,4}\s*:\s*/', '', $raw) ?? $raw;
    return trim($raw);
}

/** Converts "08May26" (tax software last-activity format) to 2026-05-08; returns the input otherwise. */
function client_csv_format_activity_date(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^\d{2}[A-Za-z]{3}\d{2}$/', $raw)) {
        $dt = DateTimeImmutable::createFromFormat('!dMy', $raw);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
    }
    return $raw;
}

/** @param array<string, string> $cells */
function client_csv_build_address(array $cells): string
{
    $address = trim($cells['address'] ?? '');
    if ($address !== '') {
        return $address;
    }

    $regionLine = trim(implode(' ', array_filter([
        trim($cells['province'] ?? ''),
        strtoupper(trim($cells['postal_code'] ?? '')),
    ], static fn (string $v): bool => $v !== '')));

    $country = trim($cells['country'] ?? '');
    if (in_array(strtolower($country), ['canada', 'ca', 'can'], true)) {
        $country = '';
    }

    return implode(', ', array_filter([
        trim($cells['street'] ?? ''),
        trim($cells['city'] ?? ''),
        $regionLine,
        $country,
    ], static fn (string $v): bool => $v !== ''));
}

/** Lines the importer writes into notes; replaced (not duplicated) on re-import. */
const CLIENT_IMPORT_NOTE_PREFIXES = ['Processing status:', 'Last activity:', 'Other phone:'];

/**
 * Keeps manually written note lines and replaces previously imported status lines.
 */
function client_merge_import_notes(string $existing, string $imported): string
{
    $lines = [];
    $seen = [];
    foreach (preg_split('/\R/', $existing) ?: [] as $line) {
        $trimmed = trim($line);
        foreach (CLIENT_IMPORT_NOTE_PREFIXES as $prefix) {
            if (str_starts_with($trimmed, $prefix)) {
                continue 2;
            }
        }
        $lines[] = rtrim($line);
        if ($trimmed !== '') {
            $seen[$trimmed] = true;
        }
    }

    foreach (preg_split('/\R/', $imported) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || isset($seen[$trimmed])) {
            continue;
        }
        $lines[] = $trimmed;
        $seen[$trimmed] = true;
    }

    return trim(implode("\n", $lines));
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

    // Tax software exports (e.g. TaxCycle) put the last name under "Name" and the first name in an unlabeled next column.
    if (
        isset($columnMap['name'])
        && !isset($columnMap['first_name'])
        && !isset($columnMap['last_name'])
        && array_key_exists($columnMap['name'] + 1, $headerRow)
        && trim((string) $headerRow[$columnMap['name'] + 1]) === ''
    ) {
        $columnMap['last_name'] = $columnMap['name'];
        $columnMap['first_name'] = $columnMap['name'] + 1;
        unset($columnMap['name']);
    }

    if (!isset($columnMap['name']) && !isset($columnMap['first_name']) && !isset($columnMap['last_name'])) {
        fclose($handle);
        throw new RuntimeException('CSV must include a Name column (e.g. Name, Full Name, Client Name, or First/Last Name).');
    }

    $rows = [];
    $line = 1;
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line++;
        if ($data === [null] || $data === false) {
            continue;
        }

        $cells = [];
        foreach ($columnMap as $field => $idx) {
            $cells[$field] = trim((string) ($data[$idx] ?? ''));
        }

        $name = $cells['name'] ?? '';
        if ($name === '') {
            $first = $cells['first_name'] ?? '';
            $last = $cells['last_name'] ?? '';
            if ($first === '*') {
                $first = '';
            }
            $name = $last === '' ? '' : trim($first . ' ' . $last);
        }

        $phones = array_values(array_unique(array_filter([
            client_csv_clean_phone($cells['phone'] ?? ''),
            client_csv_clean_phone($cells['phone_cell'] ?? ''),
            client_csv_clean_phone($cells['phone_home'] ?? ''),
            client_csv_clean_phone($cells['phone_work'] ?? ''),
        ], static fn (string $v): bool => $v !== '')));

        $noteLines = [];
        if (($cells['notes'] ?? '') !== '') {
            $noteLines[] = $cells['notes'];
        }
        if (count($phones) > 1) {
            $noteLines[] = 'Other phone: ' . implode(', ', array_slice($phones, 1));
        }
        if (($cells['status'] ?? '') !== '') {
            $noteLines[] = 'Processing status: ' . $cells['status'];
        }
        if (($cells['last_activity'] ?? '') !== '') {
            $noteLines[] = 'Last activity: ' . client_csv_format_activity_date($cells['last_activity']);
        }

        $row = [
            'name' => $name,
            'sin' => $cells['sin'] ?? '',
            'email' => strtolower($cells['email'] ?? ''),
            'phone' => $phones[0] ?? '',
            'company' => $cells['company'] ?? '',
            'address' => client_csv_build_address($cells),
            'date_of_birth' => $cells['date_of_birth'] ?? '',
            'notes' => implode("\n", $noteLines),
            '_line' => $line,
        ];
        // Skips blank rows and stray fragments of rows the exporter split across lines.
        if ($row['name'] === '' && $row['email'] === '' && $row['phone'] === '' && $row['sin'] === '' && $row['date_of_birth'] === '') {
            continue;
        }
        $rows[] = $row;
    }

    fclose($handle);
    return $rows;
}
