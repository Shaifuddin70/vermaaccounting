<?php

declare(strict_types=1);

/**
 * Authoritative list of published, indexable city landing pages for internal linking.
 * Sourced from cms_contents (type=city) via CmsContentRepository::serviceAreas().
 */
final class CmsServiceAreas
{
    public const INDEX_PATH = '/service-area';
    public const INDEX_LABEL = 'Service Areas';

    /** Preferred cities for compact homepage / service-page callouts (matched by name). */
    public const FEATURED_NAMES = [
        'Ottawa',
        'Barrhaven',
        'Kanata',
        'Nepean',
        'Gloucester',
        'Orléans',
        'Orleans',
        'Stittsville',
        'Manotick',
        'Riverside South',
    ];

    private static bool $normalized = false;

    /** @return list<array{path: string, name: string, region: string}> */
    public static function all(): array
    {
        // Heal legacy /locations/{city} URLs once so links match /service-area/{city}.
        if (!self::$normalized && class_exists('CmsContentService')) {
            self::$normalized = true;
            try {
                CmsContentService::normalizeCityPathsUnderServiceArea();
            } catch (Throwable $e) {
                error_log('CmsServiceAreas normalize: ' . $e->getMessage());
            }
        }
        try {
            return (new CmsContentRepository())->serviceAreas();
        } catch (Throwable) {
            return [];
        }
    }

    public static function reset(): void
    {
        self::$normalized = false;
    }

    public static function count(): int
    {
        return count(self::all());
    }

    public static function indexPath(): string
    {
        return self::INDEX_PATH;
    }

    /**
     * Featured subset for compact UI. Falls back to the first N cities when none match.
     *
     * @return list<array{path: string, name: string, region: string}>
     */
    public static function featured(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $all = self::all();
        if ($all === []) {
            return [];
        }
        $byName = [];
        foreach ($all as $area) {
            $byName[mb_strtolower($area['name'])] = $area;
        }
        $out = [];
        $seen = [];
        foreach (self::FEATURED_NAMES as $name) {
            $key = mb_strtolower($name);
            if (!isset($byName[$key]) || isset($seen[$byName[$key]['path']])) {
                continue;
            }
            $out[] = $byName[$key];
            $seen[$byName[$key]['path']] = true;
            if (count($out) >= $limit) {
                return $out;
            }
        }
        foreach ($all as $area) {
            if (isset($seen[$area['path']])) {
                continue;
            }
            $out[] = $area;
            $seen[$area['path']] = true;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Other cities for cross-linking from a city page (excludes current path).
     *
     * @return list<array{path: string, name: string, region: string}>
     */
    public static function nearby(string $currentPath, int $limit = 12): array
    {
        $currentPath = CmsPath::normalize($currentPath);
        $out = [];
        foreach (self::all() as $area) {
            if (CmsPath::normalize($area['path']) === $currentPath) {
                continue;
            }
            $out[] = $area;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Contextual service → city links for a service page.
     * Anchor: "{Service label} in {City}" when $serviceLabel is set.
     *
     * @return list<array{path: string, name: string, region: string, label: string}>
     */
    public static function forService(?string $serviceLabel = null, int $limit = 8): array
    {
        $out = [];
        foreach (self::featured($limit) as $area) {
            $label = $serviceLabel !== null && $serviceLabel !== ''
                ? $serviceLabel . ' in ' . $area['name']
                : $area['name'];
            $out[] = $area + ['label' => $label];
        }
        return $out;
    }
}
