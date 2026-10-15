<?php

declare(strict_types=1);

/**
 * Company contact details and social profiles, edited under Website → Site configuration → Company details
 * and used by every page, the CMS placeholders and the structured data.
 */
final class CmsCompany
{
    /** key => [label, icon class, example URL] */
    public const SOCIAL = [
        'facebook' => ['Facebook', 'fab fa-facebook-f', 'https://www.facebook.com/yourpage'],
        'instagram' => ['Instagram', 'fab fa-instagram', 'https://www.instagram.com/yourhandle/'],
        'linkedin' => ['LinkedIn', 'fab fa-linkedin-in', 'https://www.linkedin.com/company/yourcompany'],
        'x' => ['X (Twitter)', 'fab fa-twitter', 'https://x.com/yourhandle'],
        'youtube' => ['YouTube', 'fab fa-youtube', 'https://www.youtube.com/@yourchannel'],
        'tiktok' => ['TikTok', 'fab fa-tiktok', 'https://www.tiktok.com/@yourhandle'],
        'whatsapp' => ['WhatsApp', 'fab fa-whatsapp', 'https://wa.me/16135550123'],
        'google' => ['Google Business Profile', 'fab fa-google', 'https://g.page/yourbusiness'],
    ];

    /** Messaging links that are not profiles, so they stay out of schema.org sameAs. */
    private const NOT_PROFILES = ['whatsapp'];

    /** Config defaults merged into CmsSiteConfig::defaults(). */
    public static function defaults(): array
    {
        $out = [
            'company_phone' => '+1 (613) 318-6478',
            'company_email' => 'info@vermaaccounting.ca',
            'company_hours' => 'Mon – Sun: 9:00 AM – 6:00 PM',
            'company_street' => 'Les Emmerson Drive',
            'company_city' => 'Nepean',
            'company_region' => 'ON',
            'company_postal_code' => 'K2J 7L6',
            'company_country' => 'CA',
        ];
        $urls = [
            'facebook' => 'https://www.facebook.com/profile.php?id=61570236688782',
            'instagram' => 'https://www.instagram.com/vermaaccounting.ca/',
            'whatsapp' => 'https://wa.me/16133186478',
        ];
        foreach (array_keys(self::SOCIAL) as $key) {
            $out['social_' . $key . '_on'] = isset($urls[$key]) ? 1 : 0;
            $out['social_' . $key . '_url'] = $urls[$key] ?? '';
        }
        return $out;
    }

    /** @return list<string> */
    public static function validate(array $config): array
    {
        $errors = [];
        $digits = preg_replace('/\D/', '', (string) $config['company_phone']);
        if (strlen($digits) < 7 || strlen($digits) > 15 || !preg_match('/^[\d\s()+.\-]+$/', trim((string) $config['company_phone']))) {
            $errors[] = 'Company phone must be a phone number such as +1 (613) 555-0123.';
        }
        if (!filter_var(trim((string) $config['company_email']), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Company email must be a valid email address.';
        }
        foreach (['company_hours' => 120, 'company_street' => 191, 'company_city' => 100, 'company_region' => 60, 'company_postal_code' => 20, 'company_country' => 60] as $key => $max) {
            if (mb_strlen((string) $config[$key]) > $max) {
                $errors[] = ucfirst(str_replace(['company_', '_'], ['', ' '], $key)) . ' is too long.';
            }
        }
        foreach (self::SOCIAL as $key => [$label]) {
            $url = trim((string) $config['social_' . $key . '_url']);
            if ($url !== '' && !preg_match('#^https://[a-z0-9.-]+\.[a-z]{2,}(/[^\s"\'<>]*)?$#i', $url)) {
                $errors[] = $label . ' link must be a full https:// address.';
            } elseif ($url === '' && (int) $config['social_' . $key . '_on'] === 1) {
                $errors[] = $label . ' is switched on but has no link.';
            }
        }
        return $errors;
    }

    private static function value(string $key): string
    {
        return trim((string) CmsSiteConfig::value($key, ''));
    }

    public static function phone(): string
    {
        return self::value('company_phone');
    }

    /** E.164 form, e.g. +16133186478 (10-digit numbers are treated as North American). */
    public static function phoneE164(): string
    {
        $raw = self::phone();
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10 && !str_starts_with($raw, '+')) {
            $digits = '1' . $digits;
        }
        return '+' . $digits;
    }

