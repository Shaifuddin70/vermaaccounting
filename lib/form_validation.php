<?php
declare(strict_types=1);

/**
 * Shared form field validation for public submit and admin submission edit.
 */

/** @return list<string> */
function form_field_option_values(array $field): array
{
    $values = [];
    foreach ($field['options'] ?? [] as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $values[] = (string) ($opt['value'] ?? '');
    }

    return array_values(array_filter($values, static fn (string $v): bool => $v !== ''));
}

/**
 * Raw posted answers keyed by field id (for condition evaluation).
 *
 * @return array<string, mixed>
 */
function collect_posted_answers_by_id(array $schema, array $post): array
{
    $answers = [];
    foreach ($schema['fields'] ?? [] as $field) {
        if (!is_array($field) || empty($field['id']) || empty($field['type']) || empty($field['name'])) {
            continue;
        }
        $type = (string) $field['type'];
        if (in_array($type, ['heading', 'paragraph', 'page_break'], true)) {
            continue;
        }
        $id = (string) $field['id'];
        $name = (string) $field['name'];

        if ($type === 'checkbox') {
            $raw = $post[$name] ?? [];
            $answers[$id] = is_array($raw) ? array_map('strval', $raw) : [];
            continue;
        }

        if (in_array($type, ['file', 'image'], true)) {
            continue;
        }

        $answers[$id] = trim((string) ($post[$name] ?? ''));
    }

    return $answers;
}

function form_condition_rule_matches(array $rule, array $answersById): bool
{
    $fieldId = (string) ($rule['field'] ?? '');
    $val = $answersById[$fieldId] ?? '';
    $target = $rule['value'] ?? '';
    $operator = (string) ($rule['operator'] ?? 'equals');

    switch ($operator) {
        case 'equals':
            return (string) $val === (string) $target;
        case 'not_equals':
            return (string) $val !== (string) $target;
        case 'contains':
            return str_contains(strtolower((string) $val), strtolower((string) $target));
        case 'empty':
            return $val === '' || $val === [] || (is_array($val) && count($val) === 0);
        case 'not_empty':
            return !($val === '' || $val === [] || (is_array($val) && count($val) === 0));
        default:
            return false;
    }
}

/** Whether a field should be shown (and validated) given current answers. */
function form_field_is_visible(array $field, array $answersById): bool
{
    $conditions = $field['conditions'] ?? [];
    if (!is_array($conditions) || $conditions === []) {
        return true;
    }

    $block = $conditions[0] ?? null;
    if (!is_array($block)) {
        return true;
    }

    $rules = $block['rules'] ?? [];
    if (!is_array($rules) || $rules === []) {
        return true;
    }

    $results = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $results[] = form_condition_rule_matches($rule, $answersById);
    }
    if ($results === []) {
        return true;
    }

    $logic = (string) ($block['logic'] ?? 'all');
    $match = $logic === 'any'
        ? in_array(true, $results, true)
        : !in_array(false, $results, true);

    $action = (string) ($block['action'] ?? 'show');

    return $action === 'hide' ? !$match : $match;
}

/** Validate a date string (Y-m-d) and optional min-age. Returns error or null. */
function validate_date_field_value(string $label, string $value, array $field = []): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value, app_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (
        $dt === false
        || (($errors['warning_count'] ?? 0) > 0)
        || (($errors['error_count'] ?? 0) > 0)
        || $dt->format('Y-m-d') !== $value
    ) {
        return $label . ' must be a valid date.';
    }

    $minAge = normalize_min_age($field['minAge'] ?? 0);

    return validate_date_min_age($label, $value, $minAge);
}

function validate_email_field_value(string $label, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return $label . ' must be a valid email.';
    }

    return null;
}

function validate_plain_number_field_value(string $label, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
        return $label . ' must be a valid number.';
    }

    return null;
}

/**
 * Validate a Yes/No follow-up value for its configured type.
 */
function validate_yes_no_reason_value(string $label, string $value, string $reasonType = 'textarea'): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $reasonType = normalize_yes_no_reason_type($reasonType);

    return match ($reasonType) {
        'email' => validate_email_field_value($label, $value),
        'number' => validate_plain_number_field_value($label, $value),
        'date' => validate_date_field_value($label, $value, []),
        'tel' => (preg_match('/\d/', $value) ? null : ($label . ' must include a phone number.')),
        default => null,
    };
}

