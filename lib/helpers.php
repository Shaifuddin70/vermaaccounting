<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Root-relative URL for CSS, JS, images (works on /form/slug and all clean URLs). */
function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

/** Clean app URL without .php extension (requires Apache mod_rewrite). */
function app_url(string $path, array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    if (str_ends_with(strtolower($path), '.php')) {
        $path = substr($path, 0, -4);
    }
    if ($path === '/index') {
        $path = '/';
    }
    if ($path === '/admin/index') {
        $path = '/admin';
    }
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    return $path;
}

/** Field types that render in a two-column row on the public form. */
function form_field_uses_half_column(string $type, array $field = []): bool
{
    if (in_array($type, ['text', 'email', 'tel', 'number', 'date', 'select'], true)) {
        return true;
    }
    if ($type === 'partners') {
        return true;
    }
    if ($type === 'yes_no') {
        return yes_no_follow_ups($field) === [];
    }
    return false;
}

/**
 * Split schema fields into pages using page_break markers.
 * The page_break label becomes the title of the following page.
 *
 * @return list<array{title: string, fields: list<array<string, mixed>>}>
 */
function form_schema_pages(array $schema): array
{
    $pages = [];
    $fields = [];
    $firstTitle = trim((string) ($schema['settings']['firstPageTitle'] ?? ''));
    $nextTitle = $firstTitle !== '' ? $firstTitle : 'Page 1';

    foreach ($schema['fields'] ?? [] as $field) {
        if (!is_array($field)) {
            continue;
        }
        if (($field['type'] ?? '') === 'page_break') {
            $pages[] = [
                'title' => $nextTitle,
                'fields' => $fields,
            ];
            $fields = [];
            $label = trim((string) ($field['label'] ?? ''));
            $nextTitle = $label !== '' ? $label : ('Page ' . (count($pages) + 1));
            continue;
        }
        $fields[] = $field;
    }

    $pages[] = [
        'title' => $nextTitle,
        'fields' => $fields,
    ];

    while (count($pages) > 1 && $pages[count($pages) - 1]['fields'] === []) {
        array_pop($pages);
    }

    if ($pages === []) {
        $pages[] = ['title' => 'Page 1', 'fields' => []];
    }

    return $pages;
}

/** True when the form should show multi-step pagination. */
function form_has_multiple_pages(array $schema): bool
{
    return count(form_schema_pages($schema)) > 1;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'form';
}

