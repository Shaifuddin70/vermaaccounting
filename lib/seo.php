<?php

declare(strict_types=1);

/**
 * Per-page SEO title and description (≤60 / ≤160 chars).
 *
 * @return array{title: string, description: string}
 */
function seo_meta_for_path(string $path): array
{
    $path = seo_normalize_path($path);

    $pages = [
        '/' => [
            'title' => 'Accounting & Tax Services in Ontario | Verma Accounting',
            'description' => 'Verma Accounting offers personal & corporate tax, bookkeeping, and payroll across Ontario. Certified accountants maximize savings and keep you CRA-compliant.',
        ],
        '/accounting' => [
            'title' => 'Accounting Services in Ontario | Verma Accounting',
            'description' => 'Reliable accounting services for Ontario businesses and individuals: financial statements, reporting, and year-end support from certified professionals.',
        ],
        '/bookkeeping' => [
            'title' => 'Bookkeeping Services in Ontario | Verma Accounting',
            'description' => 'Accurate, up-to-date bookkeeping for Ontario businesses. We handle reconciliations, reports, and clean books so you stay organized and CRA-ready.',
        ],
        '/payroll' => [
            'title' => 'Payroll Services in Ontario | Verma Accounting',
            'description' => 'Stress-free payroll for Ontario businesses: accurate pay runs, remittances, and year-end T4s handled by experienced payroll professionals.',
        ],
        '/personal-tax' => [
            'title' => 'Personal Tax Preparation in Ontario | Verma Accounting',
            'description' => 'Maximize your refund with expert personal tax preparation across Ontario. Certified accountants ensure accurate, CRA-compliant filing every year.',
        ],
        '/corporate-tax' => [
            'title' => 'Corporate Tax Filing in Ontario | Verma Accounting',
            'description' => 'Corporate tax filing and planning for Ontario businesses. Minimize liabilities and stay CRA-compliant with experienced corporate tax accountants.',
        ],
        '/business-registration' => [
            'title' => 'Business Registration in Ontario | Verma Accounting',
            'description' => 'Register your Ontario business the right way. We handle incorporation, HST, and setup so you launch quickly and stay compliant from day one.',
        ],
        '/loan' => [
            'title' => 'Business Loan Services in Ontario | Verma Accounting',
            'description' => 'Get help securing business financing in Ontario. We prepare the statements and projections lenders need to approve your business loan faster.',
        ],
        '/resources' => [
            'title' => 'Tax & Accounting Resources | Verma Accounting Ontario',
            'description' => 'Free tax tips, key deadlines, and accounting resources for Ontario individuals and businesses from the team at Verma Accounting.',
        ],
        '/blog' => [
            'title' => 'Accounting & Tax Blog | Verma Accounting Ontario',
            'description' => 'Practical tax, bookkeeping, and accounting guides for Ontario individuals and businesses from Verma Accounting.',
        ],
        '/contact' => [
            'title' => 'Contact Verma Accounting | Ontario Tax Accountants',
            'description' => 'Contact Verma Accounting for tax, bookkeeping, and payroll help across Ontario. Call +1 (613) 318-6478 or email info@vermaaccounting.ca.',
        ],
        '/about' => [
            'title' => 'About Verma Accounting | Ontario Tax Accountants',
            'description' => 'Learn about Verma Accounting: certified Ontario accountants with 10+ years helping individuals and businesses with tax, bookkeeping, and payroll.',
        ],
        '/privacy' => [
            'title' => 'Privacy Policy | Verma Accounting',
            'description' => 'Learn how Verma Accounting collects, uses, and protects personal information for tax, bookkeeping, payroll, and related services in Ontario.',
        ],
        '/services' => [
            'title' => 'Our Accounting Services in Ontario | Verma Accounting',
            'description' => 'Explore Verma Accounting services: personal and corporate tax, bookkeeping, payroll, business registration, and loans for Ontario clients.',
        ],
    ];

    return $pages[$path] ?? $pages['/'];
}

