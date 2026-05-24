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

    return [
        'version' => 1,
        'settings' => array_merge([
            'submitLabel' => 'Submit',
            'successMessage' => 'Thank you! Your response has been received.',
        ], is_array($schema['settings'] ?? null) ? $schema['settings'] : []),
        'fields' => $fields,
    ];
}
