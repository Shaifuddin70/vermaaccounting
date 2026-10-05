<?php
declare(strict_types=1);

/**
 * "Advance payment" custom form field: the client picks a payment method, and each
 * method can ask for a payment reference and/or a screenshot of the payment.
 *
 * Stored in submissions.data_json as:
 *   {name}            => method value
 *   {name}_reference  => reference text
 *   {name}_screenshot => stored file path(s)
 */

/** @return array<string, string> */
function payment_input_modes(): array
{
    return [
        'off' => 'Don’t ask',
        'optional' => 'Optional',
        'required' => 'Required',
    ];
}

function payment_input_mode(mixed $mode, string $default = 'off'): string
{
    $mode = (string) $mode;
    return array_key_exists($mode, payment_input_modes()) ? $mode : $default;
}

function payment_screenshot_accept(): string
{
    return 'image/*,.pdf,application/pdf';
}

/** @return list<array<string, string>> */
function payment_default_methods(): array
{
    return [
        [
            'value' => 'e_transfer',
            'label' => 'E-transfer',
            'instructions' => 'Send an Interac e-Transfer to info@vermaaccounting.ca.',
            'reference' => 'required',
            'screenshot' => 'required',
            'recordAs' => 'e_transfer',
        ],
        [
            'value' => 'card_payment',
            'label' => 'Card payment',
            'instructions' => '',
            'reference' => 'required',
            'screenshot' => 'optional',
            'recordAs' => 'credit_card',
        ],
    ];
}

/** Best-guess invoice payment method key for a form payment method label. */
function payment_guess_invoice_method(string $label): string
{
    $l = strtolower($label);
    return match (true) {
        str_contains($l, 'transfer') && !str_contains($l, 'bank') && !str_contains($l, 'wire') => 'e_transfer',
        str_contains($l, 'interac') => 'e_transfer',
        str_contains($l, 'debit') => 'debit',
        str_contains($l, 'card') || str_contains($l, 'credit') => 'credit_card',
        str_contains($l, 'cash') => 'cash',
        str_contains($l, 'cheque') || str_contains($l, 'check') => 'cheque',
        str_contains($l, 'bank') || str_contains($l, 'wire') || str_contains($l, 'deposit') => 'bank_transfer',
        default => 'other',
    };
}

/** Parse a builder amount ("$1,250.5") into a normalized "1250.50" string, or '' if none. */
function payment_normalize_amount(mixed $raw): string
{
    $clean = str_replace([',', '$', ' ', 'CAD', 'cad'], '', trim((string) $raw));
    if ($clean === '' || !is_numeric($clean) || (float) $clean <= 0) {
        return '';
    }
    return number_format(min((float) $clean, 9999999.99), 2, '.', '');
}

function payment_field_amount(array $field): float
{
    $amount = payment_normalize_amount($field['amount'] ?? '');
    return $amount === '' ? 0.0 : (float) $amount;
}

function payment_format_amount(float $amount): string
{
    return '$' . number_format($amount, 2) . ' CAD';
}

/** @return array<string, mixed> */
function payment_field_defaults(): array
{
    return [
        'label' => 'Advance payment',
        'amount' => '',
        'referenceLabel' => 'Payment reference',
        'screenshotLabel' => 'Payment screenshot',
        'options' => payment_default_methods(),
    ];
}

/** @param array<string, mixed> $field */
function normalize_payment_field(array $field): array
{
    $field['amount'] = payment_normalize_amount($field['amount'] ?? '');
    $field['referenceLabel'] = mb_substr(trim((string) ($field['referenceLabel'] ?? '')), 0, 120) ?: 'Payment reference';
    $field['screenshotLabel'] = mb_substr(trim((string) ($field['screenshotLabel'] ?? '')), 0, 120) ?: 'Payment screenshot';

    $methods = [];
    $seen = [];
    foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $label = mb_substr(trim((string) ($opt['label'] ?? '')), 0, 80);
        if ($label === '') {
            continue;
        }
        $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', (string) ($opt['value'] ?? '')));
        $value = trim($value, '_');
        if ($value === '') {
            $value = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label)), '_') ?: 'method';
        }
        $base = $value;
        $n = 2;
        while (isset($seen[$value])) {
            $value = $base . '_' . $n++;
        }
        $seen[$value] = true;

        $methods[] = [
            'value' => $value,
            'label' => $label,
            'instructions' => mb_substr(trim((string) ($opt['instructions'] ?? '')), 0, 2000),
            'reference' => payment_input_mode($opt['reference'] ?? 'off'),
            'screenshot' => payment_input_mode($opt['screenshot'] ?? 'off'),
            'recordAs' => array_key_exists((string) ($opt['recordAs'] ?? ''), invoice_payment_methods())
                ? (string) $opt['recordAs']
                : payment_guess_invoice_method($label),
        ];
    }
    $field['options'] = $methods ?: payment_default_methods();

    return $field;
}

/** @return list<array<string, string>> */
function payment_methods(array $field): array
{
    return normalize_payment_field($field)['options'];
}

/** @return array<string, string>|null */
function payment_method_for_value(array $field, string $value): ?array
{
    foreach (payment_methods($field) as $method) {
        if ($method['value'] === $value) {
            return $method;
        }
    }
    return null;
}

function payment_method_label(array $field, string $value): string
{
    $method = payment_method_for_value($field, $value);
    return $method['label'] ?? $value;
}

/** @return array{reference: string, screenshot: string} */
function payment_storage_keys(string $name): array
{
    return [
        'reference' => $name . '_reference',
        'screenshot' => $name . '_screenshot',
    ];
}

