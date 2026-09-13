<?php

declare(strict_types=1);

/** Published form slug for the KeenTax-style personal tax intake. */
function tax_intake_form_slug(): string
{
    return 'tax-intake';
}

/**
 * @param list<array<string, mixed>> $followUps
 * @return array<string, mixed>
 */
function tax_intake_yes_no(
    string $id,
    string $name,
    string $label,
    bool $required = true,
    string $helpText = '',
    array $followUps = []
): array {
    return [
        'id' => $id,
        'type' => 'yes_no',
        'label' => $label,
        'name' => $name,
        'required' => $required,
        'helpText' => $helpText,
        'options' => [
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
        ],
        'followUps' => $followUps,
    ];
}

/**
 * @return list<array{label: string, value: string}>
 */
function tax_intake_province_options(): array
{
    $provinces = [
        'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick',
        'Newfoundland and Labrador', 'Northwest Territories', 'Nova Scotia',
        'Nunavut', 'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan', 'Yukon',
    ];
    return array_map(
        static fn (string $p): array => ['label' => $p, 'value' => $p],
        $provinces
    );
}

/**
 * Multi-step personal tax intake schema (modeled on KeenTax-style intake flows).
 *
 * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
 */
function tax_intake_default_schema(): array
{
    $accept = function_exists('form_file_accept_default')
        ? form_file_accept_default()
        : 'image/*,.pdf,.zip,application/pdf,application/zip';

    return [
        'fields' => [
            // ── Page 1: About you ──────────────────────────────────────────
            [
                'id' => 'ti_about_heading',
                'type' => 'heading',
                'label' => 'About you',
                'name' => 'about_heading',
                'required' => false,
            ],
            [
                'id' => 'ti_about_intro',
                'type' => 'paragraph',
                'label' => 'Just answer a few simple questions and we\'ll take care of the rest. Your answers are sent securely over HTTPS.',
                'name' => 'about_intro',
                'required' => false,
            ],
            [
                'id' => 'ti_first_name',
                'type' => 'text',
                'label' => 'First name',
                'name' => 'first_name',
                'required' => true,
                'placeholder' => 'Enter exactly as it appears on your SIN or passport',
            ],
            [
                'id' => 'ti_last_name',
                'type' => 'text',
                'label' => 'Last name',
                'name' => 'last_name',
                'required' => true,
                'placeholder' => 'Enter your last name',
            ],
            [
                'id' => 'ti_dob',
                'type' => 'date',
                'label' => 'Date of birth',
                'name' => 'date_of_birth',
                'required' => true,
                'minAge' => 16,
            ],
            [
                'id' => 'ti_sin',
                'type' => 'number',
                'label' => 'Social Insurance Number (SIN)',
                'name' => 'sin',
                'required' => false,
                'placeholder' => '###-###-###',
                'helpText' => 'Optional for now — we may ask for it later to file with CRA.',
                'numberFormat' => '###-###-###',
            ],
            [
                'id' => 'ti_email',
                'type' => 'email',
                'label' => 'Email address',
                'name' => 'email',
                'required' => true,
                'placeholder' => 'you@example.com',
            ],
            [
                'id' => 'ti_phone',
                'type' => 'tel',
                'label' => 'Phone number',
                'name' => 'phone',
                'required' => true,
                'placeholder' => '(###) ###-####',
                'phoneCountry' => 'CA',
                'phoneFormat' => '(###) ###-####',
                'phoneAllowCountrySelect' => true,
            ],
            [
                'id' => 'ti_address',
                'type' => 'text',
                'label' => 'Street address',
                'name' => 'address',
                'required' => true,
                'placeholder' => 'Enter your street number and street name',
            ],
            [
                'id' => 'ti_city',
                'type' => 'text',
                'label' => 'City',
                'name' => 'city',
                'required' => true,
                'placeholder' => 'City',
            ],
            [
                'id' => 'ti_province',
                'type' => 'select',
                'label' => 'Province / Territory',
                'name' => 'province',
                'required' => true,
                'placeholder' => 'Select…',
                'options' => tax_intake_province_options(),
            ],
            [
                'id' => 'ti_postal',
                'type' => 'text',
                'label' => 'Postal code',
                'name' => 'postal_code',
                'required' => true,
                'placeholder' => 'A1A 1A1',
            ],
            [
                'id' => 'ti_marital',
                'type' => 'select',
                'label' => 'What is your current marital status?',
                'name' => 'marital_status',
                'required' => true,
                'placeholder' => 'Select…',
                'options' => [
                    ['label' => 'Single', 'value' => 'Single'],
                    ['label' => 'Married', 'value' => 'Married'],
                    ['label' => 'Living common-law', 'value' => 'Common-law'],
                    ['label' => 'Separated', 'value' => 'Separated'],
                    ['label' => 'Divorced', 'value' => 'Divorced'],
                    ['label' => 'Widowed', 'value' => 'Widowed'],
                ],
            ],
            tax_intake_yes_no(
                'ti_citizen',
                'canadian_citizen',
                'Are you a Canadian citizen?',
                true,
                'Select Yes if you emigrated and are filing as a part-year resident only when applicable.'
            ),
            tax_intake_yes_no(
                'ti_filed_before',
                'filed_before',
                'Have you filed taxes before?',
                true,
                'Select No if this is your first time filing taxes in Canada.',
                [
                    [
                        'id' => 'first_filing_year',
                        'when' => 'no',
                        'type' => 'number',
                        'label' => 'What year are you filing for the first time?',
                        'required' => true,
                        'placeholder' => 'e.g. 2025',
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_newcomer',
                'newcomer',
                'Did you recently move to Canada (immigrant, refugee, or new resident)?',
                true,
                'Select Yes if you immigrated, came as a refugee, or moved to Canada recently.',
                [
                    [
                        'id' => 'arrival_status',
                        'when' => 'yes',
                        'type' => 'select',
                        'label' => 'Status when you arrived in Canada',
                        'required' => true,
                        'placeholder' => 'Select…',
                        'options' => [
                            ['label' => 'Permanent resident', 'value' => 'Permanent resident'],
                            ['label' => 'Work permit', 'value' => 'Work permit'],
                            ['label' => 'Study permit', 'value' => 'Study permit'],
                            ['label' => 'Refugee / protected person', 'value' => 'Refugee'],
                            ['label' => 'Visitor / other', 'value' => 'Other'],
                        ],
                    ],
                    [
                        'id' => 'arrival_date',
                        'when' => 'yes',
                        'type' => 'date',
                        'label' => 'Date you became a resident of Canada',
                        'required' => false,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_cra_account',
                'has_cra_account',
                'Do you have a CRA My Account?',
                true
            ),

            // ── Page 2: Household ──────────────────────────────────────────
            [
                'id' => 'ti_page_household',
                'type' => 'page_break',
                'label' => 'Household',
                'name' => 'page_household',
                'required' => false,
            ],
            [
                'id' => 'ti_household_heading',
                'type' => 'heading',
                'label' => 'Spouse / partner & family',
                'name' => 'household_heading',
                'required' => false,
            ],
            tax_intake_yes_no(
                'ti_has_spouse',
                'has_spouse',
                'Do you have a spouse or common-law partner for this tax year?',
                true,
                '',
                [
                    [
                        'id' => 'spouse_first_name',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => "Spouse's first name",
                        'required' => true,
                    ],
                    [
                        'id' => 'spouse_last_name',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => "Spouse's last name",
                        'required' => true,
                    ],
                    [
                        'id' => 'spouse_dob',
                        'when' => 'yes',
                        'type' => 'date',
                        'label' => "Spouse's date of birth",
                        'required' => false,
                    ],
                    [
                        'id' => 'spouse_sin',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => "Spouse's SIN (if available)",
                        'required' => false,
                        'numberFormat' => '###-###-###',
                        'placeholder' => '###-###-###',
                    ],
                    [
                        'id' => 'spouse_income',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => "Spouse's approximate net income (optional)",
                        'required' => false,
                        'placeholder' => 'e.g. 45000',
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_dependants',
                'has_dependants',
                'Do you have children or other family members who depend on you financially?',
                true,
                '',
                [
                    [
                        'id' => 'dependants_details',
                        'when' => 'yes',
                        'type' => 'textarea',
                        'label' => 'Tell us about your dependants',
                        'required' => true,
                        'placeholder' => 'Names, ages, and relationship (e.g. child age 8, child age 12)…',
                    ],
                    [
                        'id' => 'childcare_receipts',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'Childcare receipts (optional)',
                        'required' => false,
                        'maxFiles' => 5,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_disability',
                'has_disability',
                'Do you have a disability approved by the CRA (disability tax credit)?',
                true
            ),
            tax_intake_yes_no(
                'ti_foreign_property',
                'foreign_property',
                'Do you own property outside Canada worth over $100,000?',
                true,
                'CRA may require Form T1135 if the answer is Yes.'
            ),

            // ── Page 3: Your situation ─────────────────────────────────────
            [
                'id' => 'ti_page_situation',
                'type' => 'page_break',
                'label' => 'Your situation',
                'name' => 'page_situation',
                'required' => false,
            ],
            [
                'id' => 'ti_situation_heading',
                'type' => 'heading',
                'label' => 'Tell us about your year',
                'name' => 'situation_heading',
                'required' => false,
            ],
            [
                'id' => 'ti_situation_intro',
                'type' => 'paragraph',
                'label' => 'These questions help us collect only the documents relevant to you.',
                'name' => 'situation_intro',
                'required' => false,
            ],
            tax_intake_yes_no(
                'ti_student',
                'was_student',
                'Were you a student during the tax year?',
                true,
                '',
                [
                    [
                        'id' => 'tuition_receipts',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'T2202 / tuition receipts',
                        'required' => false,
                        'maxFiles' => 5,
                        'accept' => $accept,
                    ],
                    [
                        'id' => 'student_loan_interest',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => 'Student loan interest paid (optional)',
                        'required' => false,
                        'placeholder' => 'Amount in CAD',
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_employed',
                'was_employed',
                'Did you work for an employer (T4 income)?',
                true,
                '',
                [
                    [
                        'id' => 't4_uploads',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'T4 – Pay slips from your employer',
                        'required' => false,
                        'helpText' => 'Upload all T4 slips you received.',
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                    [
                        'id' => 'has_t2200',
                        'when' => 'yes',
                        'type' => 'select',
                        'label' => 'Do you have a signed T2200 from your employer?',
                        'required' => false,
                        'placeholder' => 'Select…',
                        'options' => [
                            ['label' => 'Yes', 'value' => 'Yes'],
                            ['label' => 'No', 'value' => 'No'],
                            ['label' => 'Not sure', 'value' => 'Not sure'],
                        ],
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_self_employed',
                'was_self_employed',
                'Did you have self-employment, gig, or freelance income?',
                true,
                '',
                [
                    [
                        'id' => 'business_name',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => 'Business / trade name (optional)',
                        'required' => false,
                    ],
                    [
                        'id' => 'self_employed_notes',
                        'when' => 'yes',
                        'type' => 'textarea',
                        'label' => 'Briefly describe your business income & expenses',
                        'required' => false,
                        'placeholder' => 'Uber/Lyft, consulting, sales, approximate income…',
                    ],
                    [
                        'id' => 'self_employed_docs',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'Business records / summaries (optional)',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_ei_benefits',
                'had_ei_or_benefits',
                'Did you receive EI, CPP, OAS, or social assistance slips?',
                true,
                '',
                [
                    [
                        'id' => 'benefit_slips',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'T4E / T4A / T4A(P) / T4A(OAS) / T5007 slips',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_investments',
                'had_investments',
                'Did you have investment income (T5, T3, T5008)?',
                true,
                '',
                [
                    [
                        'id' => 'investment_slips',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'T5 / T3 / T5008 slips',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_paid_rent',
                'paid_rent',
                'Did you pay rent during the tax year?',
                true,
                '',
                [
                    [
                        'id' => 'rent_amount',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => 'Total rent paid for the year (optional)',
                        'required' => false,
                        'placeholder' => 'e.g. 14400',
                    ],
                    [
                        'id' => 'landlord_name',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => 'Full name of the landlord (optional)',
                        'required' => false,
                    ],
                    [
                        'id' => 'rent_address',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => 'Address of rented property (optional)',
                        'required' => false,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_moved',
                'moved_for_work_or_school',
                'Did you move for work or school (at least 40 km closer)?',
                true,
                '',
                [
                    [
                        'id' => 'moving_expenses',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => 'Moving expenses (optional)',
                        'required' => false,
                        'placeholder' => 'Amount in CAD',
                    ],
                    [
                        'id' => 'new_location_address',
                        'when' => 'yes',
                        'type' => 'text',
                        'label' => 'Address of new work/school',
                        'required' => false,
                    ],
                ]
            ),

            // ── Page 4: Deductions ─────────────────────────────────────────
            [
                'id' => 'ti_page_deductions',
                'type' => 'page_break',
                'label' => 'Deductions & credits',
                'name' => 'page_deductions',
                'required' => false,
            ],
            [
                'id' => 'ti_deductions_heading',
                'type' => 'heading',
                'label' => 'Income & deductions',
                'name' => 'deductions_heading',
                'required' => false,
            ],
            [
                'id' => 'ti_deductions_intro',
                'type' => 'paragraph',
                'label' => 'These expenses can reduce the tax you owe. Just tell us which ones apply — we\'ll ask for documents if needed.',
                'name' => 'deductions_intro',
                'required' => false,
            ],
            tax_intake_yes_no(
                'ti_rrsp',
                'rrsp_fhsa',
                'Did you contribute to an RRSP or FHSA?',
                true,
                '',
                [
                    [
                        'id' => 'rrsp_receipts',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'RRSP / FHSA contribution receipts',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                    [
                        'id' => 'rrsp_amount',
                        'when' => 'yes',
                        'type' => 'number',
                        'label' => 'Total contributions (optional)',
                        'required' => false,
                        'placeholder' => 'Amount in CAD',
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_donations',
                'charitable_donations',
                'Did you make charitable donations?',
                true,
                '',
                [
                    [
                        'id' => 'donation_receipts',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'Donation receipts',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_medical',
                'medical_expenses',
                'Did you have significant medical expenses?',
                true,
                '',
                [
                    [
                        'id' => 'medical_receipts',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'Medical receipts / statements',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            tax_intake_yes_no(
                'ti_employment_expenses',
                'employment_expenses',
                'Do you have employment expenses (home office, supplies, vehicle)?',
                true,
                'Claim only expenses you can prove. CRA may ask for receipts.',
                [
                    [
                        'id' => 'employment_expense_details',
                        'when' => 'yes',
                        'type' => 'textarea',
                        'label' => 'Describe employment expenses',
                        'required' => false,
                        'placeholder' => 'Home office %, phone, supplies, vehicle km…',
                    ],
                    [
                        'id' => 'employment_expense_docs',
                        'when' => 'yes',
                        'type' => 'file',
                        'label' => 'Expense receipts / T2200',
                        'required' => false,
                        'maxFiles' => 10,
                        'accept' => $accept,
                    ],
                ]
            ),
            [
                'id' => 'ti_other_income',
                'type' => 'textarea',
                'label' => 'Other income not on a standard slip (optional)',
                'name' => 'other_income_notes',
                'required' => false,
                'placeholder' => 'Tips, foreign income, crypto, etc.',
                'helpText' => 'Income not reported on a standard slip — tips, foreign income, etc.',
            ],

            // ── Page 5: Documents & submit ─────────────────────────────────
            [
                'id' => 'ti_page_documents',
                'type' => 'page_break',
                'label' => 'Documents',
                'name' => 'page_documents',
                'required' => false,
            ],
            [
                'id' => 'ti_docs_heading',
                'type' => 'heading',
                'label' => 'Upload supporting documents',
                'name' => 'docs_heading',
                'required' => false,
            ],
            [
                'id' => 'ti_docs_intro',
                'type' => 'paragraph',
                'label' => 'Upload any remaining slips, receipts, or ID. You can also send more later via Submit Tax Documents.',
                'name' => 'docs_intro',
                'required' => false,
            ],
            [
                'id' => 'ti_sin_document',
                'type' => 'file',
                'label' => 'SIN document / photo ID (optional)',
                'name' => 'sin_document',
                'required' => false,
                'maxFiles' => 3,
                'accept' => $accept,
            ],
            [
                'id' => 'ti_other_docs',
                'type' => 'file',
                'label' => 'Other tax documents',
                'name' => 'other_documents',
                'required' => false,
                'helpText' => 'PDF, images, or ZIP. You can upload multiple files.',
                'maxFiles' => 10,
                'accept' => $accept,
            ],
            [
                'id' => 'ti_notes',
                'type' => 'textarea',
                'label' => 'Anything else we should know?',
                'name' => 'notes',
                'required' => false,
                'placeholder' => 'Special situations, deadlines, preferred contact method…',
            ],
            tax_intake_yes_no(
                'ti_consent',
                'consent_prepare_return',
                'I confirm the information is accurate and authorize Verma Accounting to prepare my tax return.',
                true,
                'This serves as your electronic authorization. You\'ll review before anything is filed with CRA.'
            ),
        ],
        'settings' => [
            'submitLabel' => 'Submit tax intake',
            'successMessage' => 'Thank you — your tax information has been submitted. Our team will review everything and prepare your return. We\'ll reach out if we need anything else.',
            'firstPageTitle' => 'About you',
            'taxYear' => [
                'enabled' => true,
                'label' => 'Select tax year',
                'prompt' => 'Which tax year are you filing for?',
                'years' => [],
            ],
            'dataMatch' => [
                // Disabled: anonymous lookup must not return tax PII (SIN, DOB, etc.).
                'enabled' => false,
                'fieldIds' => ['ti_email', 'ti_phone'],
                'title' => 'We may already have your info',
                'message' => 'We found a matching client profile on file. Continue filling this form — our team will match your records securely.',
                'confirmLabel' => 'Continue',
                'declineLabel' => 'Got it',
            ],
        ],
    ];
}

/**
 * Ensure the published Tax Intake form exists (create-if-missing; never overwrite edits).
 * Also hardens security-sensitive settings on existing installs.
 */
function ensure_tax_intake_form(): void
{
    $repo = new FormRepository();
    $slug = tax_intake_form_slug();
    if ($repo->findBySlug($slug, false) === null) {
        $repo->create([
            'slug' => $slug,
            'title' => 'File Your Tax Return',
            'description' => 'Personal tax intake — about you, household, income, deductions, and documents.',
            'schema' => tax_intake_default_schema(),
            'status' => 'published',
        ]);
        return;
    }

    tax_intake_harden_existing_form();
}

/**
 * Disable anonymous PII prefill on the seeded tax intake form.
 */
function tax_intake_harden_existing_form(): void
{
    $repo = new FormRepository();
    $form = $repo->findBySlug(tax_intake_form_slug(), false);
    if ($form === null) {
        return;
    }

    $schema = $repo->decodeSchema($form);
    $dm = is_array($schema['settings']['dataMatch'] ?? null) ? $schema['settings']['dataMatch'] : [];
    if (empty($dm['enabled'])) {
        return;
    }

    $schema['settings']['dataMatch']['enabled'] = false;
    $schema['settings']['dataMatch']['message'] = 'We found a matching client profile on file. Continue filling this form — our team will match your records securely.';
    $schema['settings']['dataMatch']['confirmLabel'] = 'Continue';
    $schema['settings']['dataMatch']['declineLabel'] = 'Got it';

    $repo->update((int) $form['id'], [
        'slug' => $form['slug'],
        'title' => $form['title'],
        'description' => $form['description'] ?? '',
        'status' => $form['status'],
        'schema' => $schema,
        'cta_label' => $form['cta_label'] ?? null,
    ]);
}
