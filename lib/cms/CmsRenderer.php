<?php

declare(strict_types=1);

/**
 * Builds complete public HTML documents for CMS content.
 *
 * Order: doctype/html → head metadata & robots → canonical & social → JSON-LD → shared CSS & head assets
 * → shared header → page body → shared footer → body-end scripts → closing tags.
 */
final class CmsRenderer
{
    /** Pages and city pages show only their body inside <main>; blog home and service-area add listings. */
    public const TEMPLATES = [
        'full-width' => ['label' => 'Body only', 'types' => ['page', 'city']],
        'article-index' => ['label' => 'Article listing (blog home)', 'types' => ['page']],
        'service-areas' => ['label' => 'City directory (service areas)', 'types' => ['page']],
        'article' => ['label' => 'Article', 'types' => ['article']],
    ];

    public const ARTICLES_PER_PAGE = 12;

    private static array $mediaByUrl = [];

    /** @var list<array{name: string, path: string}>|null breadcrumbs of the page being rendered, for the header */
    private static ?array $crumbs = null;

    public static function mediaByUrl(string $url): ?array
    {
        if (array_key_exists($url, self::$mediaByUrl)) {
            return self::$mediaByUrl[$url];
        }
        $path = preg_replace('#^/media/#', '', strtok($url, '?') ?: $url);
        $stmt = Database::instance()->pdo()->prepare('SELECT id FROM cms_media WHERE path = ? LIMIT 1');
        $stmt->execute([$path]);
        $id = $stmt->fetchColumn();
        return self::$mediaByUrl[$url] = $id ? (new CmsMediaRepository())->find((int) $id) : null;
    }

    /** @return array{url: string, alt: string, width: int, height: int, srcset: string}|null */
    public static function featuredImage(array $content): ?array
    {
        if (!empty($content['featured_media_id'])) {
            $m = (new CmsMediaRepository())->find((int) $content['featured_media_id']);
            if ($m) {
                return [
                    'url' => $m['url'],
                    'alt' => trim((string) ($content['featured_alt'] ?? '')) ?: (string) $m['alt'],
                    'width' => $m['width'],
                    'height' => $m['height'],
                    'srcset' => $m['srcset'],
                    'caption' => (string) $m['caption'],
                    'credit' => (string) $m['credit'],
                ];
            }
        }
        $url = trim((string) ($content['featured_image_url'] ?? ''));
        if ($url !== '') {
            return ['url' => $url, 'alt' => trim((string) ($content['featured_alt'] ?? '')), 'width' => 0, 'height' => 0, 'srcset' => '', 'caption' => '', 'credit' => ''];
        }
        return null;
    }

    public static function templateFor(array $content): string
    {
        $type = (string) $content['type'];
        $template = (string) ($content['template'] ?? 'default');
        if ($type === 'article') {
            return 'article';
        }
        if ($type === 'page' && isset(self::TEMPLATES[$template]) && in_array('page', self::TEMPLATES[$template]['types'], true)) {
            return $template;
        }
        return 'full-width';
    }

    /** URL-based breadcrumbs: every ancestor path that is a live CMS page (or a hand-written page). */
    public static function breadcrumbs(array $content, string $path): array
    {
        $crumbs = [['name' => 'Home', 'path' => '/']];
        if ($path === '/') {
            return $crumbs;
        }
        $repo = new CmsContentRepository();
        $segments = CmsPath::segments($path);
        $prefix = '';
        foreach (array_slice($segments, 0, -1) as $seg) {
            $prefix .= '/' . $seg;
            $parent = $repo->findByPath($prefix);
            if ($parent && CmsContentRepository::isLive($parent)) {
                $crumbs[] = ['name' => (string) $parent['title'], 'path' => $prefix];
            } elseif (CmsPath::legacyPageExists($prefix)) {
                $crumbs[] = ['name' => ucwords(str_replace('-', ' ', rawurldecode($seg))), 'path' => $prefix];
            }
        }
        $crumbs[] = ['name' => (string) (($content['h1'] ?? '') ?: $content['title']), 'path' => $path];
        return $crumbs;
    }