function now_iso(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** @return list<string> */
function normalize_admin_emails(mixed $raw): array
{
    if (is_string($raw)) {
        $raw = preg_split('/[\s,;]+/', $raw) ?: [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[$email] = $email;
        }
    }

    return array_values($out);
}

/** @return array<string, mixed> */
function stored_mail_settings(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $repo = new SettingsRepository();
    $stored = $repo->getMailSettings();
    $cached = $stored;
    return $cached;
}

/** @return array<string, mixed> */
function mail_config(): array
{
    $config = app_config();
    $defaults = [
        'enabled' => false,
        'transport' => 'mail',
        'from_email' => '',
        'from_name' => 'Verma Accounting',
        'admin_email' => '',
        'admin_name' => 'Verma Accounting',
        'smtp' => [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
        ],
        'admin_notification' => [
            'enabled' => true,
            'subject' => 'New submission: {form_title} (#{submission_id})',
        ],
        'client_confirmation' => [
            'enabled' => true,
            'subject' => 'We received your submission ? {form_title}',
        ],
    ];

    $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
    $mail = array_replace_recursive($defaults, $mail);

    if (($mail['admin_email'] ?? '') === '' && ($config['admin_notification_email'] ?? '') !== '') {
        $mail['admin_email'] = (string) $config['admin_notification_email'];
    }

    $stored = stored_mail_settings();
    if ($stored !== []) {
        if (array_key_exists('admin_emails', $stored)) {
            $mail['admin_emails'] = normalize_admin_emails($stored['admin_emails']);
        }
        if (isset($stored['admin_notification']) && is_array($stored['admin_notification'])) {
            $mail['admin_notification'] = array_replace_recursive(
                $mail['admin_notification'],
                $stored['admin_notification']
            );
        }
        if (isset($stored['client_confirmation']) && is_array($stored['client_confirmation'])) {
            $mail['client_confirmation'] = array_replace_recursive(
                $mail['client_confirmation'],
                $stored['client_confirmation']
            );
        }
    }

    if (empty($mail['admin_emails'])) {
        $mail['admin_emails'] = normalize_admin_emails($mail['admin_email'] ?? '');
    }
    $mail['admin_email'] = $mail['admin_emails'][0] ?? trim((string) ($mail['admin_email'] ?? ''));

    return $mail;
}

function app_base_url(): string
{
    $config = app_config();
    $configured = trim((string) ($config['site_url'] ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    if (PHP_SAPI === 'cli') {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function brand_logo_path(): string
{
    return 'images/verma-accounting-logo.png';
}

function brand_logo_url(): string
{
    $base = app_base_url();
    $path = asset(brand_logo_path());
    return $base !== '' ? $base . $path : $path;
}

function brand_logo_img_html(string $class = '', int $width = 200, int $height = 48): string
{
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<img src="' . e(brand_logo_url()) . '" alt="Verma Accounting"'
        . $classAttr
        . ' width="' . $width . '" height="' . $height . '"'
        . ' decoding="async">';
}

function brand_logo_email_html(): string
{
    return '<img src="' . e(brand_logo_url()) . '" alt="Verma Accounting" width="200" height="48"'
        . ' style="display:block;margin:0 auto;max-width:200px;height:auto;border:0;">';
}

function app_environment(): string
{
    $env = app_config()['environment'] ?? 'production';
    return in_array($env, ['local', 'production'], true) ? $env : 'production';
}

function app_is_local(): bool
{
    return app_environment() === 'local';
}

function field_types(): array
{
    return [
        'text' => 'Short text',
        'textarea' => 'Long text',
        'email' => 'Email',
        'tel' => 'Phone',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'radio' => 'Single choice',
        'checkbox' => 'Multiple choice',
        'partners' => 'Partner reference',
        'yes_no' => 'Yes / No',
        'file' => 'File upload',
        'image' => 'Image upload',
        'heading' => 'Section heading',
        'paragraph' => 'Paragraph text',
        'page_break' => 'Page break',
    ];
}

/** Allowed input types for a Yes/No follow-up field (all interactive types). */
function yes_no_reason_types(): array
{
    return [
        'text' => 'Short text',
        'textarea' => 'Long text',
        'email' => 'Email',
        'tel' => 'Phone',
        'number' => 'Number',
        'date' => 'Date',
        'select' => 'Dropdown',
        'radio' => 'Single choice',
        'checkbox' => 'Multiple choice',
        'partners' => 'Partner reference',
        'file' => 'File upload',
        'image' => 'Image upload',
    ];
}

function normalize_yes_no_reason_type(mixed $type): string
{
    $type = strtolower(trim((string) $type));
    return array_key_exists($type, yes_no_reason_types()) ? $type : 'textarea';
}

/**
 * Normalize Yes/No follow-up fields. Migrates legacy single reason* settings.
 *
 * @return list<array<string, mixed>>
 */
function normalize_yes_no_follow_ups(array $field): array
{
    $raw = $field['followUps'] ?? null;
    $followUps = [];

    if (is_array($raw) && $raw !== []) {
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $when = (string) ($item['when'] ?? '');
            if (!in_array($when, ['yes', 'no'], true)) {
                continue;
            }
            $id = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) ($item['id'] ?? '')) ?: ('fu_' . bin2hex(random_bytes(3)));
            $type = normalize_yes_no_reason_type($item['type'] ?? 'textarea');
            $fu = [
                'id' => $id,
                'when' => $when,
                'type' => $type,
                'label' => trim((string) ($item['label'] ?? 'Please explain your answer')) ?: 'Please explain your answer',
                'placeholder' => (string) ($item['placeholder'] ?? ''),
                'required' => !empty($item['required']),
                'legacy' => !empty($item['legacy']) || $id === 'legacy_reason',
                'helpText' => (string) ($item['helpText'] ?? ''),
                'options' => [],
                'numberFormat' => '',
                'minAge' => 0,
                'phoneCountry' => 'CA',
                'phoneFormat' => phone_format_for_country('CA'),
                'phoneAllowCountrySelect' => true,
                'accept' => '',
                'maxFiles' => 5,
                'partnerInput' => 'select',
                'partnerIds' => [],
            ];

            if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
                $opts = [];
                foreach ($item['options'] ?? [] as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $val = trim((string) ($opt['value'] ?? ''));
                    $lab = trim((string) ($opt['label'] ?? $val));
                    if ($val === '') {
                        continue;
                    }
                    $opts[] = ['value' => $val, 'label' => $lab !== '' ? $lab : $val];
                }
                if ($opts === []) {
                    $opts = [
                        ['value' => 'option_1', 'label' => 'Option 1'],
                        ['value' => 'option_2', 'label' => 'Option 2'],
                    ];
                }
                $fu['options'] = $opts;
            }

            if ($type === 'number') {
                $fu['numberFormat'] = normalize_number_format((string) ($item['numberFormat'] ?? ''));
            }
            if ($type === 'date') {
                $fu['minAge'] = normalize_min_age($item['minAge'] ?? 0);
            }
            if ($type === 'tel') {
                $phone = normalize_phone_field_settings($item);
                $fu['phoneCountry'] = $phone['country'];
                $fu['phoneFormat'] = $phone['format'];
                $fu['phoneAllowCountrySelect'] = $phone['allowSelect'];
            }
            if (in_array($type, ['file', 'image'], true)) {
                $fu['accept'] = trim((string) ($item['accept'] ?? ($type === 'image' ? 'image/*' : form_file_accept_default())));
                $fu['maxFiles'] = max(1, min(10, (int) ($item['maxFiles'] ?? 5)));
            }
            if ($type === 'partners') {
                $input = (string) ($item['partnerInput'] ?? 'select');
                $fu['partnerInput'] = in_array($input, ['select', 'text', 'number'], true) ? $input : 'select';
                $ids = $item['partnerIds'] ?? [];
                $fu['partnerIds'] = array_values(array_unique(array_filter(
                    array_map('intval', is_array($ids) ? $ids : []),
                    static fn (int $pid): bool => $pid > 0
                )));
            }

            $followUps[] = $fu;
        }
    }

    if ($followUps === []) {
        $when = (string) ($field['reasonWhen'] ?? '');
        if (in_array($when, ['yes', 'no'], true)) {
            $followUps[] = [
                'id' => 'legacy_reason',
                'when' => $when,
                'type' => normalize_yes_no_reason_type($field['reasonType'] ?? 'textarea'),
                'label' => trim((string) ($field['reasonLabel'] ?? 'Please explain your answer')) ?: 'Please explain your answer',
                'placeholder' => (string) ($field['reasonPlaceholder'] ?? ''),
                'required' => array_key_exists('reasonRequired', $field) ? !empty($field['reasonRequired']) : true,
                'legacy' => true,
                'helpText' => '',
                'options' => [],
                'numberFormat' => '',
                'minAge' => 0,
                'phoneCountry' => 'CA',
                'phoneFormat' => phone_format_for_country('CA'),
                'phoneAllowCountrySelect' => true,
                'accept' => '',
                'maxFiles' => 5,
                'partnerInput' => 'select',
                'partnerIds' => [],
            ];
        }
    }

    return array_values($followUps);
}

/** Storage key for a Yes/No follow-up answer in submission data. */
function yes_no_follow_up_storage_key(string $fieldName, array $followUp): string
{
    if (!empty($followUp['legacy']) || ($followUp['id'] ?? '') === 'legacy_reason') {
        return $fieldName . '_reason';
    }
    $id = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) ($followUp['id'] ?? 'fu')) ?: 'fu';

    return $fieldName . '_fu_' . $id;
}

