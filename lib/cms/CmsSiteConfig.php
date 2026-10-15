<?php

declare(strict_types=1);

/**
 * Site-wide website configuration, stored in app_settings with a revision trail.
 * Code fields are trusted-admin input (cms.code) and are echoed verbatim; nothing is ever executed as PHP.
 */
final class CmsSiteConfig
{
    public const SETTING_KEY = 'cms_site_config';

    /** Fields only users with cms.code may change. */
    public const CODE_FIELDS = [
        'header_html', 'footer_html', 'head_markup', 'global_css', 'global_js', 'head_scripts',
        'body_start_scripts', 'body_end_scripts', 'verification_tags', 'shared_styles', 'shared_scripts',
        'not_found_html', 'analytics_ga4_id',
    ];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'site_name' => 'Verma Accounting & Financial Services',
            'base_url' => '',
            'language' => 'en-CA',
            'title_suffix' => ' | Verma Accounting',
            'default_description' => 'Verma Accounting offers personal & corporate tax, bookkeeping, and payroll across Ontario.',
            'default_og_image' => '/images/og-image.jpg',
            'default_og_image_alt' => 'Verma Accounting & Financial Services',
            'twitter_card' => 'summary_large_image',
            'robots_index' => 'index',
            'robots_follow' => 'follow',
            'max_snippet' => -1,
            'max_image_preview' => 'large',
            'max_video_preview' => -1,
            'home_content_id' => 0,
            'header_html' => '{{site_header}}',
            'footer_html' => '{{site_footer}}',
            'head_markup' => '',
            'global_css' => '',
            'global_js' => '',
            'head_scripts' => '',
            'body_start_scripts' => '',
            'body_end_scripts' => '',
            'verification_tags' => '',
            'analytics_ga4_id' => '',
            'shared_styles' => implode("\n", [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                '/css/styles.css?v=86',
                '/css/header.css?v=3',
                '/css/cms.css?v=1',
            ]),
            'shared_scripts' => implode("\n", [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
                '/components/js/site-header.js?v=1',
                '/components/js/engagement-modal.js?v=2',
                'https://elfsightcdn.com/platform.js',
            ]),
            'not_found_title' => 'Page not found',
            'not_found_html' => '<p>Sorry, we could not find that page. It may have moved or no longer exists.</p>'
                . '<p><a class="cta-button primary" href="/">Go to the homepage</a> <a class="cta-button secondary" href="/contact">Contact us</a></p>',
            'schema_organization' => 1,
            'schema_website' => 1,
            'sitemap_pages' => 1,
            'sitemap_articles' => 1,
            'sitemap_cities' => 1,
            'sitemap_legacy' => 1,
            'sitemap_exclude' => '',
            'robots_txt' => self::defaultRobotsTxt(),
            'city_similarity_threshold' => 70,
            'thin_content_words' => 300,
        ] + CmsCompany::defaults();
    }

    public static function defaultRobotsTxt(): string
    {
        $file = PROJECT_ROOT . '/robots.txt';
        if (is_file($file)) {
            return (string) preg_replace('#^(\s*Sitemap:\s*)https?://[^/\s]+#mi', '$1{{base_url}}', (string) file_get_contents($file));
        }
        return "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /data/\n\nSitemap: {{base_url}}/sitemap.xml\n";
    }

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $stored = [];
        try {
            $stored = (new SettingsRepository())->get(self::SETTING_KEY, []);
        } catch (Throwable) {
            $stored = [];
        }
        self::$cache = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        return self::$cache;
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return self::get()[$key] ?? $default;
    }

    public static function baseUrl(): string
    {
        $configured = trim((string) self::value('base_url', ''));
        if ($configured !== '' && preg_match('#^https?://[^/\s]+$#i', rtrim($configured, '/'))) {
            return rtrim($configured, '/');
        }
        $base = function_exists('app_base_url') ? app_base_url() : '';
        return $base !== '' ? $base : 'https://vermaaccounting.ca';
    }

    /**
     * Validate and persist a new config, recording a revision of the result.
     *
     * @return list<string> validation errors (nothing saved when non-empty)
     */
    public static function save(array $input, bool $canCode, string $comment = ''): array
    {
        $current = self::get();
        [$next, $errors] = self::candidate($input, $canCode);
        if ($errors !== []) {
            return $errors;
        }
        if ($next === $current) {
            return [];
        }
        (new SettingsRepository())->set(self::SETTING_KEY, $next);
        self::$cache = null;
        self::recordRevision($next, $comment);
        CmsCache::flush();
        return [];
    }

    /** @return list<string> */
    public static function validate(array $config): array
    {
        $errors = [];
        if (trim((string) $config['site_name']) === '') {
            $errors[] = 'Site name is required.';
        }
        $base = trim((string) $config['base_url']);
        if ($base !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?/?$#i', $base)) {
            $errors[] = 'Public base URL must look like https://example.com (no path).';
        }
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', (string) $config['language'])) {
            $errors[] = 'Language must be a code like en or en-CA.';
        }
        foreach (['robots_index' => ['index', 'noindex'], 'robots_follow' => ['follow', 'nofollow']] as $key => $allowed) {
            if (!in_array($config[$key], $allowed, true)) {
                $errors[] = 'Invalid default for ' . $key . '.';
            }
        }
        if (!in_array($config['max_image_preview'], ['none', 'standard', 'large'], true)) {
            $errors[] = 'Image preview must be none, standard or large.';
        }
        foreach (['shared_styles' => 'stylesheet', 'shared_scripts' => 'script'] as $key => $label) {
            foreach (self::lines((string) $config[$key]) as $url) {
                if (!preg_match('#^(https://[^\s"\'<>]+|/[^\s"\'<>]*)$#', $url) || str_starts_with($url, '//')) {
                    $errors[] = 'Shared ' . $label . ' "' . $url . '" must be an https:// URL or a /path on this site.';
                }
            }
        }
        if ($config['analytics_ga4_id'] !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', (string) $config['analytics_ga4_id'])) {
            $errors[] = 'GA4 measurement ID must look like G-XXXXXXX.';
        }
        foreach (self::CODE_FIELDS as $field) {
            if (preg_match('/<\?(php|=)?/i', (string) ($config[$field] ?? ''))) {
                $errors[] = 'PHP code is not allowed in ' . str_replace('_', ' ', $field) . '.';
            }
        }
        foreach (['head_markup', 'verification_tags'] as $field) {
            $value = (string) $config[$field];
            if (preg_match('/<(title|link\s[^>]*rel=["\']?canonical|meta\s[^>]*name=["\']?(description|robots))/i', $value)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' cannot contain title, canonical, description or robots tags; those are generated per page.';
            }
        }
        if (strlen((string) $config['robots_txt']) > 65536) {
            $errors[] = 'robots.txt must be under 64 KB.';
        }
        foreach (self::lines((string) $config['sitemap_exclude']) as $rule) {
            if (!str_starts_with($rule, '/') || preg_match('/\s/', $rule)) {
                $errors[] = 'Sitemap exclusion "' . $rule . '" must be a path starting with / (end it with * to match a whole section).';
            }
        }
        if ((int) $config['city_similarity_threshold'] < 10 || (int) $config['city_similarity_threshold'] > 100) {
            $errors[] = 'City similarity threshold must be between 10 and 100.';
        }
        return array_merge($errors, CmsCompany::validate($config));
    }

    /** @return list<string> */
    public static function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $value)), static fn ($l) => $l !== '' && !str_starts_with($l, '#')));
    }

    public static function recordRevision(array $config, string $comment = ''): void
    {
        $user = Auth::currentUser();
        Database::instance()->pdo()->prepare('INSERT INTO cms_site_config_revisions (config_json, comment, user_id, user_name, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), mb_substr($comment, 0, 255), Auth::userId(), (string) ($user['name'] ?? ''), now_iso()]);
    }

    /** @return list<array> */
    public static function revisions(int $limit = 30): array
    {
        return Database::instance()->pdo()->query('SELECT id, comment, user_name, created_at FROM cms_site_config_revisions ORDER BY id DESC LIMIT ' . max(1, $limit))->fetchAll();
    }

    public static function revision(int $id): ?array
    {
        $stmt = Database::instance()->pdo()->prepare('SELECT * FROM cms_site_config_revisions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['config'] = array_merge(self::defaults(), json_decode((string) $row['config_json'], true) ?: []);
        return $row;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /** Use an unsaved configuration for the rest of this request (previews only). */
    public static function override(array $config): void
    {
        self::$cache = array_merge(self::defaults(), $config);
    }

    /**
     * Apply submitted input to the current config without saving (for preview and validation).
     *
     * @return array{0: array, 1: list<string>} candidate config and validation errors
     */
    public static function candidate(array $input, bool $canCode): array
    {
        $next = self::get();
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $input) || (in_array($key, self::CODE_FIELDS, true) && !$canCode)) {
                continue;
            }
            $next[$key] = is_int($default) ? (int) $input[$key] : str_replace("\r\n", "\n", (string) $input[$key]);
            if (str_starts_with($key, 'company_') || str_starts_with($key, 'social_')) {
                $next[$key] = is_int($next[$key]) ? $next[$key] : trim($next[$key]);
            }
        }
        return [$next, self::validate($next)];
    }

    /** @return list<string> keys whose values differ */
    public static function changedKeys(array $a, array $b): array
    {
        $out = [];
        foreach (array_keys(self::defaults()) as $key) {
            if ((string) ($a[$key] ?? '') !== (string) ($b[$key] ?? '')) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /** Non-blocking advice about a configuration. @return list<string> */
    public static function warnings(array $config): array
    {
        $out = [];
        if (!str_contains((string) $config['header_html'], '{{site_header}}') && !str_contains((string) $config['header_html'], '{{submit_menu}}')) {
            $out[] = 'The header does not use {{site_header}} or {{submit_menu}}, so the Submit Document menu will not update when forms change.';
        }
        if (trim((string) $config['base_url']) === '') {
            $out[] = 'Public base URL is empty; canonical URLs and the sitemap use the address of each request. Set it to the live domain in production.';
        }
        if ($config['robots_index'] === 'noindex') {
            $out[] = 'The site-wide default is noindex: every page without its own setting is hidden from search engines.';
        }
        foreach (['head_scripts', 'body_start_scripts', 'body_end_scripts', 'head_markup'] as $field) {
            if (preg_match('/<script[^>]+src=["\']?http:\/\//i', (string) $config[$field])) {
                $out[] = ucfirst(str_replace('_', ' ', $field)) . ' loads a script over http://, which browsers block on https pages.';
            }
        }
        if ($config['analytics_ga4_id'] !== '' && str_contains((string) $config['head_scripts'] . $config['body_end_scripts'], 'googletagmanager.com/gtag/js')) {
            $out[] = 'GA4 is set and a gtag script is also pasted into the scripts; analytics may count every visit twice.';
        }
        return $out;
    }

    /**
     * Global head additions shared with the hand-written PHP pages (they keep their own title/meta).
     */
    public static function headExtras(): string
    {
        $c = self::get();
        $out = '';
        if (trim((string) $c['verification_tags']) !== '') {
            $out .= $c['verification_tags'] . "\n";
        }
        if ($c['analytics_ga4_id'] !== '') {
            $id = htmlspecialchars((string) $c['analytics_ga4_id'], ENT_QUOTES);
            $out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $id . '"></script>' . "\n"
                . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $id . "');</script>\n";
        }
        if (trim((string) $c['head_markup']) !== '') {
            $out .= $c['head_markup'] . "\n";
        }
        if (trim((string) $c['head_scripts']) !== '') {
            $out .= $c['head_scripts'] . "\n";
        }
        $out .= CmsStylesheets::headLinks();
        if (trim((string) $c['global_css']) !== '') {
            $out .= '<style id="cms-global-css">' . str_ireplace('</style', '<\/style', (string) $c['global_css']) . "</style>\n";
        }
        return $out;
    }

    public static function bodyStartExtras(): string
    {
        $v = (string) self::value('body_start_scripts', '');
        return trim($v) === '' ? '' : $v . "\n";
    }

    public static function bodyEndExtras(): string
    {
        $c = self::get();
        $out = '';
        if (trim((string) $c['global_js']) !== '') {
            $out .= '<script id="cms-global-js">' . str_ireplace('</script', '<\/script', (string) $c['global_js']) . "</script>\n";
        }
        if (trim((string) $c['body_end_scripts']) !== '') {
            $out .= $c['body_end_scripts'] . "\n";
        }
        return $out;
    }
}
