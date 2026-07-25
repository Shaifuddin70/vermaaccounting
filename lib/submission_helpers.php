<?php

declare(strict_types=1);

function submission_status_label(string $status): string
{
    return $status === 'complete' ? 'Complete' : 'Pending';
}

function is_image_mime(?string $mime): bool
{
    return is_string($mime) && str_starts_with($mime, 'image/');
}

function field_label_by_id(array $schema, string $fieldId): string
{
    foreach ($schema['fields'] ?? [] as $field) {
        if (($field['id'] ?? '') === $fieldId) {
            return (string) ($field['label'] ?? $fieldId);
        }
    }
    return $fieldId;
}

function field_by_name(array $schema, string $name): ?array
{
    foreach ($schema['fields'] ?? [] as $field) {
        if (($field['name'] ?? '') === $name) {
            return $field;
        }
    }
    return null;
}

/** @return array<int, array> */
function files_by_field_id(array $files): array
{
    $map = [];
    foreach ($files as $file) {
        $map[$file['field_id']][] = $file;
    }
    return $map;
}

function format_submission_value(mixed $value): string
{
    if (is_array($value)) {
        return implode(', ', array_map('strval', $value));
    }
    return (string) $value;
}

function submission_tax_year_label(?array $submission): string
{
    if (!$submission) {
        return '';
    }
    $year = $submission['tax_year'] ?? null;
    return $year !== null && $year !== '' ? (string) (int) $year : '';
}

function submission_display_year(array $submission): int
{
    $year = $submission['tax_year'] ?? null;
    if ($year !== null && $year !== '') {
        return (int) $year;
    }
    $created = (string) ($submission['created_at'] ?? '');
    if (preg_match('/^(\d{4})/', $created, $m)) {
        return (int) $m[1];
    }
    return (int) date('Y');
}

/** @return array<int, list<array>> */
function group_submissions_by_year(array $submissions): array
{
    $grouped = [];
    foreach ($submissions as $sub) {
        $grouped[submission_display_year($sub)][] = $sub;
    }
    krsort($grouped, SORT_NUMERIC);
    return $grouped;
}

function sanitize_upload_filename_part(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[^\w\-. ]+/u', '_', $value) ?? '';
    $value = preg_replace('/[\s_]+/', '_', $value) ?? '';
    return trim($value, '._-');
}

function client_upload_tax_year(array $schema, array $post): int
{
    $taxYearCfg = $schema['settings']['taxYear'] ?? [];
    if (!empty($taxYearCfg['enabled'])) {
        $year = (int) ($post['tax_year'] ?? 0);
        if ($year > 0) {
            return $year;
        }
    }
    return (int) date('Y');
}

function prefixed_client_upload_name(string $originalName, int $year, string $sin): string
{
    $originalName = basename(str_replace('\\', '/', $originalName));
    if ($originalName === '') {
        $originalName = 'file';
    }

    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $safeBase = sanitize_upload_filename_part($base) ?: 'file';
    $safeExt = $ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';

    $yearPart = (string) max(1900, min(9999, $year));
    $sinPart = sanitize_upload_filename_part($sin);
    $prefix = $sinPart !== '' ? $yearPart . '_' . $sinPart . '_' : $yearPart . '_';

    if (str_starts_with($safeBase . $safeExt, $prefix) || str_starts_with($originalName, $prefix)) {
        return mb_strimwidth($originalName, 0, 512, '');
    }

    $name = $prefix . $safeBase . $safeExt;
    return mb_strimwidth($name, 0, 512, '');
}

function client_upload_original_name(string $originalName, array $schema, array $post): string
{
    $client = extract_client_from_submission(
        ['data_json' => json_encode($post, JSON_UNESCAPED_UNICODE)],
        $schema
    );
    $year = client_upload_tax_year($schema, $post);
    return prefixed_client_upload_name($originalName, $year, $client['sin']);
}

/**
 * Build a safe download basename from client name.
 * Example: Jane_Doe
 */
function submission_client_download_basename(array $submission, array $schema, string $fallback = 'submission'): string
{
    $client = extract_client_from_submission($submission, $schema);
    $namePart = sanitize_upload_filename_part((string) ($client['name'] ?? ''));

    if ($namePart === '') {
        $id = (int) ($submission['id'] ?? 0);
        return $id > 0 ? $fallback . '-' . $id : $fallback;
    }

    return mb_strimwidth($namePart, 0, 180, '');
}