/** @return list<array<string, mixed>> */
function yes_no_follow_ups(array $field): array
{
    return normalize_yes_no_follow_ups($field);
}

/** Follow-ups that should show for a given Yes/No answer. */
function yes_no_follow_ups_for_answer(array $field, string $answer): array
{
    if (!in_array($answer, ['yes', 'no'], true)) {
        return [];
    }

    return array_values(array_filter(
        yes_no_follow_ups($field),
        static fn (array $fu): bool => ($fu['when'] ?? '') === $answer
    ));
}

/**
 * Build a pseudo field schema from a Yes/No follow-up (for render/validate/upload).
 *
 * @return array<string, mixed>
 */
function yes_no_follow_up_as_field(array $parentField, array $followUp): array
{
    $type = normalize_yes_no_reason_type($followUp['type'] ?? 'textarea');
    $parentId = (string) ($parentField['id'] ?? 'f');
    $fuId = (string) ($followUp['id'] ?? 'fu');

    $field = [
        'id' => $parentId . '__fu__' . $fuId,
        'type' => $type,
        'name' => yes_no_follow_up_storage_key((string) ($parentField['name'] ?? 'field'), $followUp),
        'label' => (string) ($followUp['label'] ?? 'Follow-up'),
        'required' => !empty($followUp['required']),
        'placeholder' => (string) ($followUp['placeholder'] ?? ''),
        'helpText' => (string) ($followUp['helpText'] ?? ''),
        'options' => is_array($followUp['options'] ?? null) ? $followUp['options'] : [],
        'conditions' => [],
        'numberFormat' => (string) ($followUp['numberFormat'] ?? ''),
        'minAge' => normalize_min_age($followUp['minAge'] ?? 0),
        'phoneCountry' => (string) ($followUp['phoneCountry'] ?? 'CA'),
        'phoneFormat' => (string) ($followUp['phoneFormat'] ?? ''),
        'phoneAllowCountrySelect' => array_key_exists('phoneAllowCountrySelect', $followUp)
            ? !empty($followUp['phoneAllowCountrySelect'])
            : true,
        'accept' => (string) ($followUp['accept'] ?? ''),
        'maxFiles' => max(1, min(10, (int) ($followUp['maxFiles'] ?? 5))),
        'partnerInput' => (string) ($followUp['partnerInput'] ?? 'select'),
        'partnerIds' => is_array($followUp['partnerIds'] ?? null) ? $followUp['partnerIds'] : [],
    ];

    if ($type === 'tel') {
        $phone = normalize_phone_field_settings($field);
        $field['phoneCountry'] = $phone['country'];
        $field['phoneFormat'] = $phone['format'];
        $field['phoneAllowCountrySelect'] = $phone['allowSelect'];
    }

    return $field;
}