function seo_normalize_path(string $path): string
{
    $path = strtok($path, '?') ?: '/';
    if ($path === '' || $path === false) {
        $path = '/';
    }

    $lower = strtolower($path);
    if ($lower === '/index.php' || $lower === '/index') {
        return '/';
    }
    if (str_ends_with($lower, '.php')) {
        $path = substr($path, 0, -4) ?: '/';
    }

    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function seo_site_base_url(): string
{
    return 'https://vermaaccounting.ca';
}

/**
 * Absolute asset URL for schema/meta images.
 */
function seo_absolute_url(string $pathOrUrl): string
{
    $value = trim($pathOrUrl);
    if ($value === '') {
        return seo_site_base_url() . '/';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    return seo_site_base_url() . '/' . ltrim($value, '/');
}

/**
 * Catalog of public service pages for ItemList / Service schema.
 *
 * @return list<array{path: string, name: string, serviceType: string}>
 */
function seo_service_catalog(): array
{
    return [
        ['path' => '/accounting', 'name' => 'Accounting Services', 'serviceType' => 'Accounting'],
        ['path' => '/bookkeeping', 'name' => 'Bookkeeping Services', 'serviceType' => 'Bookkeeping'],
        ['path' => '/payroll', 'name' => 'Payroll Services', 'serviceType' => 'Payroll'],
        ['path' => '/personal-tax', 'name' => 'Personal Tax Preparation', 'serviceType' => 'Personal tax preparation'],
        ['path' => '/corporate-tax', 'name' => 'Corporate Tax Filing', 'serviceType' => 'Corporate tax filing'],
        ['path' => '/business-registration', 'name' => 'Business Registration', 'serviceType' => 'Business registration'],
        ['path' => '/loan', 'name' => 'Business Loan Services', 'serviceType' => 'Business financing'],
    ];
}

/**
 * Homepage FAQ pairs used for visible FAQ UI + FAQPage schema.
 *
 * @return list<array{question: string, answer: string}>
 */
function seo_home_faqs(): array
{
    return [
        [
            'question' => 'Do you offer online or remote accounting services?',
            'answer' => 'Yes. Using secure, cloud-based systems, we can manage your accounts, filings, and financial documents remotely, no matter where you are in Canada.',
        ],
        [
            'question' => 'Can you help with both personal and corporate taxes?',
            'answer' => 'Absolutely. We prepare and file personal and corporate tax returns with full CRA compliance while maximizing eligible deductions and credits.',
        ],
        [
            'question' => 'Do you assist with business registration?',
            'answer' => 'Yes. We handle CRA business numbers, GST/HST registration, payroll accounts, and other compliance steps to ensure your business is legally registered and ready to operate.',
        ],
        [
            'question' => 'How do you ensure accuracy in accounting and tax filings?',
            'answer' => 'Every ledger, statement, and return is double-checked. Our accountants combine professional expertise with advanced tools and secure systems to ensure precision and CRA compliance.',
        ],
        [
            'question' => 'Will I receive ongoing support after tax filing or setup?',
            'answer' => 'Yes. We provide continuous updates, insights, and guidance to help you make informed financial decisions throughout the year.',
        ],
        [
            'question' => 'What makes your bookkeeping and accounting services different?',
            'answer' => 'Our approach blends accuracy, clarity, and compliance. Using cloud-based tools, we provide real-time access to organized records, detailed reports, and audit-ready financial statements.',
        ],
    ];
}

/**
 * Build FAQPage JSON-LD from Q&A pairs.
 *
 * @param list<array{question: string, answer: string}> $faqs
 * @return array<string, mixed>|null
 */
function seo_faq_page_schema(array $faqs): ?array
{
    $entities = [];
    foreach ($faqs as $faq) {
        $q = trim((string) ($faq['question'] ?? ''));
        $a = trim((string) ($faq['answer'] ?? ''));
        if ($q === '' || $a === '') {
            continue;
        }
        $entities[] = [
            '@type' => 'Question',
            'name' => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $a,
            ],
        ];
    }
    if ($entities === []) {
        return null;
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

/**
 * Page-level structured data for key landing pages.
 *
 * @return array<string, mixed>|null
 */
function seo_page_schema(string $path): ?array
{
    $path = seo_normalize_path($path);
    $base = seo_site_base_url();
    $meta = seo_meta_for_path($path);
    $businessId = $base . '/#business';
    $websiteId = $base . '/#website';

    if ($path === '/') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $base . '/#webpage',
            'url' => $base . '/',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $businessId],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => seo_absolute_url(brand_logo_url()),
            ],
        ];
    }

    if ($path === '/contact') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            '@id' => $base . '/contact#webpage',
            'url' => $base . '/contact',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $businessId],
            'mainEntity' => ['@id' => $businessId],
        ];
    }

    if ($path === '/about') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            '@id' => $base . '/about#webpage',
            'url' => $base . '/about',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $businessId],
            'mainEntity' => ['@id' => $businessId],
        ];
    }

    if ($path === '/services') {
        $services = seo_service_catalog();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $base . '/services#webpage',
            'url' => $base . '/services',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $businessId],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_map(
                    static fn(array $service, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $service['name'],
                        'url' => $base . $service['path'],
                    ],
                    $services,
                    array_keys($services)
                ),
            ],
        ];
    }

    if ($path === '/blog') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            '@id' => $base . '/blog#blog',
            'url' => $base . '/blog',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'publisher' => ['@id' => $businessId],
            'isPartOf' => ['@id' => $websiteId],
        ];
    }

    if ($path === '/resources') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $base . '/resources#webpage',
            'url' => $base . '/resources',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $businessId],
        ];
    }

    // Service pages already emit Service + FAQPage JSON-LD in their templates.
    return null;
}