function payment_method_icon(string $label): string
{
    $l = strtolower($label);
    return match (true) {
        str_contains($l, 'transfer') || str_contains($l, 'interac') => 'fa-money-bill-transfer',
        str_contains($l, 'card') || str_contains($l, 'credit') || str_contains($l, 'debit') => 'fa-credit-card',
        str_contains($l, 'cash') => 'fa-money-bill-wave',
        str_contains($l, 'cheque') || str_contains($l, 'check') => 'fa-money-check',
        str_contains($l, 'bank') || str_contains($l, 'wire') || str_contains($l, 'deposit') => 'fa-building-columns',
        default => 'fa-wallet',
    };
}

/** Per-method reference input, shaped like a regular field for render/validate. */
function payment_method_reference_field(array $field, array $method): array
{
    return [
        'id' => $field['id'] . '__pay__' . $method['value'] . '__ref',
        'type' => 'text',
        'name' => $field['name'] . '_ref_' . $method['value'],
        'label' => (string) ($field['referenceLabel'] ?? 'Payment reference'),
        'required' => ($method['reference'] ?? 'off') === 'required',
        'placeholder' => 'e.g. confirmation or transaction number',
        'helpText' => '',
        'options' => [],
        'conditions' => [],
    ];
}

/** Per-method screenshot upload, shaped like a file field for staging/claiming. */
function payment_method_screenshot_field(array $field, array $method): array
{
    return [
        'id' => $field['id'] . '__pay__' . $method['value'] . '__shot',
        'type' => 'file',
        'name' => $field['name'] . '_shot_' . $method['value'],
        'label' => (string) ($field['screenshotLabel'] ?? 'Payment screenshot'),
        'required' => ($method['screenshot'] ?? 'off') === 'required',
        'placeholder' => '',
        'helpText' => '',
        'options' => [],
        'conditions' => [],
        'accept' => payment_screenshot_accept(),
        'maxFiles' => 3,
    ];
}

/** Resolve a payment screenshot pseudo-field id (used by the staging upload API). */
function payment_find_screenshot_field(array $field, string $pseudoId): ?array
{
    foreach (payment_methods($field) as $method) {
        if ($method['screenshot'] === 'off') {
            continue;
        }
        $pseudo = payment_method_screenshot_field($field, $method);
        if ($pseudo['id'] === $pseudoId) {
            return $pseudo;
        }
    }
    return null;
}

/** True if a submission_files.field_id belongs to this payment field. */
function payment_file_belongs_to_field(array $field, string $fileFieldId): bool
{
    return str_starts_with($fileFieldId, (string) $field['id'] . '__pay__');
}

/**
 * Validate a posted payment field and claim its screenshot uploads.
 *
 * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} data entries + filesMeta
 */
function payment_field_from_post(
    array $field,
    array $form,
    array $schema,
    string $uploadSession,
    array $post,
    int $maxBytes,
    array $allowedMimes,
    array &$errors
): array {
    $name = (string) $field['name'];
    $label = (string) $field['label'];
    $keys = payment_storage_keys($name);
    $out = [$name => '', $keys['reference'] => '', $keys['screenshot'] => ''];

    $value = trim((string) ($post[$name] ?? ''));
    if ($value === '') {
        if (!empty($field['required'])) {
            $errors[] = 'Please choose a payment method for ' . $label . '.';
        }
        return [$out, []];
    }

    $method = payment_method_for_value($field, $value);
    if ($method === null) {
        $errors[] = 'Please choose a valid payment method for ' . $label . '.';
        return [$out, []];
    }
    $out[$name] = $method['value'];

    if ($method['reference'] !== 'off') {
        $refField = payment_method_reference_field($field, $method);
        $reference = trim((string) ($post[$refField['name']] ?? ''));
        if ($reference === '' && $refField['required']) {
            $errors[] = $refField['label'] . ' is required.';
        } elseif (mb_strlen($reference) > 200) {
            $errors[] = $refField['label'] . ' is too long.';
        }
        $out[$keys['reference']] = mb_substr($reference, 0, 200);
    }

    $filesMeta = [];
    if ($method['screenshot'] !== 'off') {
        $shotField = payment_method_screenshot_field($field, $method);
        [$fileValue, $filesMeta] = process_field_file_uploads(
            $shotField,
            $form,
            $schema,
            $uploadSession,
            $post,
            $maxBytes,
            $allowedMimes,
            $errors
        );
        $out[$keys['screenshot']] = $fileValue;
    }

    return [$out, $filesMeta];
}

/** One-line summary for emails and exports, e.g. "E-transfer — Reference: ABC123". */
function payment_submission_summary(array $field, array $data): string
{
    $name = (string) ($field['name'] ?? '');
    $value = trim((string) ($data[$name] ?? ''));
    if ($value === '') {
        return '';
    }
    $keys = payment_storage_keys($name);
    $parts = [payment_method_label($field, $value)];
    $amount = payment_field_amount($field);
    if ($amount > 0) {
        $parts[] = 'Amount: ' . payment_format_amount($amount);
    }
    $reference = trim((string) ($data[$keys['reference']] ?? ''));
    if ($reference !== '') {
        $parts[] = ($field['referenceLabel'] ?? 'Reference') . ': ' . $reference;
    }
    $shots = $data[$keys['screenshot']] ?? '';
    $shotCount = is_array($shots) ? count(array_filter($shots)) : (trim((string) $shots) !== '' ? 1 : 0);
    if ($shotCount > 0) {
        $parts[] = ($field['screenshotLabel'] ?? 'Screenshot') . ': ' . $shotCount . ' file' . ($shotCount > 1 ? 's' : '') . ' uploaded';
    }
    return implode(' — ', $parts);
}