function default_field(string $type = 'text'): array
{
    $id = 'f_' . bin2hex(random_bytes(4));
    $base = [
        'id' => $id,
        'type' => $type,
        'label' => field_types()[$type] ?? 'Field',
        'name' => $id,
        'required' => !in_array($type, ['heading', 'paragraph', 'page_break', 'checkbox', 'partners'], true),
        'placeholder' => '',
        'helpText' => '',
        'options' => [],
        'conditions' => [],
    ];

    if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
        $base['options'] = [
            ['value' => 'option_1', 'label' => 'Option 1'],
            ['value' => 'option_2', 'label' => 'Option 2'],
        ];
    }

    if ($type === 'yes_no') {
        $base['options'] = [
            ['value' => 'yes', 'label' => 'Yes'],
            ['value' => 'no', 'label' => 'No'],
        ];
        $base['followUps'] = [];
        // Legacy single-reason keys kept for older schemas until normalize migrates them.
        $base['reasonWhen'] = '';
        $base['reasonLabel'] = 'Please explain your answer';
        $base['reasonPlaceholder'] = '';
        $base['reasonRequired'] = true;
        $base['reasonType'] = 'textarea';
    }

    if ($type === 'partners') {
        $base['label'] = 'Partner reference';
        $base['required'] = false;
        $base['partnerInput'] = 'select';
        $base['partnerIds'] = [];
    }

    if (in_array($type, ['heading', 'paragraph'], true)) {
        $base['required'] = false;
        $base['label'] = $type === 'heading' ? 'Section title' : 'Instructions for the user?';
    }

    if ($type === 'page_break') {
        $base['required'] = false;
        $base['label'] = 'Next page';
        $base['conditions'] = [];
    }

    if (in_array($type, ['file', 'image'], true)) {
        $base['accept'] = $type === 'image' ? 'image/*' : form_file_accept_default();
        $base['maxFiles'] = 5;
    }

    if ($type === 'number') {
        $base['numberFormat'] = '';
    }

    if ($type === 'tel') {
        $base['label'] = 'Phone';
        $base['phoneCountry'] = 'CA';
        $base['phoneFormat'] = phone_format_for_country('CA');
        $base['phoneAllowCountrySelect'] = true;
    }

    if ($type === 'date') {
        $base['minAge'] = 0;
    }

    return $base;
}

/** Default accept attribute for general file upload fields (images, PDF, Office, ZIP). */
function form_file_accept_default(): string
{
    return 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,application/pdf,application/zip,application/x-zip-compressed';
}

/**
 * Normalize a number display format. Use # for each digit (e.g. ###-###-### for SIN).
 */
function normalize_number_format(string $format): string
{
    $format = trim($format);
    if ($format === '' || !str_contains($format, '#')) {
        return '';
    }
    if (!preg_match('/^[#\d\s\-\(\)\.\/\+]+$/u', $format)) {
        return '';
    }

    return $format;
}

/**
 * Common phone countries for the form phone field (ISO code => meta).
 *
 * @return array<string, array{name: string, dial: string, format: string}>
 */
