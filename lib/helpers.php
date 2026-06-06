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

/** Field types that render in a two-column row on the public form. */
function form_field_uses_half_column(string $type, array $field = []): bool
{
    if (in_array($type, ['text', 'email', 'tel', 'number', 'date', 'select'], true)) {
        return true;
    }
    if ($type === 'yes_no') {
        return empty($field['reasonWhen']);
    }
    return false;
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
            'subject' => 'We received your submission — {form_title}',
        ],
    ];

    $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
    $mail = array_replace_recursive($defaults, $mail);

    if (($mail['admin_email'] ?? '') === '' && ($config['admin_notification_email'] ?? '') !== '') {
        $mail['admin_email'] = (string) $config['admin_notification_email'];
    }

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
        'yes_no' => 'Yes / No',
        'file' => 'File upload',
        'image' => 'Image upload',
        'heading' => 'Section heading',
        'paragraph' => 'Paragraph text',
    ];
}

function default_field(string $type = 'text'): array
{
    $id = 'f_' . bin2hex(random_bytes(4));
    $base = [
        'id' => $id,
        'type' => $type,
        'label' => field_types()[$type] ?? 'Field',
        'name' => $id,
        'required' => !in_array($type, ['heading', 'paragraph', 'checkbox'], true),
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
        $base['reasonWhen'] = '';
        $base['reasonLabel'] = 'Please explain your answer';
        $base['reasonPlaceholder'] = '';
        $base['reasonRequired'] = true;
    }

    if (in_array($type, ['heading', 'paragraph'], true)) {
        $base['required'] = false;
        $base['label'] = $type === 'heading' ? 'Section title' : 'Instructions for the user…';
    }

    if (in_array($type, ['file', 'image'], true)) {
        $base['accept'] = $type === 'image' ? 'image/*' : '';
        $base['maxFiles'] = 1;
    }

    return $base;
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
            $when = (string) ($merged['reasonWhen'] ?? '');
            $merged['reasonWhen'] = in_array($when, ['yes', 'no'], true) ? $when : '';
            $merged['reasonLabel'] = trim((string) ($merged['reasonLabel'] ?? 'Please explain your answer'));
            $merged['reasonPlaceholder'] = (string) ($merged['reasonPlaceholder'] ?? '');
            $merged['reasonRequired'] = !empty($merged['reasonRequired']);
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
