<?php

declare(strict_types=1);

/**
 * The site header menu, edited under Website → Navigation and rendered by components/site-nav.php.
 *
 * A top-level item with links under it (or with a dynamic source) opens a dropdown; its own URL then becomes
 * the "View all" link at the bottom of that dropdown. Hidden items stay saved but are not shown, and links to
 * CMS pages that are not live (drafts, scheduled, expired) are left out until the page is published.
 */
final class CmsNavigation
{
    public const SETTING_KEY = 'cms_navigation';

    public const MAX_ITEMS = 12;
    public const MAX_CHILDREN = 30;

    /** Dropdowns filled automatically: key => label. */
    public const SOURCES = [
        'submit_forms' => 'Forms marked "Show in Submit Document menu"',
    ];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        $link = static fn (string $label, string $url, bool $newTab = false): array =>
            ['label' => $label, 'url' => $url, 'visible' => true, 'new_tab' => $newTab];
        return [
            'items' => [
                ['key' => 'home', 'label' => 'Home', 'url' => '/', 'visible' => true, 'new_tab' => false, 'source' => '', 'all_label' => '', 'children' => []],
                [
                    'key' => 'services', 'label' => 'Our Services', 'url' => '/services', 'visible' => true, 'new_tab' => false,
                    'source' => '', 'all_label' => 'View all services',
                    'children' => [
                        $link('Accounting', '/accounting'),
                        $link('Bookkeeping', '/bookkeeping'),
                        $link('Payroll', '/payroll'),
                        $link('Personal Tax', '/personal-tax'),
                        $link('Corporate Tax', '/corporate-tax'),
                        $link('Business Registration', '/business-registration'),
                        $link('Business Loans', '/loan'),
                        $link('Real Estate', 'https://owningottawa.com/', true),
                    ],
                ],
                ['key' => 'submit', 'label' => 'Submit Document', 'url' => '', 'visible' => true, 'new_tab' => false, 'source' => 'submit_forms', 'all_label' => '', 'children' => []],
                [
                    'key' => 'resources', 'label' => 'Resources', 'url' => '', 'visible' => true, 'new_tab' => false,
                    'source' => '', 'all_label' => '',
                    'children' => [
                        $link('Resources', '/resources'),
                        $link('Blog', '/blog'),
                    ],
                ],
                ['key' => 'about', 'label' => 'About Us', 'url' => '/about', 'visible' => true, 'new_tab' => false, 'source' => '', 'all_label' => '', 'children' => []],
            ],
            'show_phone' => true,
            'button_label' => 'Book Appointment',
            'button_url' => '/contact',
        ];
    }

    public static function get(): array
    {
        if (self::$cache === null) {
            $stored = null;
            try {
                $stored = (new SettingsRepository())->get(self::SETTING_KEY);
            } catch (Throwable) {
                $stored = null;
            }
            if (is_array($stored) && !isset($stored['show_phone']) && isset($stored['phone'])) {
                $stored['show_phone'] = trim((string) $stored['phone']) !== '';
            }
            self::$cache = is_array($stored) && isset($stored['items']) ? array_merge(self::defaults(), $stored) : self::defaults();
        }
        return self::$cache;
    }

    public static function isCustomised(): bool
    {
        try {
            return is_array((new SettingsRepository())->get(self::SETTING_KEY));
        } catch (Throwable) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * Validate and store a menu. Items use the shape of defaults()['items'].
     *
     * @return list<string> errors
     */
    public static function save(array $items, array $settings = []): array
    {
        $errors = [];
        $clean = [];
        $keys = [];
        foreach (array_values($items) as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $item = self::cleanLink($raw, true, 'Menu item ' . ($i + 1), $errors);
            if ($item === null) {
                continue;
            }
            $item['source'] = isset(self::SOURCES[(string) ($raw['source'] ?? '')]) ? (string) $raw['source'] : '';
            $item['all_label'] = mb_substr(trim((string) ($raw['all_label'] ?? '')), 0, 60);
            $item['children'] = [];
            foreach (array_values((array) ($raw['children'] ?? [])) as $j => $rawChild) {
                $child = is_array($rawChild) ? self::cleanLink($rawChild, false, '"' . $item['label'] . '" link ' . ($j + 1), $errors) : null;
                if ($child !== null) {
                    $item['children'][] = $child;
                }
            }
            if (count($item['children']) > self::MAX_CHILDREN) {
                $errors[] = '"' . $item['label'] . '" has more than ' . self::MAX_CHILDREN . ' links.';
            }
            if ($item['url'] === '' && $item['children'] === [] && $item['source'] === '') {
                $errors[] = '"' . $item['label'] . '" needs a URL or links under it.';
            }
            $key = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($raw['key'] ?? ''))) ?: CmsPath::slugify($item['label']) ?: 'menu';
            $base = $key;
            for ($n = 2; isset($keys[$key]); $n++) {
                $key = $base . '-' . $n;
            }
            $keys[$key] = true;
            $clean[] = ['key' => $key] + $item;
        }
        if (count($clean) > self::MAX_ITEMS) {
            $errors[] = 'The header has room for at most ' . self::MAX_ITEMS . ' top-level items.';
        }

        $current = self::get();
        $showPhone = (bool) ($settings['show_phone'] ?? $current['show_phone']);
        $buttonLabel = mb_substr(trim((string) ($settings['button_label'] ?? $current['button_label'])), 0, 40);
        $buttonUrl = trim((string) ($settings['button_url'] ?? $current['button_url']));
        if ($buttonLabel !== '' && !self::validUrl($buttonUrl)) {
            $errors[] = 'The header button needs a URL starting with /, https://, mailto: or tel:.';
        }
        if ($errors) {
            return array_values(array_unique($errors));
        }

        (new SettingsRepository())->set(self::SETTING_KEY, [
            'items' => $clean,
            'show_phone' => $showPhone,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ]);
        self::$cache = null;
        CmsCache::flush();
        ActivityLog::record('cms.navigation_saved', 'cms_navigation', null, ['items' => count($clean)]);
        return [];
    }

    /** Go back to the built-in menu. */
    public static function restoreDefaults(): void
    {
        (new SettingsRepository())->set(self::SETTING_KEY, null);
        self::$cache = null;
        CmsCache::flush();
        ActivityLog::record('cms.navigation_reset', 'cms_navigation', null, []);
    }

    private static function cleanLink(array $raw, bool $topLevel, string $name, array &$errors): ?array
    {
        $label = mb_substr(trim((string) ($raw['label'] ?? '')), 0, 60);
        $url = trim((string) ($raw['url'] ?? ''));
        if ($label === '' && $url === '') {
            return null;
        }
        if ($label === '') {
            $errors[] = $name . ' needs a label.';
        }
        if ($url !== '' && !self::validUrl($url)) {
            $errors[] = ($label !== '' ? '"' . $label . '"' : $name) . ': the URL must start with /, #, https://, mailto: or tel:.';
        } elseif ($url === '' && !$topLevel) {
            $errors[] = ($label !== '' ? '"' . $label . '"' : $name) . ' needs a URL.';
        }
        if ($url !== '' && $url[0] === '/' && !str_starts_with($url, '//')) {
            $parts = explode('#', $url, 2);
            $url = CmsPath::normalize($parts[0]) . (isset($parts[1]) ? '#' . $parts[1] : '');
        }
        return [
            'label' => $label,
            'url' => $url,
            'visible' => !empty($raw['visible']),
            'new_tab' => !empty($raw['new_tab']),
        ];
    }

    public static function validUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/\s/', $url)) {
            return false;
        }
        if (str_starts_with($url, '//')) {
            return false;
        }
        return (bool) preg_match('#^(/|\#|https?://[^/]|mailto:|tel:)#i', $url);
    }

    /**
     * The menu as rendered: hidden items and links to unpublished CMS pages removed, dynamic sources filled in.
     *
     * @return array{nav: list<array>, phone_label: string, phone_href: string, button_label: string, button_url: string}
     */
    public static function forSite(): array
    {
        $config = self::get();
        $notLive = self::notLive($config['items']);
        $show = static fn (array $l): bool => !empty($l['visible']) && !isset($notLive[self::pathOf((string) $l['url'])]);
        $nav = [];
        foreach ($config['items'] as $item) {
            if (empty($item['visible'])) {
                continue;
            }
            // Keep Service Areas out of the header; cities stay in the footer and on-page links.
            $key = (string) ($item['key'] ?? '');
            $path = self::pathOf((string) ($item['url'] ?? ''));
            if ($key === 'service_areas' || $path === '/service-area') {
                continue;
            }
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                if ($show($child)) {
                    $children[] = ['url' => $child['url'], 'title' => $child['label'], 'external' => !empty($child['new_tab'])];
                }
            }
            if (($item['source'] ?? '') === 'submit_forms' && function_exists('site_submit_menu_forms')) {
                foreach (site_submit_menu_forms() as $f) {
                    $children[] = ['url' => $f['url'], 'title' => $f['title'], 'external' => false];
                }
            }
            $urlLive = $item['url'] !== '' && !isset($notLive[self::pathOf((string) $item['url'])]);
            if ($children === []) {
                if ($urlLive) {
                    $nav[] = ['type' => 'link', 'url' => $item['url'], 'label' => $item['label'], 'external' => !empty($item['new_tab'])];
                }
                continue;
            }
            $nav[] = [
                'type' => 'mega', 'id' => $item['key'], 'label' => $item['label'],
                'compact' => count($children) <= 4,
                'items' => $children,
                'all' => $urlLive ? ['url' => $item['url'], 'label' => $item['all_label'] !== '' ? $item['all_label'] : 'View all'] : null,
            ];
        }
        $showPhone = !empty($config['show_phone']);
        return [
            'nav' => $nav,
            'phone_label' => $showPhone ? CmsCompany::phone() : '',
            'phone_href' => $showPhone ? CmsCompany::phoneHref() : '',
            'button_label' => (string) $config['button_label'],
            'button_url' => (string) $config['button_url'],
        ];
    }

    /** @return array<string, true> */
    private static function notLive(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $link) {
                $path = self::pathOf((string) ($link['url'] ?? ''));
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }
        try {
            return (new CmsContentRepository())->notLivePaths($paths);
        } catch (Throwable) {
            return [];
        }
    }

    /** Site path of an internal URL ('' for external, anchors and empty). */
    public static function pathOf(string $url): string
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return '';
        }
        return CmsPath::normalize((string) strtok($url, '#?'));
    }

    /** Internal paths the menu links to (for the SEO audit's orphan check). */
    public static function paths(): array
    {
        $out = [];
        foreach (self::get()['items'] as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $link) {
                $path = self::pathOf((string) ($link['url'] ?? ''));
                if ($path !== '' && !empty($link['visible']) && !empty($item['visible'])) {
                    $out[] = $path;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Breadcrumbs for any URL: Home, then each parent path that is a real page, then the current page.
     * Names come from the menu label, then the CMS page title, then the URL segment.
     *
     * @return list<array{name: string, path: string}>
     */
    public static function breadcrumbs(string $path, ?string $currentTitle = null): array
    {
        $path = CmsPath::normalize($path);
        $crumbs = [['name' => 'Home', 'path' => '/']];
        if ($path === '/') {
            return $crumbs;
        }
        $labels = [];
        foreach (self::get()['items'] as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $link) {
                $p = self::pathOf((string) ($link['url'] ?? ''));
                if ($p !== '' && $p !== '/' && !isset($labels[$p])) {
                    $labels[$p] = (string) $link['label'];
                }
            }
        }
        $repo = null;
        $cmsTitle = static function (string $p) use (&$repo): ?string {
            try {
                $repo ??= new CmsContentRepository();
                $row = $repo->findByPath($p);
            } catch (Throwable) {
                return null;
            }
            return $row && CmsContentRepository::isLive($row) ? (string) $row['title'] : null;
        };
        $segments = CmsPath::segments($path);
        $prefix = '';
        foreach ($segments as $i => $seg) {
            $prefix .= '/' . $seg;
            $fallback = ucwords(str_replace('-', ' ', rawurldecode($seg)));
            if ($i === count($segments) - 1) {
                $name = trim((string) $currentTitle) ?: ($labels[$prefix] ?? null) ?: $cmsTitle($prefix) ?: $fallback;
                $crumbs[] = ['name' => $name, 'path' => $prefix];
                break;
            }
            $title = $cmsTitle($prefix);
            if ($title !== null || CmsPath::legacyPageExists($prefix)) {
                $crumbs[] = ['name' => $labels[$prefix] ?? $title ?? $fallback, 'path' => $prefix];
            }
        }
        return $crumbs;
    }

    /** Top-level items a page can be placed under: key => label. A plain link becomes a dropdown when it gets one. */
    public static function parents(?string $exceptPath = null): array
    {
        $out = [];
        foreach (self::get()['items'] as $item) {
            if ($exceptPath !== null && self::pathOf((string) $item['url']) === CmsPath::normalize($exceptPath)) {
                continue;
            }
            $out[(string) $item['key']] = (string) $item['label'];
        }
        return $out;
    }

    /** Key of the top-level item that links to this path, if any. */
    public static function topKeyFor(string $path): string
    {
        $path = CmsPath::normalize($path);
        foreach (self::get()['items'] as $item) {
            if (self::pathOf((string) $item['url']) === $path) {
                return (string) $item['key'];
            }
        }
        return '';
    }

    /** Where a path sits in the menu: '' (not in it), 'top', or the parent item's key. */
    public static function placement(string $path): string
    {
        $path = CmsPath::normalize($path);
        foreach (self::get()['items'] as $item) {
            if (self::pathOf((string) $item['url']) === $path) {
                return 'top';
            }
            foreach ($item['children'] ?? [] as $child) {
                if (self::pathOf((string) $child['url']) === $path) {
                    return (string) $item['key'];
                }
            }
        }
        return '';
    }

    /**
     * Put a page in the menu, move it, or take it out ('' = not in the menu). Links to the page elsewhere in
     * the menu are replaced, so a page appears once. $oldPath follows a URL change.
     *
     * @return list<string> errors
     */
    public static function place(string $path, string $label, string $placement, ?string $oldPath = null): array
    {
        $path = CmsPath::normalize($path);
        $oldPath = $oldPath !== null ? CmsPath::normalize($oldPath) : $path;
        $current = self::placement($oldPath);
        if (($current === $placement && $oldPath === $path) || ($current === '' && $placement === '')) {
            return [];
        }
        $config = self::get();
        $items = $config['items'];
        $existing = null;
        $keptTop = false;
        $matches = static fn (array $l): bool => in_array(self::pathOf((string) $l['url']), [$path, $oldPath], true);
        foreach ($items as $i => $item) {
            if ($matches($item) && (($item['children'] ?? []) !== [] || ($item['source'] ?? '') !== '')) {
                if ($placement !== 'top') {
                    return ['This page opens the "' . $item['label'] . '" dropdown in the menu. Move or remove that dropdown under Navigation instead.'];
                }
                $items[$i]['url'] = $path;
                $keptTop = true;
                continue;
            }
            if ($matches($item)) {
                $existing ??= $item;
                unset($items[$i]);
                continue;
            }
            foreach ($item['children'] ?? [] as $j => $child) {
                if ($matches($child)) {
                    $existing ??= $child;
                    unset($items[$i]['children'][$j]);
                }
            }
            $items[$i]['children'] = array_values($items[$i]['children'] ?? []);
        }
        $items = array_values($items);
        $link = [
            'label' => (string) ($existing['label'] ?? '') ?: mb_substr($label, 0, 60),
            'url' => $path,
            'visible' => true,
            'new_tab' => false,
        ];
        if ($placement === 'top') {
            if (!$keptTop) {
                $items[] = $link + ['source' => '', 'all_label' => '', 'children' => []];
            }
        } elseif ($placement !== '') {
            $found = false;
            foreach ($items as $i => $item) {
                if ((string) $item['key'] === $placement) {
                    if ($items[$i]['children'] === [] && $item['url'] !== '' && ($item['all_label'] ?? '') === '') {
                        $items[$i]['all_label'] = $item['label'];
                    }
                    $items[$i]['children'][] = $link;
                    $found = true;
                }
            }
            if (!$found) {
                return ['That menu no longer exists. Choose another place in the header menu.'];
            }
        }
        return self::save($items, $config);
    }
}