function phone_countries(): array
{
    return [
        'CA' => ['name' => 'Canada', 'dial' => '+1', 'format' => '(###) ###-####'],
        'US' => ['name' => 'United States', 'dial' => '+1', 'format' => '(###) ###-####'],
        'GB' => ['name' => 'United Kingdom', 'dial' => '+44', 'format' => '#### ######'],
        'AU' => ['name' => 'Australia', 'dial' => '+61', 'format' => '### ### ###'],
        'IN' => ['name' => 'India', 'dial' => '+91', 'format' => '##### #####'],
        'BD' => ['name' => 'Bangladesh', 'dial' => '+880', 'format' => '####-######'],
        'PK' => ['name' => 'Pakistan', 'dial' => '+92', 'format' => '### #######'],
        'AE' => ['name' => 'United Arab Emirates', 'dial' => '+971', 'format' => '## ### ####'],
        'SA' => ['name' => 'Saudi Arabia', 'dial' => '+966', 'format' => '## ### ####'],
        'DE' => ['name' => 'Germany', 'dial' => '+49', 'format' => '### #######'],
        'FR' => ['name' => 'France', 'dial' => '+33', 'format' => '# ## ## ## ##'],
        'IT' => ['name' => 'Italy', 'dial' => '+39', 'format' => '### ### ####'],
        'ES' => ['name' => 'Spain', 'dial' => '+34', 'format' => '### ## ## ##'],
        'NL' => ['name' => 'Netherlands', 'dial' => '+31', 'format' => '# ########'],
        'IE' => ['name' => 'Ireland', 'dial' => '+353', 'format' => '## ### ####'],
        'NZ' => ['name' => 'New Zealand', 'dial' => '+64', 'format' => '## ### ####'],
        'SG' => ['name' => 'Singapore', 'dial' => '+65', 'format' => '#### ####'],
        'PH' => ['name' => 'Philippines', 'dial' => '+63', 'format' => '### ### ####'],
        'NG' => ['name' => 'Nigeria', 'dial' => '+234', 'format' => '### ### ####'],
        'ZA' => ['name' => 'South Africa', 'dial' => '+27', 'format' => '## ### ####'],
        'BR' => ['name' => 'Brazil', 'dial' => '+55', 'format' => '## #####-####'],
        'MX' => ['name' => 'Mexico', 'dial' => '+52', 'format' => '## #### ####'],
        'CN' => ['name' => 'China', 'dial' => '+86', 'format' => '### #### ####'],
        'JP' => ['name' => 'Japan', 'dial' => '+81', 'format' => '##-####-####'],
        'KR' => ['name' => 'South Korea', 'dial' => '+82', 'format' => '##-####-####'],
        'OTHER' => ['name' => 'Other', 'dial' => '+', 'format' => '##############'],
    ];
}

function normalize_phone_country(string $code): string
{
    $code = strtoupper(trim($code));
    $countries = phone_countries();
    return isset($countries[$code]) ? $code : 'CA';
}

function phone_format_for_country(string $code): string
{
    $code = normalize_phone_country($code);
    $format = (string) (phone_countries()[$code]['format'] ?? '');
    return normalize_number_format($format);
}

function phone_dial_for_country(string $code): string
{
    $code = normalize_phone_country($code);
    return (string) (phone_countries()[$code]['dial'] ?? '+1');
}

/** @return array{country: string, format: string, dial: string, allowSelect: bool} */
function normalize_phone_field_settings(array $field): array
{
    $country = normalize_phone_country((string) ($field['phoneCountry'] ?? 'CA'));
    $format = normalize_number_format((string) ($field['phoneFormat'] ?? ''));
    if ($format === '') {
        $format = phone_format_for_country($country);
    }
    $allowSelect = array_key_exists('phoneAllowCountrySelect', $field)
        ? !empty($field['phoneAllowCountrySelect'])
        : true;

    return [
        'country' => $country,
        'format' => $format,
        'dial' => phone_dial_for_country($country),
        'allowSelect' => $allowSelect,
    ];
}

function format_phone_display_value(string $dial, string $national): string
{
    $dial = trim($dial);
    $national = trim($national);
    if ($national === '') {
        return '';
    }
    if ($dial === '' || $dial === '+') {
        return $national;
    }
    return $dial . ' ' . $national;
}

/** Validate a phone field value; returns error message or null. */
function validate_phone_field_value(string $label, string $value, array $field, bool $required): ?string
{
    $settings = normalize_phone_field_settings($field);
    $value = trim($value);
    if ($value === '') {
        return $required ? ($label . ' is required.') : null;
    }

    $format = $settings['format'];
    $dial = $settings['dial'];

    if ($settings['allowSelect']) {
        $matched = null;
        $matchedDialLen = -1;
        foreach (phone_countries() as $code => $meta) {
            $countryDial = trim((string) ($meta['dial'] ?? ''));
            if ($countryDial === '' || $countryDial === '+') {
                continue;
            }
            $dialLen = strlen($countryDial);
            if ($dialLen <= $matchedDialLen) {
                continue;
            }
            if (str_starts_with($value, $countryDial . ' ') || str_starts_with($value, $countryDial)) {
                $matched = $meta;
                $matched['code'] = $code;
                $matchedDialLen = $dialLen;
            }
        }
        if ($matched !== null) {
            $dial = (string) $matched['dial'];
            $matchedFormat = normalize_number_format((string) ($matched['format'] ?? ''));
            if ($matchedFormat !== '') {
                $format = $matchedFormat;
            }
        }
    }

    if ($format === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $value) ?? '';
    $dialDigits = preg_replace('/\D+/', '', $dial) ?? '';
    if ($dialDigits !== '' && str_starts_with($digits, $dialDigits)) {
        $digits = substr($digits, strlen($dialDigits)) ?: '';
    }
    $expected = substr_count($format, '#');
    if ($expected > 0 && strlen($digits) !== $expected) {
        return $label . ' must match the phone format ' . $format . '.';
    }

    return null;
}