    /**
     * Prepare everything a template needs.
     *
     * @param array{preview?: bool, page?: int, path?: string} $opts
     */
    public static function context(array $content, array $opts = []): array
    {
        $repo = new CmsContentRepository();
        $path = $opts['path'] ?? (string) $content['path'];
        $page = max(1, (int) ($opts['page'] ?? 1));
        $content = CmsCityDefaults::apply($content);
        $seo = CmsCityDefaults::applySeo($content, $repo->seo((int) $content['id']));
        $folder = $content['folder_id'] ? (new CmsFolderRepository())->find((int) $content['folder_id']) : null;
        $template = self::templateFor($content);

        $body = (string) ($content['body_html'] ?? '');
        if ($content['type'] === 'article' && function_exists('blog_prepare_content_html')) {
            $body = blog_prepare_content_html($body);
        }
        $body = CmsHtml::enhanceImages($body, new CmsMediaRepository());
        $body = CmsBlocks::expand($body, $path, !empty($opts['preview']));
        if (str_contains($body, '{{contact_form}}')) {
            $body = str_replace('{{contact_form}}', self::contactForm(), $body);
        }
        if (str_contains($body, '{{company_') || str_contains($body, '{{social_links}}')) {
            $body = strtr($body, self::companyReplacements());
        }

        $faqs = array_values(array_filter($content['faqs'] ?? [], static fn ($f) => trim((string) ($f['question'] ?? '')) !== '' && trim((string) ($f['answer'] ?? '')) !== ''));
        $bodyFaqs = [];
        if (function_exists('blog_extract_faqs')) {
            $bodyFaqs = blog_extract_faqs($body);
        }

        $city = $content['type'] === 'city' ? $repo->city((int) $content['id']) : null;
        $terms = $repo->contentTerms((int) $content['id']);

        $listing = null;
        $serviceAreas = null;
        if ($template === 'article-index') {
            $total = $repo->countPublished('article');
            $totalPages = max(1, (int) ceil($total / self::ARTICLES_PER_PAGE));
            $listing = [
                'items' => $page <= $totalPages ? $repo->published('article', self::ARTICLES_PER_PAGE, ($page - 1) * self::ARTICLES_PER_PAGE) : [],
                'total' => $total,
                'page' => $page,
                'total_pages' => $totalPages,
                'out_of_range' => $page > $totalPages,
            ];
        } elseif ($template === 'service-areas') {
            $serviceAreas = $repo->serviceAreas();
        }

        $effective = CmsSeo::effective($content, $seo, $folder, ['page' => $page, 'preview' => !empty($opts['preview']), 'path' => $path]);
        $breadcrumbs = self::breadcrumbs($content, $path);
        $webpageType = match ($template) {
            'article-index', 'service-areas' => 'CollectionPage',
            default => 'WebPage',
        };
        $schema = CmsStructuredData::build($content, $effective, [
            'breadcrumbs' => $breadcrumbs,
            'faqs' => array_merge($faqs, $bodyFaqs),
            'city' => $city,
            'terms' => $terms,
            'seo' => $seo,
            'webpage_type' => $webpageType,
            'service_areas' => $serviceAreas ?? [],
        ]);

        return [
            'content' => $content,
            'seo' => $seo,
            'folder' => $folder,
            'effective' => $effective,
            'schema' => $schema,
            'template' => $template,
            'body' => $body,
            'faqs' => $faqs,
            'city' => $city,
            'terms' => $terms,
            'categories' => array_values(array_filter($terms, static fn ($t) => $t['kind'] === 'category')),
            'tags' => array_values(array_filter($terms, static fn ($t) => $t['kind'] === 'tag')),
            'breadcrumbs' => $breadcrumbs,
            'featured' => self::featuredImage($content),
            'related' => $content['type'] === 'article' ? $repo->related((int) $content['id'], 3) : [],
            'listing' => $listing,
            'service_areas' => $serviceAreas,
            'path' => $path,
            'page' => $page,
            'preview' => !empty($opts['preview']),
        ];
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public static function render(array $content, array $opts = []): array
    {
        $ctx = self::context($content, $opts);
        if ($ctx['listing'] !== null && $ctx['listing']['out_of_range'] && $ctx['page'] > 1) {
            return self::notFound();
        }
        $bodyHtml = self::template($ctx['template'], $ctx);
        self::$crumbs = $ctx['breadcrumbs'];
        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        if ($ctx['effective']['index'] === 'noindex' || $ctx['preview']) {
            $headers['X-Robots-Tag'] = $ctx['effective']['index'] . ', ' . $ctx['effective']['follow'];
        }
        $links = [];
        if ($ctx['listing'] !== null) {
            $base = CmsSiteConfig::baseUrl() . $ctx['path'];
            if ($ctx['page'] > 1) {
                $links[] = ['prev', $ctx['page'] === 2 ? $base : $base . '?page=' . ($ctx['page'] - 1)];
            }
            if ($ctx['page'] < $ctx['listing']['total_pages']) {
                $links[] = ['next', $base . '?page=' . ($ctx['page'] + 1)];
            }
        }
        $html = self::document($ctx['effective'], $ctx['schema'], $bodyHtml, 'cms-page cms-type-' . $content['type'] . ' cms-tpl-' . $ctx['template'] . ($ctx['path'] === '/' ? ' is-home' : ''), $ctx['path'], $links, $ctx['preview']);
        self::$crumbs = null;
        return ['status' => 200, 'headers' => $headers, 'body' => $html];
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public static function notFound(int $status = 404): array
    {
        $config = CmsSiteConfig::get();
        $title = $status === 410 ? 'This page has been removed' : (string) $config['not_found_title'];
        $effective = [
            'title' => $title . (string) $config['title_suffix'],
            'description' => (string) $config['default_description'],
            'robots' => 'noindex, follow',
            'index' => 'noindex',
            'follow' => 'follow',
            'canonical' => '',
            'social' => null,
            'language' => (string) $config['language'],
        ];
        $body = self::template('not-found', ['title' => $title, 'html' => (string) $config['not_found_html'], 'status' => $status]);
        $html = self::document($effective, [], $body, 'cms-page cms-not-found', '/__404', [], false);
        return ['status' => $status, 'headers' => ['Content-Type' => 'text/html; charset=UTF-8', 'X-Robots-Tag' => 'noindex'], 'body' => $html];
    }

    public static function template(string $name, array $cms): string
    {
        $file = PROJECT_ROOT . '/templates/cms/' . basename($name) . '.php';
        if (!is_file($file)) {
            $file = PROJECT_ROOT . '/templates/cms/default.php';
        }
        ob_start();
        try {
            (static function (string $__file, array $cms): void {
                require $__file;
            })($file, $cms);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * @param list<array> $schema
     * @param list<array{0: string, 1: string}> $links extra <link rel> tags
     */
    public static function document(array $effective, array $schema, string $bodyHtml, string $bodyClass, string $path, array $links = [], bool $preview = false): string
    {
        $config = CmsSiteConfig::get();
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out = [];
        $out[] = '<!DOCTYPE html>';
        $out[] = '<html lang="' . $h((string) $effective['language']) . '">';
        $out[] = '<head>';
        $out[] = '<meta charset="UTF-8">';
        $out[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $out[] = '<title>' . $h((string) $effective['title']) . '</title>';
        $out[] = '<meta name="description" content="' . $h((string) $effective['description']) . '">';
        $out[] = '<meta name="robots" content="' . $h((string) $effective['robots']) . '">';
        if (($effective['canonical'] ?? '') !== '' && !$preview) {
            $out[] = '<link rel="canonical" href="' . $h((string) $effective['canonical']) . '">';
        }
        foreach ($links as [$rel, $href]) {
            $out[] = '<link rel="' . $h($rel) . '" href="' . $h($href) . '">';
        }
        $social = $effective['social'] ?? null;
        if (is_array($social)) {
            $locale = str_replace('-', '_', (string) $effective['language']);
            $out[] = '<meta property="og:type" content="' . $h($social['type']) . '">';
            $out[] = '<meta property="og:title" content="' . $h($social['title']) . '">';
            $out[] = '<meta property="og:description" content="' . $h($social['description']) . '">';
            $out[] = '<meta property="og:url" content="' . $h((string) $effective['canonical']) . '">';
            $out[] = '<meta property="og:site_name" content="' . $h((string) $config['site_name']) . '">';
            $out[] = '<meta property="og:locale" content="' . $h($locale) . '">';
            if ($social['image'] !== '') {
                $out[] = '<meta property="og:image" content="' . $h($social['image']) . '">';
                if ($social['image_size']) {
                    $out[] = '<meta property="og:image:width" content="' . (int) $social['image_size'][0] . '">';
                    $out[] = '<meta property="og:image:height" content="' . (int) $social['image_size'][1] . '">';
                }
                if ($social['image_alt'] !== '') {
                    $out[] = '<meta property="og:image:alt" content="' . $h($social['image_alt']) . '">';
                }
            }
            $out[] = '<meta name="twitter:card" content="' . $h($social['twitter_card']) . '">';
            $out[] = '<meta name="twitter:title" content="' . $h($social['title']) . '">';
            $out[] = '<meta name="twitter:description" content="' . $h($social['description']) . '">';
            if ($social['image'] !== '') {
                $out[] = '<meta name="twitter:image" content="' . $h($social['image']) . '">';
                if ($social['image_alt'] !== '') {
                    $out[] = '<meta name="twitter:image:alt" content="' . $h($social['image_alt']) . '">';
                }
            }
        }
        foreach ($schema as $item) {
            $out[] = '<script type="application/ld+json">' . CmsStructuredData::encode($item) . '</script>';
        }
        $out[] = '<link rel="icon" type="image/jpeg" href="/images/verma-accounting-favicon.jpg">';
        $out[] = '<link rel="apple-touch-icon" href="/images/verma-accounting-favicon.jpg">';
        $out[] = '<meta name="theme-color" content="#1e3a8a">';
        $styles = array_values(array_unique(CmsSiteConfig::lines((string) $config['shared_styles'])));
        $origins = [];
        foreach ($styles as $href) {
            if (str_starts_with($href, 'https://fonts.googleapis.com')) {
                $origins['https://fonts.googleapis.com'] = '';
                $origins['https://fonts.gstatic.com'] = ' crossorigin';
            } elseif (preg_match('#^(https://[^/]+)/#', $href, $m)) {
                $origins[$m[1]] = '';
            }
        }
        foreach ($origins as $origin => $cross) {
            $out[] = '<link rel="preconnect" href="' . $h($origin) . '"' . $cross . '>';
        }
        $out[] = '<link rel="preload" href="/fonts/roboto-latin.woff2" as="font" type="font/woff2" crossorigin>';
        $out[] = '<link rel="alternate" type="text/markdown" href="/llms.txt" title="LLM-friendly site summary (llms.txt)">';
        foreach ($styles as $href) {
            $out[] = '<link rel="stylesheet" href="' . $h(CmsStylesheets::versionedUrl($href)) . '">';
        }
        $out[] = CmsSiteConfig::headExtras();
        $out[] = '</head>';
        $out[] = '<body class="' . $h($bodyClass) . '">';
        $out[] = CmsSiteConfig::bodyStartExtras();
        if ($preview) {
            $out[] = '<div class="cms-preview-bar" role="status">Preview — this page is not public. Search engines are told not to index it.</div>';
        }
        $out[] = self::expandPlaceholders((string) $config['header_html'], $path, true, $preview);
        $out[] = $bodyHtml;
        $out[] = self::expandPlaceholders((string) $config['footer_html'], $path, true, $preview);
        foreach (array_values(array_unique(CmsSiteConfig::lines((string) $config['shared_scripts']))) as $src) {
            $out[] = '<script src="' . $h(CmsStylesheets::versionedUrl($src)) . '" defer></script>';
        }
        $out[] = CmsSiteConfig::bodyEndExtras();
        $out[] = '</body>';
        $out[] = '</html>';
        return implode("\n", array_filter($out, static fn ($l) => $l !== ''));
    }

    public static function placeholders(): array
    {
        return [
            '{{site_header}}' => 'The standard site header and navigation (menus stay up to date automatically)',
            '{{site_footer}}' => 'The standard site footer',
            '{{submit_menu}}' => 'List of links to the forms shown in the Submit Document menu',
            '{{site_cta}}' => 'The site call-to-action link',
            '{{contact_form}}' => 'The site inquiry form (name, phone, email, message); also works in page bodies',
            '{{site_name}}' => 'Site name',
            '{{base_url}}' => 'Public base URL',
            '{{year}}' => 'Current year',
            '{{company_phone}}' => 'Company phone number (Company details); also works in page bodies',
            '{{company_phone_link}}' => 'tel: link for the company phone, for href="…"',
            '{{company_email}}' => 'Company email address',
            '{{company_email_link}}' => 'mailto: link for the company email, for href="…"',
            '{{company_address}}' => 'Company address on one line',
            '{{company_hours}}' => 'Business hours',
            '{{social_links}}' => 'Icons for the social profiles that are switched on',
            '{{block:name}}' => 'A reusable block from Website → Blocks',
        ];
    }

    /** @return array<string, string> company placeholders => HTML */
    public static function companyReplacements(): array
    {
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return [
            '{{company_phone}}' => $h(CmsCompany::phone()),
            '{{company_phone_link}}' => $h(CmsCompany::phoneHref()),
            '{{company_email}}' => $h(CmsCompany::email()),
            '{{company_email_link}}' => $h(CmsCompany::emailHref()),
            '{{company_address}}' => $h(CmsCompany::addressLine()),
            '{{company_hours}}' => $h(CmsCompany::hours()),
            '{{social_links}}' => CmsCompany::socialLinksHtml(),
        ];
    }

    public static function expandPlaceholders(string $html, string $path = '/', bool $blocks = true, bool $preview = false): string
    {
        if (!str_contains($html, '{{')) {
            return $html;
        }
        if ($blocks) {
            $html = CmsBlocks::expand($html, $path, $preview);
        }
        $h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $replacements = [
            '{{site_name}}' => $h((string) CmsSiteConfig::value('site_name')),
            '{{base_url}}' => $h(CmsSiteConfig::baseUrl()),
            '{{year}}' => gmdate('Y'),
        ] + self::companyReplacements();
        if (str_contains($html, '{{site_header}}')) {
            $replacements['{{site_header}}'] = self::partial('site-nav', ['canonicalPath' => $path, 'siteCrumbs' => self::$crumbs ?? [['name' => 'Home', 'path' => '/']]]);
        }
        if (str_contains($html, '{{site_footer}}')) {
            $replacements['{{site_footer}}'] = self::partial('site-footer', []);
        }
        if (str_contains($html, '{{submit_menu}}')) {
            $items = '';
            foreach (site_submit_menu_forms() as $f) {
                $items .= '<li><a href="' . $h((string) $f['url']) . '">' . $h((string) $f['title']) . '</a></li>';
            }
            $replacements['{{submit_menu}}'] = '<ul class="cms-submit-menu">' . $items . '</ul>';
        }
        if (str_contains($html, '{{contact_form}}')) {
            $replacements['{{contact_form}}'] = self::contactForm();
        }
        if (str_contains($html, '{{site_cta}}')) {
            $cta = site_cta_resolve();
            $replacements['{{site_cta}}'] = '<a class="cta-button primary" href="' . $h((string) $cta['url']) . '">' . $h((string) ($cta['hero_label'] ?: 'Get started')) . '</a>';
        }
        return strtr($html, $replacements);
    }

    /** The current hand-written header or footer markup, for seeding editable site chrome. */
    public static function chromeMarkup(string $part): string
    {
        return trim(self::partial($part === 'footer' ? 'site-footer' : 'site-nav', ['canonicalPath' => '/']));
    }

    private static function contactForm(): string
    {
        return self::partial('site-inquiry-form', ['includeService' => false]);
    }

    private static function partial(string $name, array $vars): string
    {
        ob_start();
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            include $__file;
        })(PROJECT_ROOT . '/components/' . $name . '.php', $vars);
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function formatDate(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        $ts = strtotime($utc . ' UTC');
        if (!$ts) {
            return '';
        }
        try {
            $tz = function_exists('app_timezone') ? app_timezone() : new DateTimeZone('America/Toronto');
        } catch (Throwable) {
            $tz = new DateTimeZone('UTC');
        }
        return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('F j, Y');
    }

    public static function readingTime(string $html): string
    {
        return max(1, (int) ceil(CmsHtml::wordCount($html) / 200)) . ' min read';
    }
}
