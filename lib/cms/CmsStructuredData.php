<?php

declare(strict_types=1);

/**
 * JSON-LD for CMS pages. Only facts stored in the CMS or the existing business profile are used;
 * nothing (reviews, ratings, addresses) is invented.
 */
final class CmsStructuredData
{
    public const TYPES = [
        'WebSite' => 'WebSite (site-wide)',
        'Organization' => 'Organization / LocalBusiness (site-wide)',
        'WebPage' => 'WebPage',
        'BreadcrumbList' => 'BreadcrumbList',
        'BlogPosting' => 'Article / BlogPosting',
        'Person' => 'Author (Person)',
        'Service' => 'Service',
        'LocalBusiness' => 'Local business (city pages)',
        'FAQPage' => 'FAQPage',
    ];

    /**
     * @param array $content
     * @param array $effective CmsSeo::effective() result
     * @param array{breadcrumbs?: list<array{name: string, path: string}>, faqs?: list<array{question: string, answer: string}>, city?: array, terms?: list<array>, webpage_type?: string, seo?: array} $extra
     * @return list<array> JSON-LD objects (each with @context)
     */
    public static function build(array $content, array $effective, array $extra = []): array
    {
        $config = CmsSiteConfig::get();
        $base = CmsSiteConfig::baseUrl();
        $seo = $extra['seo'] ?? [];
        $disabled = array_flip((array) ($seo['schema']['disabled'] ?? []));
        $url = $effective['canonical_source'] === 'custom' ? $effective['url'] : $effective['canonical'];
        $businessId = $base . '/#business';
        $websiteId = $base . '/#website';
        $out = [];

        if ((int) $config['schema_organization'] === 1 && !isset($disabled['Organization']) && function_exists('seo_local_business_schema')) {
            $out[] = seo_local_business_schema();
        }
        if ((int) $config['schema_website'] === 1 && !isset($disabled['WebSite']) && function_exists('seo_website_schema')) {
            $out[] = seo_website_schema();
        }

        $breadcrumbs = $extra['breadcrumbs'] ?? [];
        if (!isset($disabled['WebPage'])) {
            $page = [
                '@context' => 'https://schema.org',
                '@type' => $extra['webpage_type'] ?? 'WebPage',
                '@id' => $url . '#webpage',
                'url' => $url,
                'name' => $effective['title'],
                'description' => $effective['description'],
                'inLanguage' => $effective['language'],
                'isPartOf' => ['@id' => $websiteId],
            ];
            if (!empty($content['updated_at'])) {
                $page['dateModified'] = self::iso((string) $content['updated_at']);
            }
            if (count($breadcrumbs) > 1 && !isset($disabled['BreadcrumbList'])) {
                $page['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
            }
            if ($effective['social']['image'] !== '') {
                $page['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $effective['social']['image']];
            }
            $out[] = $page;
        }

        if (count($breadcrumbs) > 1 && !isset($disabled['BreadcrumbList'])) {
            $items = [];
            foreach ($breadcrumbs as $i => $crumb) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $base . ($crumb['path'] === '/' ? '/' : $crumb['path']),
                ];
            }
            $out[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
        }

        $type = (string) ($content['type'] ?? 'page');
        $authorName = trim((string) ($content['author_name'] ?? ''));
        if ($type === 'article' && !isset($disabled['BlogPosting'])) {
            $article = [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                '@id' => $url . '#article',
                'headline' => mb_substr((string) (($content['h1'] ?? '') ?: $content['title']), 0, 110),
                'description' => $effective['description'],
                'inLanguage' => $effective['language'],
                'mainEntityOfPage' => ['@id' => $url . '#webpage'],
                'publisher' => ['@id' => $businessId],
                'isPartOf' => ['@type' => 'Blog', '@id' => $base . '/blog#blog'],
            ];
            if (!empty($content['published_at'])) {
                $article['datePublished'] = self::iso((string) $content['published_at']);
            }
            if (!empty($content['updated_at'])) {
                $article['dateModified'] = self::iso((string) $content['updated_at']);
            }
            if ($effective['social']['image'] !== '') {
                $article['image'] = [$effective['social']['image']];
            }
            if ($authorName !== '' && !isset($disabled['Person'])) {
                $article['author'] = ['@type' => 'Person', 'name' => $authorName, 'url' => $base . '/about'];
            } else {
                $article['author'] = ['@id' => $businessId];
            }
            $terms = $extra['terms'] ?? [];
            $keywords = array_values(array_map(static fn ($t) => $t['name'], array_filter($terms, static fn ($t) => $t['kind'] === 'tag')));
            $sections = array_values(array_map(static fn ($t) => $t['name'], array_filter($terms, static fn ($t) => $t['kind'] === 'category')));
            if ($keywords) {
                $article['keywords'] = implode(', ', $keywords);
            }
            if ($sections) {
                $article['articleSection'] = $sections[0];
            }
            $article['wordCount'] = CmsHtml::wordCount((string) ($content['body_html'] ?? ''));
            $out[] = $article;
        }

        $city = $extra['city'] ?? null;
        $serviceName = '';
        if ($type === 'city' && $city) {
            $serviceName = trim((string) ($city['primary_service'] ?? ''));
        }
        if ($serviceName !== '' && !isset($disabled['Service'])) {
            $service = [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                '@id' => $url . '#service',
                'name' => $type === 'city' && !empty($city['city']) ? $serviceName . ' in ' . $city['city'] : $serviceName,
                'serviceType' => $serviceName,
                'provider' => ['@id' => $businessId],
                'url' => $url,
                'description' => $effective['description'],
            ];
            if ($type === 'city' && !empty($city['city'])) {
                $service['areaServed'] = self::place($city);
            }
            $out[] = $service;
        }

        if ($type === 'city' && $city && (int) ($city['local_business'] ?? 0) === 1 && !isset($disabled['LocalBusiness'])) {
            $phone = trim((string) $city['phone']);
            $address = trim((string) $city['address']);
            if ($phone !== '' || $address !== '') {
                $local = [
                    '@context' => 'https://schema.org',
                    '@type' => ['LocalBusiness', 'AccountingService'],
                    '@id' => $url . '#localbusiness',
                    'name' => (string) $config['site_name'] . (!empty($city['city']) ? ' – ' . $city['city'] : ''),
                    'url' => $url,
                    'parentOrganization' => ['@id' => $businessId],
                    'areaServed' => self::place($city),
                ];
                if ($phone !== '') {
                    $local['telephone'] = $phone;
                }
                if (trim((string) $city['email']) !== '') {
                    $local['email'] = trim((string) $city['email']);
                }
                if ($address !== '') {
                    $local['address'] = array_filter([
                        '@type' => 'PostalAddress',
                        'streetAddress' => $address,
                        'addressLocality' => (string) $city['city'],
                        'addressRegion' => (string) $city['region'],
                        'addressCountry' => (string) $city['country'],
                    ]);
                }
                if (trim((string) $city['map_url']) !== '') {
                    $local['hasMap'] = trim((string) $city['map_url']);
                }
                $hours = self::openingHours($city['hours'] ?? []);
                if ($hours) {
                    $local['openingHoursSpecification'] = $hours;
                }
                if ($effective['social']['image'] !== '') {
                    $local['image'] = $effective['social']['image'];
                }
                $out[] = $local;
            }
        }

        $faqs = $extra['faqs'] ?? [];
        if ($faqs && !isset($disabled['FAQPage']) && function_exists('seo_faq_page_schema')) {
            $faq = seo_faq_page_schema($faqs);
            if ($faq !== null) {
                $faq['@id'] = $url . '#faq';
                $out[] = $faq;
            }
        }

        foreach ((array) ($seo['schema']['custom'] ?? []) as $custom) {
            if (is_array($custom) && self::validateObject($custom) === []) {
                $out[] = $custom;
            }
        }
        return $out;
    }

    private static function place(array $city): array
    {
        $place = ['@type' => 'City', 'name' => (string) $city['city']];
        if (!empty($city['region'])) {
            $place['containedInPlace'] = ['@type' => 'AdministrativeArea', 'name' => (string) $city['region']];
        }
        return $place;
    }

    /** @param list<array{days?: string|list<string>, opens?: string, closes?: string}> $hours */
    private static function openingHours(array $hours): array
    {
        $map = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
        $out = [];
        foreach ($hours as $row) {
            $days = is_array($row['days'] ?? null) ? $row['days'] : preg_split('/[\s,]+/', strtolower((string) ($row['days'] ?? '')));
            $named = [];
            foreach ($days ?: [] as $d) {
                $key = substr(strtolower(trim((string) $d)), 0, 3);
                if (isset($map[$key])) {
                    $named[] = $map[$key];
                }
            }
            $opens = (string) ($row['opens'] ?? '');
            $closes = (string) ($row['closes'] ?? '');
            if ($named && preg_match('/^\d{2}:\d{2}$/', $opens) && preg_match('/^\d{2}:\d{2}$/', $closes)) {
                $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $named, 'opens' => $opens, 'closes' => $closes];
            }
        }
        return $out;
    }