/** Apply a # digit mask to raw digits (used while typing / normalizing). */
function apply_number_format(string $raw, string $format): string
{
    $format = normalize_number_format($format);
    if ($format === '') {
        return trim($raw);
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    $maxDigits = substr_count($format, '#');
    if ($maxDigits > 0) {
        $digits = substr($digits, 0, $maxDigits);
    }

    $out = '';
    $di = 0;
    $len = strlen($digits);
    $fLen = strlen($format);
    for ($i = 0; $i < $fLen; $i++) {
        $ch = $format[$i];
        if ($ch === '#') {
            if ($di >= $len) {
                break;
            }
            $out .= $digits[$di];
            $di++;
        } elseif ($di < $len) {
            $out .= $ch;
        } else {
            break;
        }
    }

    return $out;
}

/** True when value fully matches the number format (all digit slots filled). */
function number_format_is_complete(string $value, string $format): bool
{
    $format = normalize_number_format($format);
    if ($format === '') {
        return true;
    }
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    $expected = substr_count($format, '#');

    return strlen($digits) === $expected && apply_number_format($digits, $format) === $value;
}

/** Validate a formatted number field value; returns error message or null. */
function validate_number_format_value(string $label, string $value, string $format, bool $required): ?string
{
    $format = normalize_number_format($format);
    if ($value === '') {
        return $required ? ($label . ' is required.') : null;
    }
    if ($format === '') {
        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $label . ' must be a valid number.';
        }

        return null;
    }
    if (!number_format_is_complete($value, $format)) {
        return $label . ' must match the format ' . $format . '.';
    }

    return null;
}

/** Minimum age in whole years (0 = no minimum). Capped at 120. */
function normalize_min_age(mixed $raw): int
{
    if (is_string($raw) && trim($raw) === '') {
        return 0;
    }
    $age = (int) $raw;

    return max(0, min(120, $age));
}

/**
 * Latest allowed birth date (Y-m-d) for a minimum age, or null if no minimum.
 */
function date_max_for_min_age(int $minAge): ?string
{
    $minAge = normalize_min_age($minAge);
    if ($minAge < 1) {
        return null;
    }

    $today = new DateTimeImmutable('today', app_timezone());

    return $today->modify('-' . $minAge . ' years')->format('Y-m-d');
}

/**
 * Validate a date value against min age. Returns error message or null.
 */
