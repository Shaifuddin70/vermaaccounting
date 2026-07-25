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
 * Page-level structured data for key landing pages.
 *
 * @return array<string, mixed>|null
 */
function seo_page_schema(string $path): ?array
{
    $path = seo_normalize_path($path);
    $base = seo_site_base_url();
    $meta = seo_meta_for_path($path);

    if ($path === '/contact') {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            '@id' => $base . '/contact#webpage',
            'url' => $base . '/contact',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $base . '/#website'],
            'about' => ['@id' => $base . '/#business'],
            'mainEntity' => ['@id' => $base . '/#business'],
        ];
    }

    if ($path === '/services') {
        $services = [
            ['name' => 'Bookkeeping Services', 'url' => $base . '/bookkeeping'],
            ['name' => 'Accounting Services', 'url' => $base . '/accounting'],
            ['name' => 'Payroll Services', 'url' => $base . '/payroll'],
            ['name' => 'Personal Tax Preparation', 'url' => $base . '/personal-tax'],
            ['name' => 'Corporate Tax Filing', 'url' => $base . '/corporate-tax'],
            ['name' => 'Business Registration', 'url' => $base . '/business-registration'],
            ['name' => 'Business Loan Services', 'url' => $base . '/loan'],
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $base . '/services#webpage',
            'url' => $base . '/services',
            'name' => $meta['title'],
            'description' => $meta['description'],
            'isPartOf' => ['@id' => $base . '/#website'],
            'about' => ['@id' => $base . '/#business'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => array_map(
                    static fn(array $service, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $service['name'],
                        'url' => $service['url'],
                    ],
                    $services,
                    array_keys($services)
                ),
            ],
        ];
    }

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

function seo_local_business_schema(): array
{
    $logo = brand_logo_url();
    if (str_starts_with($logo, '/')) {
        $logo = 'https://vermaaccounting.ca' . $logo;
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'AccountingService',
        '@id' => seo_site_base_url() . '/#business',
        'name' => 'Verma Accounting & Financial Services',
        'url' => seo_site_base_url() . '/',
        'image' => $logo,
        'logo' => $logo,
        'description' => 'Certified accountants providing personal tax, corporate tax, bookkeeping, payroll, and business registration services across Ontario, Canada.',
        'email' => 'info@vermaaccounting.ca',
        'telephone' => '+1-613-318-6478',
        'priceRange' => '$$',
        'currenciesAccepted' => 'CAD',
        'address' => [
            '@type' => 'PostalAddress',
            'addressRegion' => 'ON',
            'addressCountry' => 'CA',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 42.9849,
            'longitude' => -81.2453,
        ],
        'areaServed' => [
            '@type' => 'State',
            'name' => 'Ontario',
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
        ],
        'sameAs' => [
            'https://www.facebook.com/profile.php?id=61570236688782',
            'https://www.instagram.com/vermaaccounting.ca/',
        ],
    ];
}