    private static function iso(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');
        return $ts ? gmdate('c', $ts) : $utc;
    }

    /** Safe serialisation for <script type="application/ld+json">. */
    public static function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Validate a custom JSON-LD block typed in the editor.
     *
     * @return array{errors: list<string>, data: ?array}
     */
    public static function parseCustom(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return ['errors' => [], 'data' => null];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['errors' => ['Custom structured data is not valid JSON: ' . json_last_error_msg()], 'data' => null];
        }
        $items = array_is_list($data) ? $data : [$data];
        $errors = [];
        foreach ($items as $i => $item) {
            foreach (self::validateObject(is_array($item) ? $item : []) as $e) {
                $errors[] = 'Block ' . ($i + 1) . ': ' . $e;
            }
        }
        return ['errors' => $errors, 'data' => $errors ? null : $items];
    }

    /** @return list<string> */
    public static function validateObject(array $item): array
    {
        $errors = [];
        $context = $item['@context'] ?? '';
        if (!is_string($context) || !preg_match('#^https?://schema\.org/?$#', $context)) {
            $errors[] = '@context must be https://schema.org';
        }
        if (empty($item['@type'])) {
            $errors[] = '@type is required';
        }
        $types = (array) ($item['@type'] ?? []);
        foreach ($types as $t) {
            if (in_array($t, ['Review', 'AggregateRating'], true)) {
                $errors[] = $t . ' markup is not allowed unless it reflects real, visible reviews; add it in the page body instead.';
            }
        }
        if (isset($item['aggregateRating']) || isset($item['review'])) {
            $errors[] = 'Ratings and reviews are not allowed in custom schema.';
        }
        return $errors;
    }
}