function validate_date_min_age(string $label, string $value, int $minAge): ?string
{
    $minAge = normalize_min_age($minAge);
    if ($value === '' || $minAge < 1) {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, app_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (
        $dt === false
        || (($errors['warning_count'] ?? 0) > 0)
        || (($errors['error_count'] ?? 0) > 0)
    ) {
        return $label . ' must be a valid date.';
    }

    $max = date_max_for_min_age($minAge);
    if ($max !== null && $dt->format('Y-m-d') > $max) {
        return $label . ' requires a minimum age of ' . $minAge . '.';
    }

    return null;
}

function normalize_form_schema(array $schema): array
{
    $fields = [];
    foreach ($schema['fields'] ?? [] as $field) {
        if (!is_array($field) || empty($field['type'])) {
            continue;
        }
        $type = (string) $field['type'];
        if (!array_key_exists($type, field_types())) {
            continue;
        }
        $merged = array_merge(default_field($type), $field);
        $merged['id'] = (string) ($merged['id'] ?? ('f_' . bin2hex(random_bytes(4))));
        $merged['name'] = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) ($merged['name'] ?? $merged['id'])) ?: $merged['id'];
        if ($type === 'yes_no') {
            $merged['followUps'] = normalize_yes_no_follow_ups($merged);
            // Keep legacy mirrors for older readers / partial UI updates.
            $first = $merged['followUps'][0] ?? null;
            if ($first) {
                $merged['reasonWhen'] = $first['when'];
                $merged['reasonLabel'] = $first['label'];
                $merged['reasonPlaceholder'] = $first['placeholder'];
                $merged['reasonRequired'] = $first['required'];
                $merged['reasonType'] = $first['type'];
            } else {
                $merged['reasonWhen'] = '';
                $merged['reasonLabel'] = 'Please explain your answer';
                $merged['reasonPlaceholder'] = '';
                $merged['reasonRequired'] = true;
                $merged['reasonType'] = 'textarea';
            }
        }
        if ($type === 'partners') {
            $input = (string) ($merged['partnerInput'] ?? 'select');
            $merged['partnerInput'] = in_array($input, ['select', 'text', 'number'], true) ? $input : 'select';
            $ids = $merged['partnerIds'] ?? [];
            $merged['partnerIds'] = array_values(array_unique(array_filter(
                array_map('intval', is_array($ids) ? $ids : []),
                static fn (int $id): bool => $id > 0
            )));
        }
        if (in_array($type, ['file', 'image'], true)) {
            $maxFiles = (int) ($merged['maxFiles'] ?? 1);
            $merged['maxFiles'] = max(1, min(10, $maxFiles));
        }
        if ($type === 'number') {
            $merged['numberFormat'] = normalize_number_format((string) ($merged['numberFormat'] ?? ''));
        }
        if ($type === 'tel') {
            $phone = normalize_phone_field_settings($merged);
            $merged['phoneCountry'] = $phone['country'];
            $merged['phoneFormat'] = $phone['format'];
            $merged['phoneAllowCountrySelect'] = $phone['allowSelect'];
        }
        if ($type === 'date') {
            $merged['minAge'] = normalize_min_age($merged['minAge'] ?? 0);
        }
        $fields[] = $merged;
    }

    $settings = array_merge([
        'submitLabel' => 'Submit',
        'successMessage' => 'Thank you! Your response has been received.',
        'taxYear' => normalize_tax_year_settings([]),
        'dataMatch' => normalize_data_match_settings([]),
    ], is_array($schema['settings'] ?? null) ? $schema['settings'] : []);

    $settings['taxYear'] = normalize_tax_year_settings(
        is_array($settings['taxYear'] ?? null) ? $settings['taxYear'] : []
    );
    $settings['dataMatch'] = normalize_data_match_settings(
        is_array($settings['dataMatch'] ?? null) ? $settings['dataMatch'] : [],
        $fields
    );

    return [
        'version' => 1,
        'settings' => $settings,
        'fields' => $fields,
    ];
}

/** @return array{enabled: bool, label: string, prompt: string, years: list<int>} */
function normalize_tax_year_settings(array $settings): array
{
    $tax = is_array($settings['taxYear'] ?? null) ? $settings['taxYear'] : $settings;

    $enabled = !empty($tax['enabled']);
    $label = trim((string) ($tax['label'] ?? '')) ?: 'Which tax year are you filing for?';
    $prompt = trim((string) ($tax['prompt'] ?? '')) ?: 'Select a year to continue to the form for that tax period.';

    $years = [];
    if (!empty($tax['years']) && is_array($tax['years'])) {
        foreach ($tax['years'] as $y) {
            $y = (int) $y;
            if ($y >= 1990 && $y <= 2100) {
                $years[] = $y;
            }
        }
    } else {
        $raw = trim((string) ($tax['yearsList'] ?? $tax['yearsText'] ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
                $y = (int) $part;
                if ($y >= 1990 && $y <= 2100) {
                    $years[] = $y;
                }
            }
        }
    }

    if (!$years && !empty($tax['yearFrom']) && !empty($tax['yearTo'])) {
        $from = (int) $tax['yearFrom'];
        $to = (int) $tax['yearTo'];
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        for ($y = $to; $y >= $from; $y--) {
            if ($y >= 1990 && $y <= 2100) {
                $years[] = $y;
            }
        }
    }

    if ($enabled && $years === []) {
        $current = (int) date('Y');
        for ($i = 0; $i < 6; $i++) {
            $years[] = $current - $i;
        }
    }

    $years = array_values(array_unique($years));
    rsort($years);

    return [
        'enabled' => $enabled,
        'label' => $label,
        'prompt' => $prompt,
        'years' => $years,
    ];
}

function form_tax_year_enabled(array $schema): bool
{
    return !empty($schema['settings']['taxYear']['enabled']);
}

/** @return list<int> */
function form_tax_year_options(array $schema): array
{
    return $schema['settings']['taxYear']['years'] ?? [];
}

/** Field types allowed as lookup / match keys. */
function form_data_matchable_field_types(): array
{
    return ['text', 'email', 'tel', 'number', 'date', 'select', 'radio', 'yes_no'];
}