    public static function phoneHref(): string
    {
        $e164 = self::phoneE164();
        return $e164 === '' ? '' : 'tel:' . $e164;
    }

    /** Phone for structured data, e.g. +1-613-318-6478. */
    public static function phoneSchema(): string
    {
        $e164 = self::phoneE164();
        if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $m)) {
            return '+1-' . $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        return $e164;
    }

    public static function email(): string
    {
        return self::value('company_email');
    }

    public static function emailHref(): string
    {
        return self::email() === '' ? '' : 'mailto:' . self::email();
    }

    public static function hours(): string
    {
        return self::value('company_hours');
    }

    /** @return array{street: string, city: string, region: string, postal_code: string, country: string} */
    public static function address(): array
    {
        return [
            'street' => self::value('company_street'),
            'city' => self::value('company_city'),
            'region' => self::value('company_region'),
            'postal_code' => self::value('company_postal_code'),
            'country' => self::value('company_country'),
        ];
    }

    /** One line, e.g. "Les Emmerson Drive, Nepean, ON K2J 7L6". */
    public static function addressLine(): string
    {
        $a = self::address();
        $last = trim($a['region'] . ' ' . $a['postal_code']);
        return implode(', ', array_filter([$a['street'], $a['city'], $last], static fn ($p) => $p !== ''));
    }

    /** schema.org PostalAddress, or null when no address is set. */
    public static function postalAddressSchema(): ?array
    {
        $a = self::address();
        if ($a['street'] === '' && $a['city'] === '') {
            return null;
        }
        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $a['street'],
            'addressLocality' => $a['city'],
            'addressRegion' => $a['region'],
            'postalCode' => $a['postal_code'],
            'addressCountry' => $a['country'],
        ], static fn ($v) => $v !== '');
    }

    /**
     * Social profiles that are switched on and have a link, in display order.
     *
     * @return list<array{key: string, label: string, icon: string, url: string}>
     */
    public static function socialLinks(): array
    {
        $out = [];
        foreach (self::SOCIAL as $key => [$label, $icon]) {
            $url = self::value('social_' . $key . '_url');
            if ($url !== '' && (int) CmsSiteConfig::value('social_' . $key . '_on', 0) === 1) {
                $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $url];
            }
        }
        return $out;
    }

    /** Profile URLs for schema.org sameAs. @return list<string> */
    public static function sameAs(): array
    {
        $out = [];
        foreach (self::socialLinks() as $link) {
            if (!in_array($link['key'], self::NOT_PROFILES, true)) {
                $out[] = $link['url'];
            }
        }
        return $out;
    }

    /** Icon links for the enabled social profiles (used by {{social_links}}). */
    public static function socialLinksHtml(string $class = 'company-social'): string
    {
        $links = self::socialLinks();
        if ($links === []) {
            return '';
        }
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div class="' . $h($class) . '">';
        foreach ($links as $link) {
            $html .= '<a href="' . $h($link['url']) . '" target="_blank" rel="noopener" aria-label="' . $h($link['label']) . '"><i class="' . $h($link['icon']) . '" aria-hidden="true"></i></a>';
        }
        return $html . '</div>';
    }
}

function company_phone(): string
{
    return CmsCompany::phone();
}

function company_phone_href(): string
{
    return CmsCompany::phoneHref();
}

function company_email(): string
{
    return CmsCompany::email();
}

function company_email_href(): string
{
    return CmsCompany::emailHref();
}
