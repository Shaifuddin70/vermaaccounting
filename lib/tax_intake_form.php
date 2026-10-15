<?php

declare(strict_types=1);

/** Published form slug for the KeenTax-style personal tax intake. */
function tax_intake_form_slug(): string
{
    return 'tax-intake';
}

/**
 * Bump when tax_intake_default_schema() changes so the seeded form is upgraded once.
 * Forms saved from the builder keep settings.templateVersion, so later admin edits are not overwritten.
 */
function tax_intake_template_version(): int
{
    return 2;
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
    array $followUps = [],
    array $conditions = []
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
        'conditions' => $conditions,
    ];
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function tax_intake_field(
    string $id,
    string $type,
    string $name,
    string $label,
    bool $required = false,
    array $extra = []
): array {
    return array_merge([
        'id' => $id,
        'type' => $type,
        'label' => $label,
        'name' => $name,
        'required' => $required,
    ], $extra);
}

/**
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function tax_intake_money(string $id, string $name, string $label, bool $required = false, array $extra = []): array
{
    return tax_intake_field($id, 'number', $name, $label, $required, array_merge([
        'placeholder' => 'Amount in CAD',
    ], $extra));
}

/** @return array<string, mixed> */
function tax_intake_heading(string $id, string $label): array
{
    return tax_intake_field($id, 'heading', $id, $label);
}

/** @return array<string, mixed> */
function tax_intake_paragraph(string $id, string $label): array
{
    return tax_intake_field($id, 'paragraph', $id, $label);
}

/** @return array<string, mixed> */
function tax_intake_page(string $id, string $label, array $conditions = []): array
{
    return tax_intake_field($id, 'page_break', $id, $label, false, ['conditions' => $conditions]);
}

/**
 * Show-condition: every listed field id must equal its value.
 *
 * @param array<string, string> $rules field id => value
 * @return list<array<string, mixed>>
 */
function tax_intake_show_when(array $rules, string $logic = 'all', string $operator = 'equals'): array
{
    $list = [];
    foreach ($rules as $field => $value) {
        $list[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    return [['action' => 'show', 'logic' => $logic, 'rules' => $list]];
}

/** @return array<string, mixed> */
function tax_intake_follow_up(string $id, string $when, string $type, string $label, bool $required = false, array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'when' => $when,
        'type' => $type,
        'label' => $label,
        'required' => $required,
    ], $extra);
}

/**
 * Stand-in for a repeatable block: one structured entry above, extra entries described here.
 *
 * @return array<string, mixed>
 */
function tax_intake_another(string $id, string $name, string $question, string $detailsLabel, string $placeholder): array
{
    return tax_intake_yes_no($id, $name, $question, false, '', [
        tax_intake_follow_up('details', 'yes', 'textarea', $detailsLabel, true, ['placeholder' => $placeholder]),
    ]);
}

/**
 * @param list<array<string, mixed>> $conditions
 * @return array<string, mixed>
 */
function tax_intake_upload(
    string $id,
    string $name,
    string $label,
    array $conditions = [],
    bool $required = false,
    int $maxFiles = 10,
    string $helpText = ''
): array {
    return tax_intake_field($id, 'file', $name, $label, $required, [
        'maxFiles' => $maxFiles,
        'accept' => form_file_accept_default(),
        'helpText' => $helpText,
        'conditions' => $conditions,
    ]);
}

/**
 * @return list<array{label: string, value: string}>
 */
function tax_intake_options(array $values): array
{
    return array_map(
        static fn (string $v): array => ['label' => $v, 'value' => $v],
        array_values($values)
    );
}

/**
 * @return list<array{label: string, value: string}>
 */
function tax_intake_province_options(): array
{
    return tax_intake_options([
        'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick',
        'Newfoundland and Labrador', 'Northwest Territories', 'Nova Scotia',
        'Nunavut', 'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan', 'Yukon',
    ]);
}

/** Most recent five completed tax years, newest first. */
function tax_intake_tax_years(): array
{
    $last = (int) date('Y') - 1;

    return range($last, $last - 4);
}

/**
 * Multi-step personal tax intake schema (modeled on the KeenTax intake flow).
 *
 * @return array{fields: list<array<string, mixed>>, settings: array<string, mixed>}
 */