/** @return array{enabled: bool, fieldIds: list<string>, title: string, message: string, confirmLabel: string, declineLabel: string} */
function normalize_data_match_settings(array $settings, array $formFields = []): array
{
    $dm = is_array($settings['dataMatch'] ?? null) ? $settings['dataMatch'] : $settings;

    $validIds = [];
    foreach ($formFields as $field) {
        $type = $field['type'] ?? '';
        $id = (string) ($field['id'] ?? '');
        if ($id !== '' && in_array($type, form_data_matchable_field_types(), true)) {
            $validIds[$id] = true;
        }
    }

    $fieldIds = [];
    foreach ($dm['fieldIds'] ?? [] as $fid) {
        $fid = (string) $fid;
        if ($fid !== '' && ($validIds === [] || isset($validIds[$fid]))) {
            $fieldIds[] = $fid;
        }
    }
    $fieldIds = array_values(array_unique($fieldIds));

    $enabled = !empty($dm['enabled']) && count($fieldIds) >= 2;

    return [
        'enabled' => $enabled,
        'fieldIds' => $fieldIds,
        'title' => trim((string) ($dm['title'] ?? '')) ?: 'We found your information',
        'message' => trim((string) ($dm['message'] ?? ''))
            ?: 'A previous submission matches what you entered. Would you like to fill this form with that saved information?',
        'confirmLabel' => trim((string) ($dm['confirmLabel'] ?? '')) ?: 'Yes, fill the form',
        'declineLabel' => trim((string) ($dm['declineLabel'] ?? '')) ?: 'No, start fresh',
    ];
}

function form_data_match_enabled(array $schema): bool
{
    return !empty($schema['settings']['dataMatch']['enabled']);
}

/** @return list<string> */
function form_data_match_field_ids(array $schema): array
{
    return $schema['settings']['dataMatch']['fieldIds'] ?? [];
}

function field_name_by_id(array $schema, string $fieldId): ?string
{
    foreach ($schema['fields'] ?? [] as $field) {
        if (($field['id'] ?? '') === $fieldId) {
            return (string) ($field['name'] ?? null);
        }
    }
    return null;
}

/**
 * Site-wide CTA form (homepage hero + header). Falls back to /contact when unset.
 *
 * @return array{url: string, hero_label: string, nav_label: string, form: ?array}
 */
function site_cta_resolve(): array
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $resolved = [
        'url'        => '/contact',
        'hero_label' => 'Get Free Consultation',
        'nav_label'  => '',
        'form'       => null,
    ];

    try {
        if (!class_exists('FormRepository', false)) {
            require_once __DIR__ . '/bootstrap.php';
        }
        $form = (new FormRepository())->findSiteCtaForm();
        if ($form) {
            $label = trim((string) ($form['cta_label'] ?? ''));
            $resolved['url'] = '/form/' . rawurlencode((string) $form['slug']);
            $resolved['hero_label'] = $label !== '' ? $label : 'Get Free Consultation';
            $resolved['nav_label'] = $label !== '' ? $label : (string) $form['title'];
            $resolved['form'] = $form;
        }
    } catch (Throwable) {
        // Keep defaults if DB unavailable
    }

    return $resolved;
}

function site_cta_url(): string
{
    return site_cta_resolve()['url'];
}

function site_cta_hero_label(): string
{
    return site_cta_resolve()['hero_label'];
}

function site_cta_nav_label(): string
{
    return site_cta_resolve()['nav_label'];
}

function file_manager_form_slug(): string
{
    return '__file-manager-storage__';
}

function is_file_manager_form(array $form): bool
{
    return ($form['slug'] ?? '') === file_manager_form_slug();
}

function uploads_quota_bytes(): int
{
    $config = app_config();
    return (int) ($config['uploads_quota_bytes'] ?? 15 * 1024 * 1024 * 1024);
}

function format_file_size(int $bytes): string
{
    if ($bytes < 1) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int) floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    $value = $bytes / (1024 ** $power);
    $decimals = $power === 0 ? 0 : ($value >= 100 ? 0 : ($value >= 10 ? 1 : 2));

    return number_format($value, $decimals) . ' ' . $units[$power];
}

function file_extension_label(?string $filename, ?string $mime = null): string
{
    $ext = strtoupper(pathinfo((string) $filename, PATHINFO_EXTENSION));
    if ($ext !== '') {
        return $ext;
    }
    if (is_string($mime) && str_contains($mime, '/')) {
        return strtoupper(explode('/', $mime, 2)[1]);
    }
    return 'FILE';
}
