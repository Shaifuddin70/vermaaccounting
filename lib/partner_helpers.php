<?php

declare(strict_types=1);

/** @return list<int> */
function active_partner_ids(): array
{
    return array_map(
        static fn (array $row): int => (int) $row['id'],
        (new UserRepository())->activePartners()
    );
}

/** @return list<int> */
function normalize_partner_field_ids(array $field): array
{
    $ids = $field['partnerIds'] ?? [];
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $id): bool => $id > 0
    )));
}

function normalize_partner_input_type(array $field): string
{
    $input = (string) ($field['partnerInput'] ?? 'select');
    return in_array($input, ['select', 'text', 'number'], true) ? $input : 'select';
}

/** @return list<array<string, mixed>> */
function partners_for_field(array $field): array
{
    $ids = normalize_partner_field_ids($field);
    if ($ids === []) {
        return [];
    }

    $all = (new UserRepository())->activePartners();
    $idSet = array_flip($ids);
    return array_values(array_filter(
        $all,
        static fn (array $partner): bool => isset($idSet[(int) $partner['id']])
    ));
}

function resolve_partner_id_from_field_value(array $field, mixed $value): ?int
{
    if ($value === '' || $value === null) {
        return null;
    }

    $input = normalize_partner_input_type($field);
    $allowedIds = normalize_partner_field_ids($field);
    $userRepo = new UserRepository();

    if ($input === 'select') {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }
        if ($allowedIds !== [] && !in_array($id, $allowedIds, true)) {
            return null;
        }
        $user = $userRepo->find($id);
        return ($user && ($user['role'] ?? '') === 'partner' && ($user['status'] ?? '') === 'active') ? $id : null;
    }

    $code = trim((string) $value);
    if ($code === '') {
        return null;
    }

    $partner = $userRepo->findPartnerByReferenceCode($code, $allowedIds !== [] ? $allowedIds : null);
    return $partner ? (int) $partner['id'] : null;
}

/** @return list<int> */
function extract_partner_ids_from_submission_data(array $schema, array $data): array
{
    $ids = [];
    foreach ($schema['fields'] as $field) {
        if (($field['type'] ?? '') !== 'partners') {
            continue;
        }
        $value = $data[$field['name']] ?? '';
        if (is_array($value)) {
            foreach ($value as $item) {
                $partnerId = resolve_partner_id_from_field_value($field, $item);
                if ($partnerId !== null) {
                    $ids[$partnerId] = true;
                }
            }
            continue;
        }
        $partnerId = resolve_partner_id_from_field_value($field, $value);
        if ($partnerId !== null) {
            $ids[$partnerId] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

/**
 * @param array<string, mixed> $field
 * @return string Stored field value (partner id or reference code)
 */
function partners_field_from_post(array $field, array $post, array &$errors): string
{
    $name = (string) $field['name'];
    $value = trim((string) ($post[$name] ?? ''));
    $input = normalize_partner_input_type($field);
    $label = (string) ($field['label'] ?? 'Partner reference');

    if (!empty($field['required']) && $value === '') {
        $errors[] = $label . ' is required.';
        return '';
    }

    if ($value === '') {
        return '';
    }

    if ($input === 'select') {
        $id = (int) $value;
        $allowed = partners_for_field($field);
        $validIds = array_map(static fn (array $row): int => (int) $row['id'], $allowed);
        if ($id < 1 || !in_array($id, $validIds, true)) {
            $errors[] = $label . ': please choose a valid partner.';
            return '';
        }
        return (string) $id;
    }

    if ($input === 'number' && !preg_match('/^\d+$/', $value)) {
        $errors[] = $label . ': enter a valid reference number.';
        return '';
    }

    $partnerId = resolve_partner_id_from_field_value($field, $value);
    if ($partnerId === null) {
        $errors[] = $label . ': reference code not recognized.';
        return '';
    }

    return $value;
}

function sync_submission_partners_from_data(int $submissionId, array $schema, array $data): void
{
    (new FormRepository())->syncSubmissionPartners(
        $submissionId,
        extract_partner_ids_from_submission_data($schema, $data)
    );
}

function partner_user_id(): ?int
{
    if (Auth::userRole() !== 'partner') {
        return null;
    }

    $id = Auth::currentUser()['id'] ?? null;
    return $id ? (int) $id : null;
}

function format_partner_submission_value(mixed $value): string
{
    if ($value === '' || $value === null) {
        return '';
    }

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $formatted = format_partner_submission_value($item);
            if ($formatted !== '') {
                $parts[] = $formatted;
            }
        }
        return implode(', ', $parts);
    }

    $userRepo = new UserRepository();
    $id = (int) $value;
    if ($id > 0 && (string) $id === trim((string) $value)) {
        $user = $userRepo->find($id);
        if ($user && ($user['role'] ?? '') === 'partner') {
            $code = trim((string) ($user['reference_code'] ?? ''));
            return $code !== '' ? $user['name'] . ' (' . $code . ')' : (string) $user['name'];
        }
    }

    $partner = $userRepo->findPartnerByReferenceCode((string) $value);
    if ($partner) {
        return $partner['name'] . ' (' . $value . ')';
    }

    return (string) $value;
}

/** @param array<string, mixed> $form */
/** @param array<string, mixed> $submission */
function assert_submission_access(array $form, array $submission): void
{
    $role = Auth::userRole();
    if ($role === 'admin') {
        return;
    }

    if ($role === 'reviewer') {
        if (($form['status'] ?? '') !== 'published') {
            header('Location: /admin/reviewer-submissions');
            exit;
        }
        return;
    }

    if ($role === 'partner') {
        $partnerId = partner_user_id();
        $repo = new FormRepository();
        if (
            !$partnerId
            || ($form['status'] ?? '') !== 'published'
            || !$repo->partnerHasAccessToSubmission((int) $submission['id'], $partnerId)
        ) {
            $_SESSION['flash_error'] = 'You do not have access to that submission.';
            header('Location: /admin/reviewer-submissions');
            exit;
        }
    }
}

/** @param array<string, mixed> $form */
function assert_form_submissions_access(array $form): void
{
    $role = Auth::userRole();
    if ($role === 'admin') {
        return;
    }

    if (($form['status'] ?? '') !== 'published') {
        header('Location: /admin/reviewer-submissions');
        exit;
    }
}
