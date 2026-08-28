<?php

declare(strict_types=1);

/** Published form slug for secure document uploads. */
function document_submission_form_slug(): string
{
    return 'submit-documents';
}

/**
 * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
 */
function document_submission_default_schema(): array
{
    return [
        'fields' => [
            [
                'id' => 'doc_customer_id',
                'type' => 'text',
                'label' => 'Client ID or email',
                'name' => 'customer_id',
                'required' => true,
                'placeholder' => 'Enter your client ID or email',
                'helpText' => 'Use the client ID number we gave you, or the email address on your account.',
            ],
            [
                'id' => 'doc_files',
                'type' => 'file',
                'label' => 'Upload documents',
                'name' => 'documents',
                'required' => true,
                'helpText' => 'PDF, images, or ZIP files. You can upload multiple files.',
                'maxFiles' => 10,
                'accept' => form_file_accept_default(),
            ],
            [
                'id' => 'doc_notes',
                'type' => 'textarea',
                'label' => 'Notes (optional)',
                'name' => 'notes',
                'required' => false,
                'placeholder' => 'Tax year, document list, or anything we should know…',
            ],
        ],
        'settings' => [
            'submitLabel' => 'Submit documents',
            'successMessage' => 'Your documents have been submitted successfully. Our team will review them shortly.',
        ],
    ];
}

function ensure_document_submission_form(): void
{
    $repo = new FormRepository();
    $slug = document_submission_form_slug();
    if ($repo->findBySlug($slug, false) === null) {
        $repo->create([
            'slug' => $slug,
            'title' => 'Submit Tax Documents',
            'description' => 'Securely upload tax documents using your client ID or email.',
            'schema' => document_submission_default_schema(),
            'status' => 'published',
        ]);
    }

    document_submission_sync_form_fields();
}

/** Keep the public document form field labels/types in sync after updates. */
function document_submission_sync_form_fields(): void
{
    $repo = new FormRepository();
    $form = $repo->findBySlug(document_submission_form_slug(), false);
    if ($form === null) {
        return;
    }

    $schema = $repo->decodeSchema($form);
    $defaults = document_submission_default_schema();
    $defaultCustomer = null;
    foreach ($defaults['fields'] as $field) {
        if (($field['name'] ?? '') === 'customer_id') {
            $defaultCustomer = $field;
            break;
        }
    }
    if ($defaultCustomer === null) {
        return;
    }

    $changed = false;
    foreach ($schema['fields'] as &$field) {
        if (($field['name'] ?? '') !== 'customer_id') {
            continue;
        }
        foreach (['type', 'label', 'placeholder', 'helpText'] as $key) {
            if (($field[$key] ?? null) !== ($defaultCustomer[$key] ?? null)) {
                $field[$key] = $defaultCustomer[$key];
                $changed = true;
            }
        }
    }
    unset($field);

    if ($changed) {
        $repo->update((int) $form['id'], ['schema' => $schema]);
    }
}

/**
 * Resolve a document submission reference to a client (numeric client ID or email).
 *
 * @return array<string, mixed>|null
 */
function document_submission_resolve_client(string $reference): ?array
{
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }

    $repo = new ClientRepository();

    if (preg_match('/^\d+$/', $reference)) {
        $client = $repo->find((int) $reference);
        if ($client !== null) {
            return $client;
        }
    }

    if (filter_var($reference, FILTER_VALIDATE_EMAIL)) {
        return $repo->findByEmail(strtolower($reference));
    }

    return null;
}

/**
 * @param array<string, mixed> $data
 * @param list<string> $errors
 */
function document_submission_validate(array $data, array &$errors): void
{
    $service = trim((string) ($data['tax_service_type'] ?? ''));
    if (!in_array($service, ['personal', 'business'], true)) {
        $errors[] = 'Please select Personal or Business tax service.';
    }

    $reference = trim((string) ($data['customer_id'] ?? ''));
    if ($reference === '') {
        $errors[] = 'Please enter your client ID or email.';
        return;
    }

    if (document_submission_resolve_client($reference) === null) {
        $errors[] = 'Client ID or email not found. Please check your details or contact us for help.';
    }
}

/**
 * @param array<string, mixed> $data
 */
function document_submission_after_save(int $submissionId, array $data): void
{
    $client = document_submission_resolve_client((string) ($data['customer_id'] ?? ''));
    if ($client === null) {
        return;
    }

    (new ClientRepository())->linkSubmission((int) $client['id'], $submissionId);
}

function document_submission_service_label(string $service): string
{
    return match ($service) {
        'personal' => 'Personal tax',
        'business' => 'Business tax',
        default => ucfirst($service),
    };
}