/**
 * Resolve SEO meta for the current request, with optional overrides.
 *
 * @return array{title: string, description: string}
 */
function seo_resolve(?string $path = null, ?string $titleOverride = null, ?string $descriptionOverride = null): array
{
    $path = seo_normalize_path($path ?? ($_SERVER['REQUEST_URI'] ?? '/'));
    $meta = seo_meta_for_path($path);

    if ($titleOverride !== null && $titleOverride !== '') {
        $meta['title'] = $titleOverride;
    }
    if ($descriptionOverride !== null && $descriptionOverride !== '') {
        $meta['description'] = $descriptionOverride;
    }

    return $meta;
}

function seo_website_schema(): array
{
    $base = seo_site_base_url();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $base . '/#website',
        'url' => $base . '/',
        'name' => 'Verma Accounting & Financial Services',
        'description' => 'Certified accountants providing personal tax, corporate tax, bookkeeping, payroll, and business registration services across Ontario, Canada.',
        'publisher' => ['@id' => $base . '/#business'],
        'inLanguage' => 'en-CA',
    ];
}

function seo_local_business_schema(): array
{
    $base = seo_site_base_url();
    $logo = seo_absolute_url(brand_logo_url());

    return [
        '@context' => 'https://schema.org',
        '@type' => ['LocalBusiness', 'AccountingService'],
        '@id' => $base . '/#business',
        'name' => 'Verma Accounting & Financial Services',
        'alternateName' => 'Verma Accounting',
        'url' => $base . '/',
        'image' => $logo,
        'logo' => $logo,
        'description' => 'Certified accountants providing personal tax, corporate tax, bookkeeping, payroll, and business registration services across Ontario, Canada.',
        'email' => 'info@vermaaccounting.ca',
        'telephone' => '+1-613-318-6478',
        'priceRange' => '$$',
        'currenciesAccepted' => 'CAD',
        'paymentAccepted' => 'Cash, Credit Card, Debit Card, Interac e-Transfer',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Les Emmerson Drive',
            'addressLocality' => 'Nepean',
            'addressRegion' => 'ON',
            'postalCode' => 'K2J 7L6',
            'addressCountry' => 'CA',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 45.2691,
            'longitude' => -75.7518,
        ],
        'contactPoint' => [
            [
                '@type' => 'ContactPoint',
                'telephone' => '+1-613-318-6478',
                'contactType' => 'customer service',
                'email' => 'info@vermaaccounting.ca',
                'areaServed' => 'CA-ON',
                'availableLanguage' => ['English'],
                'hoursAvailable' => [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    'opens' => '09:00',
                    'closes' => '18:00',
                ],
            ],
        ],
        'areaServed' => [
            [
                '@type' => 'City',
                'name' => 'Nepean',
                'containedInPlace' => [
                    '@type' => 'City',
                    'name' => 'Ottawa',
                ],
            ],
            [
                '@type' => 'City',
                'name' => 'Ottawa',
            ],
            [
                '@type' => 'City',
                'name' => 'Toronto',
            ],
            [
                '@type' => 'State',
                'name' => 'Ontario',
            ],
            [
                '@type' => 'Country',
                'name' => 'Canada',
            ],
        ],
        'openingHoursSpecification' => [
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens' => '09:00',
                'closes' => '18:00',
            ],
        ],
        'knowsAbout' => [
            'Personal Tax Preparation',
            'Corporate Tax Filing',
            'Bookkeeping',
            'Payroll',
            'Business Registration',
            'Business Loans',
        ],
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'Accounting and tax services',
            'itemListElement' => array_map(
                static function (array $service) use ($base): array {
                    return [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => $service['name'],
                            'url' => $base . $service['path'],
                        ],
                    ];
                },
                seo_service_catalog()
            ),
        ],
        'sameAs' => [
            'https://www.facebook.com/profile.php?id=61570236688782',
            'https://www.instagram.com/vermaaccounting.ca/',
        ],
    ];
}