/**
 * Validate select/radio against allowed options.
 */
function validate_choice_field_value(string $label, string $value, array $field, bool $required): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $required ? ($label . ' is required.') : null;
    }

    $allowed = form_field_option_values($field);
    if ($allowed !== [] && !in_array($value, $allowed, true)) {
        return $label . ': please choose a valid option.';
    }

    return null;
}

/**
 * Validate checkbox selections against allowed options.
 *
 * @param list<string> $values
 */
function validate_checkbox_field_values(string $label, array $values, array $field, bool $required): ?string
{
    $values = array_values(array_filter(array_map('strval', $values), static fn (string $v): bool => $v !== ''));
    if ($values === []) {
        return $required ? ($label . ' is required.') : null;
    }

    $allowed = form_field_option_values($field);
    if ($allowed === []) {
        return null;
    }
    foreach ($values as $v) {
        if (!in_array($v, $allowed, true)) {
            return $label . ': please choose valid options.';
        }
    }

    return null;
}

/**
 * Normalize + validate a scalar (non-file, non-partners, non-checkbox) field.
 *
 * @return array{value: string, errors: list<string>, extra: array<string, string>}
 */
function validate_scalar_form_field(array $field, string $value, bool $visible, array $post = []): array
{
    $type = (string) ($field['type'] ?? 'text');
    $label = (string) ($field['label'] ?? 'Field');
    $required = !empty($field['required']);
    $value = trim($value);
    $errors = [];
    $extra = [];

    if (!$visible) {
        return ['value' => '', 'errors' => [], 'extra' => []];
    }

    if ($type === 'yes_no') {
        $value = in_array($value, ['yes', 'no'], true) ? $value : '';
        if ($required && $value === '') {
            $errors[] = $label . ' is required.';
        }

        foreach (yes_no_follow_ups_for_answer($field, $value) as $followUp) {
            $reasonKey = yes_no_follow_up_storage_key((string) $field['name'], $followUp);
            $reasonVal = trim((string) ($post[$reasonKey] ?? ''));
            $reasonLabel = (string) ($followUp['label'] ?? 'Reason');
            if (!empty($followUp['required']) && $reasonVal === '') {
                $errors[] = $reasonLabel . ' is required.';
            }
            $reasonError = validate_yes_no_reason_value($reasonLabel, $reasonVal, (string) ($followUp['type'] ?? 'textarea'));
            if ($reasonError !== null) {
                $errors[] = $reasonError;
            }
            $extra[$reasonKey] = $reasonVal;
        }

        return ['value' => $value, 'errors' => $errors, 'extra' => $extra];
    }

    if ($required && $value === '') {
        $errors[] = $label . ' is required.';
    }

    if ($value === '') {
        return ['value' => '', 'errors' => $errors, 'extra' => []];
    }

    if ($type === 'email') {
        $err = validate_email_field_value($label, $value);
        if ($err !== null) {
            $errors[] = $err;
        }
    }

    if ($type === 'number') {
        $format = normalize_number_format((string) ($field['numberFormat'] ?? ''));
        if ($format !== '') {
            $value = apply_number_format($value, $format);
            $formatError = validate_number_format_value($label, $value, $format, false);
            if ($formatError !== null) {
                $errors[] = $formatError;
            }
        } else {
            $err = validate_plain_number_field_value($label, $value);
            if ($err !== null) {
                $errors[] = $err;
            }
        }
    }

    if ($type === 'tel') {
        $phoneError = validate_phone_field_value($label, $value, $field, false);
        if ($phoneError !== null) {
            $errors[] = $phoneError;
        }
    }

    if ($type === 'date') {
        $dateError = validate_date_field_value($label, $value, $field);
        if ($dateError !== null) {
            $errors[] = $dateError;
        }
    }

    if (in_array($type, ['select', 'radio'], true)) {
        $choiceError = validate_choice_field_value($label, $value, $field, false);
        if ($choiceError !== null) {
            $errors[] = $choiceError;
        }
    }

    if ($type === 'text' || $type === 'textarea') {
        // Presence already handled; reject oversized payloads.
        if (strlen($value) > 20000) {
            $errors[] = $label . ' is too long.';
        }
    }

    return ['value' => $value, 'errors' => $errors, 'extra' => []];
}