function tax_intake_default_schema(): array
{
    $yes = static fn (string $fieldId): array => tax_intake_show_when([$fieldId => 'yes']);
    $provinces = tax_intake_province_options();

    return [
        'fields' => [
            // ── Step 1: About you ──────────────────────────────────────────
            tax_intake_paragraph('ti_about_intro', 'Just answer a few simple questions and we\'ll take care of the rest. Your answers are sent securely over HTTPS.'),
            tax_intake_heading('ti_identification_heading', 'Identification'),
            tax_intake_field('ti_sin', 'number', 'sin', 'Social Insurance Number', true, [
                'placeholder' => '###-###-###',
                'numberFormat' => '###-###-###',
                'helpText' => 'Returning clients: enter your SIN and date of birth to pre-fill your information from last year.',
            ]),
            tax_intake_field('ti_dob', 'date', 'date_of_birth', 'Date of Birth', true, [
                'minAge' => 16,
                'helpText' => 'Must match your government ID.',
            ]),

            tax_intake_heading('ti_name_heading', 'Your Name'),
            tax_intake_field('ti_first_name', 'text', 'first_name', 'First Name', true, [
                'placeholder' => 'As on your SIN or passport',
            ]),
            tax_intake_field('ti_last_name', 'text', 'last_name', 'Last Name', true, [
                'placeholder' => 'As on your SIN or passport',
            ]),
            tax_intake_field('ti_middle_name', 'text', 'middle_name', 'Middle Name'),

            tax_intake_heading('ti_contact_heading', 'Contact'),
            tax_intake_field('ti_email', 'email', 'email', 'Email', true, [
                'placeholder' => 'you@example.com',
            ]),
            tax_intake_field('ti_phone', 'tel', 'phone', 'Phone Number', true, [
                'placeholder' => '(###) ###-####',
                'helpText' => 'A number the CRA can reach you on.',
                'phoneCountry' => 'CA',
                'phoneFormat' => '(###) ###-####',
                'phoneAllowCountrySelect' => true,
            ]),
            tax_intake_yes_no('ti_whatsapp_same', 'whatsapp_same_as_phone', 'Is your WhatsApp number the same as your phone number?', false, '', [
                tax_intake_follow_up('country_code', 'no', 'select', 'WhatsApp Country Code', true, [
                    'options' => [
                        ['label' => '+1 (Canada/US)', 'value' => '+1'],
                        ['label' => '+44 (UK)', 'value' => '+44'],
                        ['label' => '+91 (India)', 'value' => '+91'],
                        ['label' => '+61 (Australia)', 'value' => '+61'],
                        ['label' => '+971 (UAE)', 'value' => '+971'],
                        ['label' => '+92 (Pakistan)', 'value' => '+92'],
                        ['label' => '+880 (Bangladesh)', 'value' => '+880'],
                        ['label' => '+234 (Nigeria)', 'value' => '+234'],
                        ['label' => '+254 (Kenya)', 'value' => '+254'],
                        ['label' => '+27 (South Africa)', 'value' => '+27'],
                    ],
                ]),
                tax_intake_follow_up('number', 'no', 'text', 'WhatsApp Number', true, [
                    'placeholder' => 'Number without the country code',
                ]),
            ]),

            tax_intake_heading('ti_marital_heading', 'Marital Status'),
            tax_intake_field('ti_marital', 'select', 'marital_status', 'What is your current marital status?', true, [
                'helpText' => 'Your status on December 31 of the tax year.',
                'options' => [
                    ['label' => 'Married', 'value' => 'Married'],
                    ['label' => 'Living Common-Law', 'value' => 'Common-law'],
                    ['label' => 'Widowed', 'value' => 'Widowed'],
                    ['label' => 'Divorced', 'value' => 'Divorced'],
                    ['label' => 'Separated', 'value' => 'Separated'],
                    ['label' => 'Single', 'value' => 'Single'],
                ],
            ]),
            tax_intake_yes_no('ti_marital_changed', 'marital_status_changed', 'Did your marital status change during the tax year?', false, '', [
                tax_intake_follow_up('date', 'yes', 'date', 'When did it change?'),
            ]),

            tax_intake_heading('ti_preferences_heading', 'Preferences'),
            tax_intake_field('ti_language', 'select', 'cra_language', 'Preferred language for CRA correspondence', false, [
                'options' => tax_intake_options(['English', 'French']),
            ]),
            tax_intake_yes_no('ti_citizen', 'canadian_citizen', 'Are you a Canadian citizen?', false),
            tax_intake_yes_no('ti_left_canada', 'left_canada', 'Did you permanently leave Canada during the year?', false, '', [
                tax_intake_follow_up('date', 'yes', 'date', 'Date you left Canada'),
            ]),
            tax_intake_yes_no('ti_cra_account', 'has_cra_account', 'Do you have a CRA My Account?', false),
            tax_intake_yes_no('ti_elections_canada', 'elections_canada', 'Authorize Elections Canada to use your information to update voter registration?', false, 'Optional.'),

            // ── Step 2: Your address ───────────────────────────────────────
            tax_intake_page('ti_page_address', 'Your Address'),
            tax_intake_heading('ti_address_heading', 'Address'),
            tax_intake_field('ti_unit', 'text', 'unit', 'Unit / Apt'),
            tax_intake_field('ti_address', 'text', 'address', 'Street', true, [
                'placeholder' => 'Street number and street name',
            ]),
            tax_intake_field('ti_po_box', 'text', 'po_box', 'P.O. Box'),
            tax_intake_field('ti_city', 'text', 'city', 'City', true),
            tax_intake_field('ti_province', 'select', 'province', 'Province / Territory', true, [
                'options' => $provinces,
            ]),
            tax_intake_field('ti_postal', 'text', 'postal_code', 'Postal Code', true, [
                'placeholder' => 'A1A 1A1',
            ]),
            tax_intake_field('ti_country', 'text', 'country', 'Country', false, [
                'placeholder' => 'Canada',
                'helpText' => 'Leave blank if Canada.',
            ]),

            tax_intake_heading('ti_dec31_heading', 'Province on December 31'),
            tax_intake_field('ti_province_dec31', 'select', 'province_dec31', 'Which province/territory did you live in on December 31 of the tax year?', true, [
                'options' => $provinces,
                'helpText' => 'Usually the same as the province above.',
            ]),
            tax_intake_yes_no('ti_moved_province', 'moved_province', 'Did you move to a different province during the tax year?', false, '', [
                tax_intake_follow_up('from', 'yes', 'select', 'Which province did you move from?', false, ['options' => $provinces]),
                tax_intake_follow_up('date', 'yes', 'date', 'Date of Move', true),
            ]),

            // ── Step 3: Your tax situation ─────────────────────────────────
            tax_intake_page('ti_page_situation', 'Your Tax Situation'),
            tax_intake_paragraph('ti_situation_intro', 'These answers decide which follow-up steps and document uploads you\'ll see next.'),

            tax_intake_heading('ti_important_heading', 'Important Questions'),
            tax_intake_yes_no('ti_filed_before', 'filed_before', 'Have you filed taxes before?', true, '', [
                tax_intake_follow_up('first_filing_year', 'no', 'number', 'What year are you filing for the first time?', false, [
                    'placeholder' => 'e.g. ' . ((int) date('Y') - 1),
                ]),
            ]),
            tax_intake_yes_no('ti_newcomer', 'newcomer', 'Did you recently move to Canada in the tax year?', true),
            tax_intake_yes_no('ti_student', 'was_student', 'Were you a student in the tax year?', true, '', [
                tax_intake_follow_up('tuition', 'yes', 'number', 'Estimated tuition paid in the tax year', false, ['placeholder' => 'Amount in CAD']),
            ]),
            tax_intake_yes_no('ti_employed', 'was_employed', 'Did you work for an employer in the tax year?', true, '', [
                tax_intake_follow_up('income', 'yes', 'number', 'Estimated total income in the tax year', false, ['placeholder' => 'Amount in CAD']),
            ]),
            tax_intake_yes_no('ti_self_employed', 'was_self_employed', 'Did you earn money from Uber/Lyft, your own business, or freelancing?', true),
            tax_intake_yes_no('ti_moved', 'moved_for_work_or_school', 'Did you move to be closer to a new job or school?', true),
            tax_intake_yes_no('ti_paid_rent', 'paid_rent', 'Did you pay rent in the tax year?', true),

            tax_intake_heading('ti_about_situation_heading', 'About Your Situation'),
            tax_intake_yes_no('ti_dependants', 'has_dependants', 'Do you have children or other family members who depend on you financially?', false),
            tax_intake_yes_no('ti_organ_donor', 'organ_donor', 'Would you like to register as an organ and tissue donor?', false, 'Ontario returns only.'),
            tax_intake_yes_no('ti_disability', 'has_disability', 'Do you have a disability approved by the CRA?', false),
            tax_intake_yes_no('ti_bankruptcy', 'bankruptcy', 'Did you file for bankruptcy during the tax year?', false),

            tax_intake_heading('ti_income_heading', 'Income You Received'),
            tax_intake_yes_no('ti_rental_income', 'rental_income', 'Did you collect rent from a property you own?', false),
            tax_intake_yes_no('ti_investments', 'had_investments', 'Did you earn interest or dividends from a bank or investments?', false),
            tax_intake_yes_no('ti_sold_assets', 'sold_assets', 'Did you sell any stocks, crypto, or property?', false),
            tax_intake_yes_no('ti_ei', 'received_ei', 'Did you receive Employment Insurance (EI) benefits?', false),
            tax_intake_yes_no('ti_pension', 'received_pension', 'Did you receive a pension?', false),
            tax_intake_yes_no('ti_social_assistance', 'social_assistance', 'Did you receive social assistance?', false),
            tax_intake_yes_no('ti_workers_comp', 'workers_comp', 'Did you receive Workers\' Compensation?', false),
            tax_intake_yes_no('ti_scholarship', 'scholarship', 'Did you receive a scholarship, bursary, or grant?', false),
            tax_intake_yes_no('ti_foreign_income', 'foreign_income', 'Did you earn any income from outside Canada?', false),
            tax_intake_yes_no('ti_tips', 'unreported_tips', 'Did you earn tips that were not included on your pay stub?', false),

            tax_intake_heading('ti_spent_heading', 'Money You Spent'),
            tax_intake_yes_no('ti_rrsp', 'rrsp_fhsa', 'Did you put money into an RRSP, PRPP, or FHSA?', false),
            tax_intake_yes_no('ti_child_care', 'child_care', 'Did you pay for child care?', false),
            tax_intake_yes_no('ti_medical', 'medical_expenses', 'Did you pay for medical or dental expenses out of your own pocket?', false),
            tax_intake_yes_no('ti_donations', 'charitable_donations', 'Did you donate to a charity or organization?', false),
            tax_intake_yes_no('ti_support_paid', 'support_paid', 'Did you pay alimony or child support to an ex-spouse?', false),
            tax_intake_yes_no('ti_student_loan', 'student_loan_interest', 'Did you pay interest on a government student loan?', false),
            tax_intake_yes_no('ti_wfh', 'work_from_home', 'Did you work from home for your employer?', false),
            tax_intake_yes_no('ti_employment_expenses', 'employment_expenses', 'Did your employer require you to pay for work expenses?', false),
            tax_intake_yes_no('ti_union_dues', 'union_dues', 'Did you pay union or professional dues?', false),

            tax_intake_heading('ti_housing_heading', 'Housing'),
            tax_intake_yes_no('ti_property_tax', 'property_tax', 'Did you pay property tax on a home you own?', false),
            tax_intake_yes_no('ti_first_home', 'first_home', 'Did you buy your first home in the tax year?', false),
            tax_intake_yes_no('ti_foreign_property', 'foreign_property', 'Do you own property outside Canada worth over $100,000?', false, 'The CRA may require Form T1135 if the answer is Yes.'),

            // ── Step 4: Spouse / partner ───────────────────────────────────
            tax_intake_page('ti_page_spouse', 'Spouse / Partner', [[
                'action' => 'show',
                'logic' => 'any',
                'rules' => [
                    ['field' => 'ti_marital', 'operator' => 'equals', 'value' => 'Married'],
                    ['field' => 'ti_marital', 'operator' => 'equals', 'value' => 'Common-law'],
                ],
            ]]),
            tax_intake_field('ti_spouse_first_name', 'text', 'spouse_first_name', 'Spouse\'s First Name', true),
            tax_intake_field('ti_spouse_last_name', 'text', 'spouse_last_name', 'Spouse\'s Last Name', true),
            tax_intake_field('ti_spouse_sin', 'number', 'spouse_sin', 'Spouse\'s SIN', false, [
                'placeholder' => '###-###-###',
                'numberFormat' => '###-###-###',
            ]),
            tax_intake_field('ti_spouse_dob', 'date', 'spouse_dob', 'Spouse\'s Date of Birth'),
            tax_intake_money('ti_spouse_income', 'spouse_income', 'Their net income in the tax year', false, [
                'helpText' => 'Line 23600 of their return, or an estimate.',
            ]),
            tax_intake_yes_no('ti_spouse_self_employed', 'spouse_self_employed', 'Is your spouse self-employed?', false),
            tax_intake_field('ti_spouse_country', 'text', 'spouse_country', 'Current country of residence', true, [
                'placeholder' => 'Canada',
            ]),
            tax_intake_yes_no('ti_spouse_filing', 'spouse_filing_with_us', 'Is your spouse also filing their taxes with us?', false, 'If Yes, we\'ll link your returns so credits and benefits are optimized for both of you.'),

            // ── Step 5: Dependants ─────────────────────────────────────────
            tax_intake_page('ti_page_dependants', 'Dependants', $yes('ti_dependants')),
            tax_intake_heading('ti_dependant_heading', 'Dependant 1'),
            tax_intake_field('ti_dep_first_name', 'text', 'dependant_first_name', 'First Name', true),
            tax_intake_field('ti_dep_last_name', 'text', 'dependant_last_name', 'Last Name', true),
            tax_intake_field('ti_dep_dob', 'date', 'dependant_dob', 'Date of Birth'),
            tax_intake_field('ti_dep_relationship', 'select', 'dependant_relationship', 'Relationship', false, [
                'options' => tax_intake_options(['Child', 'Grandchild', 'Parent', 'Grandparent', 'Sibling', 'Niece/Nephew', 'Aunt/Uncle', 'Other']),
            ]),
            tax_intake_field('ti_dep_sin', 'number', 'dependant_sin', 'SIN (if available)', false, [
                'placeholder' => '###-###-###',
                'numberFormat' => '###-###-###',
            ]),
            tax_intake_money('ti_dep_income', 'dependant_income', 'Their net income'),
            tax_intake_yes_no('ti_dep_lives_with', 'dependant_lives_with_you', 'Lives with you?', false),
            tax_intake_yes_no('ti_dep_disability', 'dependant_disability', 'Has a disability?', false),
            tax_intake_yes_no('ti_dep_student', 'dependant_student', 'Is a student?', false),
            tax_intake_yes_no('ti_dep_child_care', 'dependant_claim_child_care', 'Claim child care expenses for this dependant?', false),
            tax_intake_another(
                'ti_dep_more',
                'more_dependants',
                'Do you have another dependant?',
                'Tell us about your other dependants',
                'For each: name, date of birth, relationship, SIN, net income, lives with you, disability, student…'
            ),

            // ── Step 6: Self-employment ────────────────────────────────────
            tax_intake_page('ti_page_self_employment', 'Self-employment / Business income (Uber / Lyft)', $yes('ti_self_employed')),
            tax_intake_field('ti_business_name', 'text', 'business_name', 'Business Name'),
            tax_intake_field('ti_business_type', 'select', 'business_type', 'Business Type', false, [
                'options' => tax_intake_options(['Sole Proprietorship', 'Partnership']),
            ]),
            tax_intake_field('ti_business_industry', 'select', 'business_industry', 'Industry', false, [
                'options' => tax_intake_options([
                    'Rideshare (Uber/Lyft)', 'Delivery (DoorDash/Skip/Instacart)', 'Freelance/Consulting', 'Retail',
                    'Construction', 'Real Estate', 'Food Service/Restaurant', 'Health/Wellness', 'Technology/IT',
                    'Arts/Entertainment', 'Trucking/Transportation', 'Cleaning Services', 'Other',
                ]),
            ]),
            tax_intake_money('ti_business_gross', 'business_gross_income', 'Total Gross Income', true, [
                'helpText' => 'Before expenses.',
            ]),
            tax_intake_yes_no('ti_business_add_expenses', 'business_add_expenses', 'Add business expenses?', false),
            ...array_map(
                static fn (array $e): array => tax_intake_money('ti_bx_' . $e[0], 'business_expense_' . $e[0], $e[1], false, [
                    'conditions' => $yes('ti_business_add_expenses'),
                ]),
                [
                    ['advertising', 'Advertising'],
                    ['meals', 'Meals & Entertainment (50% deductible)'],
                    ['insurance', 'Insurance'],
                    ['interest', 'Interest & Bank Charges'],
                    ['office', 'Office Expenses'],
                    ['supplies', 'Supplies'],
                    ['legal', 'Legal & Accounting'],
                    ['rent', 'Rent'],
                    ['salaries', 'Salaries & Wages'],
                    ['travel', 'Travel'],
                    ['phone_utilities', 'Phone & Utilities'],
                    ['other', 'Other Expenses'],
                ]
            ),
            tax_intake_yes_no('ti_business_vehicle', 'business_vehicle', 'Did you use a vehicle for this business?', false, '', [], $yes('ti_business_add_expenses')),
            tax_intake_field('ti_vehicle_make', 'text', 'vehicle_make_model', 'Make / Model of Vehicle', false, [
                'conditions' => $yes('ti_business_vehicle'),
            ]),
            tax_intake_field('ti_vehicle_class', 'select', 'vehicle_class', 'Vehicle Class', false, [
                'conditions' => $yes('ti_business_vehicle'),
                'options' => tax_intake_options([
                    'Class 10 - Car, van, pickup (most vehicles)',
                    'Class 10.1 - Passenger vehicle above CRA limit',
                    'Class 54 - Zero-emission vehicle',
                    'Other / Not sure',
                ]),
            ]),
            tax_intake_yes_no('ti_vehicle_is_motor', 'vehicle_is_motor_vehicle', 'Is this a motor vehicle?', false, '', [], $yes('ti_business_vehicle')),
            tax_intake_field('ti_vehicle_purchase_date', 'date', 'vehicle_purchase_date', 'Purchase Date', false, [
                'conditions' => $yes('ti_business_vehicle'),
            ]),
            tax_intake_field('ti_vehicle_km_total', 'number', 'vehicle_km_total', 'Total KM driven in the tax year', false, [
                'conditions' => $yes('ti_business_vehicle'),
            ]),
            tax_intake_field('ti_vehicle_km_business', 'number', 'vehicle_km_business', 'KM driven for business', false, [
                'conditions' => $yes('ti_business_vehicle'),
            ]),
            ...array_map(
                static fn (array $e): array => tax_intake_money('ti_vx_' . $e[0], 'vehicle_expense_' . $e[0], $e[1], false, [
                    'conditions' => $yes('ti_business_vehicle'),
                ]),
                [
                    ['fuel', 'Fuel and Oil'],
                    ['maintenance', 'Maintenance and Repairs'],
                    ['insurance', 'Insurance'],
                    ['registration', 'Registration and Licenses'],
                    ['electricity', 'Electricity (zero-emission vehicles)'],
                    ['parking', 'Parking Expenses'],
                    ['supplementary_insurance', 'Supplementary Business Insurance'],
                    ['other_1', 'Other Motor Vehicle Expense 1'],
                    ['other_2', 'Other Motor Vehicle Expense 2'],
                ]
            ),
            tax_intake_heading('ti_gst_heading', 'GST/HST'),
            tax_intake_yes_no('ti_gst_registered', 'gst_registered', 'Registered for GST/HST?', false, '', [
                tax_intake_follow_up('number', 'yes', 'text', 'GST/HST Number', false, ['placeholder' => 'e.g. 123456789 RT0001']),
                tax_intake_follow_up('collected', 'yes', 'number', 'GST/HST Collected', false, ['placeholder' => 'Amount in CAD']),
                tax_intake_follow_up('paid', 'yes', 'number', 'GST/HST Paid on Expenses', false, ['placeholder' => 'Amount in CAD']),
            ]),
            tax_intake_another(
                'ti_business_more',
                'more_businesses',
                'Do you have another business?',
                'Tell us about your other businesses',
                'For each: business name, type, industry, gross income, main expenses…'
            ),

            // ── Step 7: Rental income ──────────────────────────────────────
            tax_intake_page('ti_page_rental', 'Rental Income', $yes('ti_rental_income')),
            tax_intake_field('ti_rental_address', 'text', 'rental_property_address', 'Property Address', true),
            tax_intake_field('ti_rental_landlord', 'text', 'rental_landlord_name', 'Landlord Name', true, [
                'helpText' => 'The owner(s) named on the property.',
            ]),
            tax_intake_field('ti_rental_type', 'select', 'rental_property_type', 'Property Type', false, [
                'options' => tax_intake_options(['House', 'Apartment/Condo', 'Room', 'Commercial', 'Other']),
            ]),
            tax_intake_field('ti_rental_units', 'number', 'rental_units', '# of Units', false, ['placeholder' => '1']),
            tax_intake_field('ti_rental_ownership', 'number', 'rental_ownership_percent', 'Your Ownership %', false, ['placeholder' => '100']),
            tax_intake_money('ti_rental_gross', 'rental_gross_rent', 'Total Gross Rent Collected', true),
            tax_intake_yes_no('ti_rental_add_expenses', 'rental_add_expenses', 'Add rental expenses?', false),
            ...array_map(
                static fn (array $e): array => tax_intake_money('ti_rx_' . $e[0], 'rental_expense_' . $e[0], $e[1], false, [
                    'conditions' => $yes('ti_rental_add_expenses'),
                ]),
                [
                    ['advertising', 'Advertising'],
                    ['insurance', 'Insurance'],
                    ['mortgage_interest', 'Mortgage Interest'],
                    ['legal', 'Legal & Accounting'],
                    ['repairs', 'Repairs & Maintenance'],
                    ['management', 'Property Management'],
                    ['property_taxes', 'Property Taxes'],
                    ['utilities', 'Utilities'],
                    ['condo_fees', 'Condo Fees'],
                    ['other', 'Other Expenses'],
                ]
            ),
            tax_intake_another(
                'ti_rental_more',
                'more_rental_properties',
                'Do you have another rental property?',
                'Tell us about your other rental properties',
                'For each: address, property type, ownership %, gross rent, main expenses…'
            ),

            // ── Step 8: Other income ───────────────────────────────────────
            tax_intake_page('ti_page_other_income', 'Other Income', tax_intake_show_when([
                'ti_foreign_income' => 'yes',
                'ti_tips' => 'yes',
            ], 'any')),
            tax_intake_field('ti_other_income_type', 'select', 'other_income_type', 'Type of Income', true, [
                'options' => tax_intake_options([
                    'Tips/Gratuities', 'Scholarships/Bursaries/Grants', 'Support Payments Received',
                    'Foreign Rental Income', 'Foreign Pension', 'Other Foreign Income',
                    'Casual/Occasional Earnings', 'Other',
                ]),
            ]),
            tax_intake_field('ti_other_income_desc', 'text', 'other_income_description', 'Description'),
            tax_intake_money('ti_other_income_amount', 'other_income_amount', 'Amount (CAD)', true),
            tax_intake_field('ti_other_income_country', 'text', 'other_income_country', 'Country (if foreign)'),
            tax_intake_money('ti_other_income_foreign_tax', 'other_income_foreign_tax', 'Foreign Tax Paid', false, [
                'conditions' => tax_intake_show_when(['ti_other_income_type' => 'Foreign'], 'all', 'contains'),
            ]),
            tax_intake_another(
                'ti_other_income_more',
                'more_other_income',
                'Do you have another income to report?',
                'Tell us about your other income',
                'For each: type, description, amount (CAD), country, foreign tax paid…'
            ),

            // ── Step 9: RRSP / FHSA ────────────────────────────────────────
            tax_intake_page('ti_page_rrsp', 'RRSP / FHSA', $yes('ti_rrsp')),
            tax_intake_field('ti_rrsp_type', 'select', 'rrsp_type', 'Type', false, [
                'options' => [
                    ['label' => 'RRSP', 'value' => 'RRSP'],
                    ['label' => 'Spousal RRSP', 'value' => 'Spousal RRSP'],
                    ['label' => 'PRPP', 'value' => 'PRPP'],
                    ['label' => 'FHSA (First Home Savings)', 'value' => 'FHSA'],
                ],
            ]),
            tax_intake_field('ti_rrsp_institution', 'text', 'rrsp_institution', 'Financial Institution'),
            tax_intake_money('ti_rrsp_amount', 'rrsp_amount', 'Contribution Amount', true),
            tax_intake_field('ti_rrsp_receipt', 'text', 'rrsp_receipt_number', 'Receipt Number', false, ['helpText' => 'Optional.']),
            tax_intake_yes_no('ti_rrsp_first_60', 'rrsp_first_60_days', 'Was this contributed in the first 60 days of the following year?', false),
            tax_intake_another(
                'ti_rrsp_more',
                'more_rrsp',
                'Do you have another contribution?',
                'Tell us about your other contributions',
                'For each: type, institution, amount, receipt number, first 60 days (yes/no)…'
            ),

            // ── Step 10: Child care ────────────────────────────────────────
            tax_intake_page('ti_page_child_care', 'Child Care', $yes('ti_child_care')),
            tax_intake_field('ti_cc_child_name', 'text', 'child_care_child_name', 'Child\'s Name', true),
            tax_intake_field('ti_cc_child_dob', 'date', 'child_care_child_dob', 'Child\'s Date of Birth'),
            tax_intake_field('ti_cc_type', 'select', 'child_care_type', 'Type of Care', false, [
                'options' => tax_intake_options([
                    'Daycare Centre', 'Home Care Provider', 'Day Camp/Sports School',
                    'Nanny/Babysitter', 'Boarding School', 'Other',
                ]),
            ]),
            tax_intake_field('ti_cc_provider', 'text', 'child_care_provider', 'Care Provider Name', true),
            tax_intake_field('ti_cc_provider_id', 'text', 'child_care_provider_id', 'Provider SIN or Business Number'),
            tax_intake_money('ti_cc_amount', 'child_care_amount', 'Total Amount Paid', true),
            tax_intake_another(
                'ti_cc_more',
                'more_child_care',
                'Do you have another child care provider?',
                'Tell us about your other providers',
                'For each: child\'s name, type of care, provider name, SIN/BN, amount paid…'
            ),

            // ── Step 11: Rent paid ─────────────────────────────────────────
            tax_intake_page('ti_page_rent', 'Rent Paid', $yes('ti_paid_rent')),
            tax_intake_field('ti_rent_landlord', 'text', 'landlord_name', 'Landlord\'s Name'),
            tax_intake_field('ti_rent_address', 'text', 'rent_address', 'Address of Rented Property', true),
            tax_intake_field('ti_rent_postal', 'text', 'rent_postal_code', 'Postal Code', true, ['placeholder' => 'A1A 1A1']),
            tax_intake_money('ti_rent_amount', 'rent_amount', 'Rent Paid', true),
            tax_intake_field('ti_rent_period', 'radio', 'rent_period', 'Is that amount per month or for the whole year?', true, [
                'options' => [
                    ['label' => 'Per month', 'value' => 'month'],
                    ['label' => 'Total for the year', 'value' => 'year'],
                ],
            ]),
            tax_intake_field('ti_rent_months', 'number', 'rent_months', 'How many months did you stay here in the tax year?', true, [
                'placeholder' => '12',
            ]),
            tax_intake_another(
                'ti_rent_more',
                'more_rent',
                'Did you rent another place during the year?',
                'Tell us about your other rentals',
                'For each: landlord, address, postal code, rent paid (monthly or yearly), months stayed…'
            ),

            // ── Step 12: Work from home ────────────────────────────────────
            tax_intake_page('ti_page_wfh', 'Work From Home', $yes('ti_wfh')),
            tax_intake_field('ti_wfh_method', 'radio', 'wfh_method', 'Which method would you like to use?', false, [
                'options' => [
                    ['label' => 'Flat Rate Method ($2/day)', 'value' => 'flat'],
                    ['label' => 'Detailed Method (actual expenses)', 'value' => 'detailed'],
                ],
            ]),
            tax_intake_yes_no('ti_wfh_t2200', 'wfh_has_t2200', 'Do you have a signed T2200 (Declaration of Conditions of Employment) from your employer?', false),
            tax_intake_field('ti_wfh_days', 'number', 'wfh_days', 'Number of days worked from home', false, [
                'helpText' => 'Maximum 250 days.',
                'conditions' => tax_intake_show_when(['ti_wfh_method' => 'flat']),
            ]),
            tax_intake_field('ti_wfh_home_area', 'number', 'wfh_home_area', 'Total home area (sq ft)', false, [
                'conditions' => tax_intake_show_when(['ti_wfh_method' => 'detailed']),
            ]),
            tax_intake_field('ti_wfh_office_area', 'number', 'wfh_office_area', 'Office / workspace area (sq ft)', false, [
                'conditions' => tax_intake_show_when(['ti_wfh_method' => 'detailed']),
            ]),
            ...array_map(
                static fn (array $e): array => tax_intake_money('ti_wx_' . $e[0], 'wfh_expense_' . $e[0], $e[1], false, [
                    'conditions' => tax_intake_show_when(['ti_wfh_method' => 'detailed']),
                ]),
                [
                    ['heat', 'Heat'],
                    ['electricity', 'Electricity'],
                    ['water', 'Water'],
                    ['internet', 'Internet'],
                    ['rent', 'Rent Paid (renters only)'],
                    ['maintenance', 'Maintenance & Supplies'],
                    ['insurance', 'Home Insurance (commission employees only)'],
                    ['property_tax', 'Property Tax (commission employees only)'],
                    ['mortgage_interest', 'Mortgage Interest (commission employees only)'],
                ]
            ),

            // ── Step 13: Moving expenses ───────────────────────────────────
            tax_intake_page('ti_page_moving', 'Moving Expenses', $yes('ti_moved')),
            tax_intake_heading('ti_move_heading', 'Move Details'),
            tax_intake_yes_no('ti_move_unused', 'move_unused_expenses', 'Do you have any unused moving expenses from the previous year?', false, '', [
                tax_intake_follow_up('amount', 'yes', 'number', 'Unused moving expenses from the previous year', true, ['placeholder' => 'Amount in CAD']),
            ]),
            tax_intake_field('ti_move_old_street', 'text', 'move_old_street', 'Old Street Address', true),
            tax_intake_field('ti_move_old_city', 'text', 'move_old_city', 'Old City', true),
            tax_intake_field('ti_move_old_province', 'select', 'move_old_province', 'Old Province / Territory', true, ['options' => $provinces]),
            tax_intake_field('ti_move_new_street', 'text', 'move_new_street', 'New Street Address', true),
            tax_intake_field('ti_move_new_city', 'text', 'move_new_city', 'New City', true),
            tax_intake_field('ti_move_new_province', 'select', 'move_new_province', 'New Province / Territory', true, ['options' => $provinces]),
            tax_intake_field('ti_move_dist_old', 'number', 'move_distance_old', 'Distance from old home to new work/school (km)', true),
            tax_intake_field('ti_move_dist_new', 'number', 'move_distance_new', 'Distance from new home to new work/school (km)', true),
            tax_intake_field('ti_move_date', 'date', 'move_date', 'Move Date', true),
            tax_intake_field('ti_move_reason', 'select', 'move_reason', 'Why did you move?', true, [
                'options' => tax_intake_options([
                    'Started work at a new location',
                    'Started a business at a new location',
                    'Full-time student at a post-secondary school',
                ]),
            ]),
            tax_intake_field('ti_move_start_date', 'date', 'move_start_date', 'When did you start the new job/business/school?', true),

            tax_intake_heading('ti_move_new_place_heading', 'New Work / School Details'),
            tax_intake_field('ti_move_work_name', 'text', 'move_work_name', 'Name', true),
            tax_intake_field('ti_move_work_street', 'text', 'move_work_street', 'Street Address', true),
            tax_intake_field('ti_move_work_city', 'text', 'move_work_city', 'City', true),
            tax_intake_field('ti_move_work_province', 'select', 'move_work_province', 'Province / Territory', true, ['options' => $provinces]),

            tax_intake_heading('ti_move_travel_heading', 'Travel Summary'),
            tax_intake_field('ti_move_people', 'number', 'move_people', 'How many people moved?', true),
            tax_intake_field('ti_move_travel_method', 'text', 'move_travel_method', 'Travel method', true, [
                'placeholder' => 'e.g. Car, flight, train',
            ]),
            tax_intake_field('ti_move_km', 'number', 'move_km_travelled', 'Kilometers travelled', true),

            tax_intake_heading('ti_move_costs_heading', 'Moving Costs'),
            tax_intake_money('ti_move_transport', 'move_cost_transport', 'Transportation and storage (moving belongings)'),
            tax_intake_money('ti_move_travel_cost', 'move_cost_travel', 'Travel costs during the move'),
            tax_intake_money('ti_move_temp_living', 'move_cost_temp_living', 'Temporary living costs'),
            tax_intake_field('ti_move_nights', 'number', 'move_temp_nights', 'Number of nights (temporary stay)'),
            tax_intake_money('ti_move_meals', 'move_cost_meals', 'Meal costs'),
            tax_intake_field('ti_move_meal_days', 'number', 'move_meal_days', 'Number of days for meal costs'),
            tax_intake_money('ti_move_incidental', 'move_cost_incidental', 'Incidental moving costs'),
            tax_intake_money('ti_move_vacant', 'move_cost_vacant_home', 'Costs to keep old home while vacant'),
            tax_intake_money('ti_move_lease', 'move_cost_lease_cancel', 'Lease cancellation cost'),
            tax_intake_yes_no('ti_move_sold_home', 'move_sold_old_home', 'Did you sell your old home because of this move?', false, '', [
                tax_intake_follow_up('costs', 'yes', 'number', 'Selling costs for old home', false, ['placeholder' => 'Amount in CAD']),
            ]),
            tax_intake_money('ti_move_reimbursement', 'move_reimbursement', 'Reimbursement / allowance you received'),
            tax_intake_money('ti_move_new_income', 'move_new_location_income', 'Income earned at new work/school location', true),
            tax_intake_money('ti_move_other', 'move_cost_other', 'Other moving costs'),

            // ── Step 14: Newcomer info ─────────────────────────────────────
            tax_intake_page('ti_page_newcomer', 'Newcomer Info', $yes('ti_newcomer')),
            tax_intake_field('ti_immigration_type', 'select', 'immigration_type', 'Immigration Type', true, [
                'options' => tax_intake_options(['Permanent Resident', 'Work Permit', 'Study Permit', 'Refugee', 'Other']),
            ]),
            tax_intake_field('ti_arrival_date', 'date', 'arrival_date', 'Date Entered Canada', true),
            tax_intake_field('ti_origin_country', 'text', 'origin_country', 'Country of Origin', true),
            tax_intake_field('ti_port_of_entry', 'text', 'port_of_entry', 'Port of Entry'),
            tax_intake_field('ti_world_income', 'number', 'world_income_before_arrival', 'World income earned before arriving in Canada'),
            tax_intake_field('ti_world_income_currency', 'text', 'world_income_currency', 'Currency', false, [
                'placeholder' => 'e.g. INR, USD',
            ]),
            tax_intake_money('ti_cad_income_before_residency', 'canadian_income_before_residency', 'Canadian income earned before establishing residency'),

            // ── Step 15: Additional details ────────────────────────────────
            tax_intake_page('ti_page_additional', 'Additional Details'),
            tax_intake_heading('ti_additional_heading', 'Anything else we should know?'),
            tax_intake_field('ti_notes', 'textarea', 'notes', 'Additional Notes (optional)', false, [
                'placeholder' => 'e.g. settlements, unreported side income, expenses you\'re unsure how to categorize…',
            ]),

            // ── Step 16: Documents ─────────────────────────────────────────
            tax_intake_page('ti_page_documents', 'Documents'),
            tax_intake_paragraph('ti_docs_intro', 'Upload boxes below are based on your answers. Accepted: PDF, images, Word, Excel, CSV, and text files — up to 10 MB each.'),
            tax_intake_upload('ti_sin_document', 'sin_document', 'SIN Document', [], true, 3),
            tax_intake_upload('ti_government_id', 'government_id', 'Government ID', [], false, 3),
            tax_intake_upload('ti_doc_t4', 't4_uploads', 'T4 – Pay Slips from Your Employer', $yes('ti_employed'), false, 10, 'Upload all T4 slips you received.'),
            tax_intake_upload('ti_doc_t2202', 'tuition_receipts', 'T2202 – Tuition Receipt from Your School', $yes('ti_student')),
            tax_intake_upload('ti_doc_t4e', 't4e_slips', 'T4E – Employment Insurance Slip', $yes('ti_ei')),
            tax_intake_upload('ti_doc_pension', 'pension_slips', 'T4A / T4A(OAS) / T4A(P) – Pension Slips', $yes('ti_pension')),
            tax_intake_upload('ti_doc_t5007_sa', 't5007_social_assistance', 'T5007 – Social Assistance Slip', $yes('ti_social_assistance')),
            tax_intake_upload('ti_doc_investments', 'investment_slips', 'T5 / T3 – Investment Slips + T3 – Trust Income Slips', $yes('ti_investments')),
            tax_intake_upload('ti_doc_t5008', 'capital_gains_docs', 'T5008 – Securities Transaction Slips + Capital Gains Supporting Documents', $yes('ti_sold_assets')),
            tax_intake_upload('ti_doc_rrsp', 'rrsp_receipts', 'RRSP / FHSA Contribution Receipts', $yes('ti_rrsp')),
            tax_intake_upload('ti_doc_medical', 'medical_receipts', 'Medical & Dental Receipts', $yes('ti_medical')),
            tax_intake_upload('ti_doc_child_care', 'childcare_receipts', 'Child Care Receipts', $yes('ti_child_care')),
            tax_intake_upload('ti_doc_donations', 'donation_receipts', 'Donation Receipts', $yes('ti_donations')),
            tax_intake_upload('ti_doc_property_tax', 'property_tax_bill', 'Property Tax Bill', $yes('ti_property_tax')),
            tax_intake_upload('ti_doc_moving', 'moving_receipts', 'Moving Expense Receipts', $yes('ti_moved')),
            tax_intake_upload('ti_doc_t2200', 't2200_declaration', 'T2200 – Employer Declaration', tax_intake_show_when([
                'ti_wfh' => 'yes',
                'ti_employment_expenses' => 'yes',
            ], 'any')),
            tax_intake_upload('ti_doc_t5007_wc', 't5007_workers_comp', 'T5007 – Workers\' Compensation Slip', $yes('ti_workers_comp')),
            tax_intake_field('ti_other_docs_types', 'checkbox', 'other_document_types', 'Other documents — which types are you uploading?', false, [
                'options' => tax_intake_options([
                    'T4', 'T4A', 'T4E', 'T5', 'T3', 'T2202', 'T5007', 'T4A(OAS)', 'T4A(P)', 'RL Slip (Quebec)',
                    'Donation Receipt', 'Medical Receipt', 'Rent Receipt', 'RRSP Receipt', 'Child Care Receipt',
                    'T2200', 'SIN Document', 'Government ID', 'Property Tax Bill', 'Moving Receipts', 'Other',
                ]),
            ]),
            tax_intake_upload('ti_other_docs', 'other_documents', 'Other documents', [], false, 10, 'Anything not covered above. You can also send more later via Submit Tax Documents.'),

            // ── Step 17: Review & submit ───────────────────────────────────
            tax_intake_page('ti_page_review', 'Review & Submit'),
            tax_intake_paragraph('ti_review_intro', 'Use Back to review any step. You can submit even if some optional details are missing — we\'ll follow up if we need anything.'),
            tax_intake_field('ti_declaration', 'checkbox', 'declaration', 'Declaration', true, [
                'options' => [[
                    'label' => 'I confirm the information I provided is accurate, and I accept responsibility for reviewing my return and for any tax owing.',
                    'value' => 'agreed',
                ]],
            ]),
            tax_intake_field('ti_terms', 'checkbox', 'terms_accepted', 'Terms & Conditions', true, [
                'options' => [[
                    'label' => 'I agree to Verma Accounting\'s Terms & Conditions, including the limitation of liability, indemnification, and client responsibility clauses.',
                    'value' => 'agreed',
                ]],
            ]),
            tax_intake_field('ti_signature', 'text', 'signature_full_name', 'Your Full Legal Name', true, [
                'helpText' => 'Typing your name serves as your electronic signature.',
            ]),
        ],
        'settings' => [
            'templateVersion' => tax_intake_template_version(),
            'submitLabel' => 'Sign & Submit My Tax Information',
            'showInSubmitMenu' => true,
            'successMessage' => 'Your tax information has been submitted. Our team will review everything and prepare your return. We\'ll reach out if we need anything else.',
            'firstPageTitle' => 'About You',
            'taxYear' => [
                'enabled' => true,
                'label' => 'Select Tax Year',
                'prompt' => 'Which tax year are you filing for? Your info is encrypted & secure, and it takes about 15 minutes.',
                'years' => tax_intake_tax_years(),
            ],
            'dataMatch' => [
                'enabled' => true,
                'fieldIds' => ['ti_dob', 'ti_sin'],
                'title' => 'Welcome back!',
                'message' => 'We found your information from a previous filing. Would you like to pre-fill this form with it?',
                'confirmLabel' => 'Pre-fill my information',
                'declineLabel' => 'No, start fresh',
            ],
        ],
    ];
}

/**
 * Ensure the published Tax Intake form exists, and upgrade it once when the template version changes.
 */
function ensure_tax_intake_form(): void
{
    $repo = new FormRepository();
    $slug = tax_intake_form_slug();
    $existing = $repo->findBySlug($slug, false);
    $description = 'Personal tax intake — about you, address, tax situation, income, deductions, documents, and e-signature.';

    if ($existing === null) {
        $repo->create([
            'slug' => $slug,
            'title' => 'File Your Tax Return',
            'description' => $description,
            'schema' => tax_intake_default_schema(),
            'status' => 'published',
        ]);
        return;
    }

    $schema = $repo->decodeSchema($existing);
    if ((int) ($schema['settings']['templateVersion'] ?? 1) >= tax_intake_template_version()) {
        return;
    }

    $repo->update((int) $existing['id'], [
        'description' => $description,
        'schema' => tax_intake_default_schema(),
    ]);
}
