<?php

declare(strict_types=1);

/** Published form slug used by public Contact Us / inquiry forms. */
function contact_form_slug(): string
{
    return 'contact';
}

/**
 * Default schema for the site contact form (editable later in Form Builder).
 *
 * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
 */
function contact_form_default_schema(): array
{
    $services = [
        'Bookkeeping',
        'Financial Accounting',
        'Payroll',
        'Personal Tax Preparation',
        'Corporate Tax Services',
        'Business Registration',
        'Other',
    ];

    return [
        'fields' => [
            [
                'id' => 'contact_name',
                'type' => 'text',
                'label' => 'Name',
                'name' => 'name',
                'required' => true,
                'placeholder' => 'Name',
            ],
            [
                'id' => 'contact_phone',
                'type' => 'text',
                'label' => 'Phone',
                'name' => 'phone',
                'required' => true,
                'placeholder' => 'Phone',
            ],
            [
                'id' => 'contact_email',
                'type' => 'email',
                'label' => 'Email',
                'name' => 'email',
                'required' => true,
                'placeholder' => 'Email',
            ],
            [
                'id' => 'contact_service',
                'type' => 'select',
                'label' => 'Service',
                'name' => 'service',
                'required' => false,
                'placeholder' => 'Select a Service',
                'options' => array_map(
                    static fn (string $label): array => ['label' => $label, 'value' => $label],
                    $services
                ),
            ],
            [
                'id' => 'contact_message',
                'type' => 'textarea',
                'label' => 'Message',
                'name' => 'message',
                'required' => true,
                'placeholder' => 'Message',
            ],
        ],
        'settings' => [
            'successMessage' => 'Thanks for your submission! We will get back to you soon.',
        ],
    ];
}

/**
 * Ensure the published Contact Us form exists so public pages can submit into admin.
 * Does not overwrite an existing form (admins can customize it in Form Builder).
 */
function ensure_contact_form(): void
{
    $repo = new FormRepository();
    $slug = contact_form_slug();
    if ($repo->findBySlug($slug, false) !== null) {
        return;
    }

    $repo->create([
        'slug' => $slug,
        'title' => 'Contact Us',
        'description' => 'Website contact and inquiry form submissions.',
        'schema' => contact_form_default_schema(),
        'status' => 'published',
    ]);
}
