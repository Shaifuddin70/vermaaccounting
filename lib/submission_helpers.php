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
