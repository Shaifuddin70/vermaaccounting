<?php
declare(strict_types=1);

/** Shared helpers for the Website (CMS) admin pages. */

/** @return array{edit: bool, publish: bool, seo: bool, code: bool, redirects: bool} */
function cms_admin_caps(): array
{
    return [
        'edit' => Auth::can('cms.edit'),
        'publish' => Auth::can('cms.publish'),
        'seo' => Auth::can('cms.seo'),
        'code' => Auth::can('cms.code'),
        'redirects' => Auth::can('cms.redirects'),
    ];
}

function cms_admin_back(string $url, ?string $success = null, ?string $error = null): never
{
    if ($success !== null) {
        $_SESSION['flash_success'] = $success;
    }
    if ($error !== null) {
        $_SESSION['flash_error'] = $error;
    }
    header('Location: ' . $url, true, 303);
    exit;
}

function cms_admin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Location: /admin/cms', true, 303);
        exit;
    }
    if (!Auth::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        cms_admin_back((string) ($_SERVER['HTTP_REFERER'] ?? '/admin/cms'), null, 'Your session expired. Please try again.');
    }
}

function cms_admin_type_nav(string $type): string
{
    return match ($type) {
        'article' => 'cms-article',
        'city' => 'cms-city',
        default => 'cms-page',
    };
}

function cms_admin_type_plural(string $type): string
{
    return match ($type) {
        'article' => 'Blog articles',
        'city' => 'City pages',
        'page' => 'Pages',
        default => 'All content',
    };
}

/** Local datetime display for a UTC value. */
function cms_admin_date(?string $utc): string
{
    if (!$utc) {
        return '—';
    }
    return function_exists('app_format_datetime') ? app_format_datetime($utc) : $utc;
}

/**
 * Editor field values (keyed by input name) from stored content.
 *
 * @return array<string, mixed>
 */
function cms_admin_form_values(?array $content, array $seo, array $city, array $terms, string $type): array
{
    $c = $content ?? [];
    $schema = is_array($seo['schema'] ?? null) ? $seo['schema'] : [];
    $hours = is_array($city['hours'] ?? null) ? $city['hours'] : [];
    $cta = is_array($city['cta'] ?? null) ? $city['cta'] : [];
    return [
        'id' => (int) ($c['id'] ?? 0),
        'type' => $c['type'] ?? $type,
        'title' => (string) ($c['title'] ?? ''),
        'internal_name' => (string) ($c['internal_name'] ?? ''),
        'h1' => (string) ($c['h1'] ?? ''),
        'slug' => (string) ($c['slug'] ?? ''),
        'folder_id' => (int) ($c['folder_id'] ?? 0),
        'path' => (string) ($c['path'] ?? ''),
        'body_html' => (string) ($c['body_html'] ?? ''),
        'body_mode' => (string) ($c['body_mode'] ?? 'sanitized'),
        'excerpt' => (string) ($c['excerpt'] ?? ''),
        'featured_media_id' => (int) ($c['featured_media_id'] ?? 0),
        'featured_image_url' => (string) ($c['featured_image_url'] ?? ''),
        'featured_alt' => (string) ($c['featured_alt'] ?? ''),
        'author_user_id' => (int) ($c['author_user_id'] ?? 0),
        'author_name' => (string) ($c['author_name'] ?? ''),
        'template' => (string) ($c['template'] ?? ($type === 'article' ? 'article' : 'default')),
        'language' => (string) ($c['language'] ?? CmsSiteConfig::value('language', 'en-CA')),
        'publish_at' => app_datetime_local_value($c['publish_at'] ?? null),
        'expire_at' => app_datetime_local_value($c['expire_at'] ?? null),
        'internal_notes' => (string) ($c['internal_notes'] ?? ''),
        'faqs' => is_array($c['faqs'] ?? null) ? $c['faqs'] : [],
        'term_ids' => array_map(static fn ($t) => (int) $t['id'], array_filter($terms, static fn ($t) => $t['kind'] === 'category')),
        'tags' => implode(', ', array_map(static fn ($t) => $t['name'], array_filter($terms, static fn ($t) => $t['kind'] === 'tag'))),
        'seo_title' => (string) ($seo['seo_title'] ?? ''),
        'meta_description' => (string) ($seo['meta_description'] ?? ''),
        'robots_index' => (string) (($seo['robots_index'] ?? '') ?: 'inherit'),
        'robots_follow' => (string) (($seo['robots_follow'] ?? '') ?: 'inherit'),
        'max_snippet' => isset($seo['max_snippet']) && $seo['max_snippet'] !== null ? (string) $seo['max_snippet'] : '',
        'max_image_preview' => (string) ($seo['max_image_preview'] ?? ''),
        'max_video_preview' => isset($seo['max_video_preview']) && $seo['max_video_preview'] !== null ? (string) $seo['max_video_preview'] : '',
        'robots_extra' => CmsSeo::extraDirectives((string) ($seo['robots_extra'] ?? '')),
        'sitemap_include' => (int) ($seo['sitemap_include'] ?? 1),
        'canonical_mode' => (string) (($seo['canonical_mode'] ?? '') ?: 'auto'),
        'canonical_url' => (string) ($seo['canonical_url'] ?? ''),
        'og_title' => (string) ($seo['og_title'] ?? ''),
        'og_description' => (string) ($seo['og_description'] ?? ''),
        'og_image_id' => (int) ($seo['og_image_id'] ?? 0),
        'og_image_url' => (string) ($seo['og_image_url'] ?? ''),
        'og_image_alt' => (string) ($seo['og_image_alt'] ?? ''),
        'og_type' => (string) ($seo['og_type'] ?? ''),
        'twitter_card' => (string) ($seo['twitter_card'] ?? ''),
        'schema_disabled' => array_values((array) ($schema['disabled'] ?? [])),
        'schema_custom' => !empty($schema['custom']) ? (string) json_encode(count($schema['custom']) === 1 ? $schema['custom'][0] : $schema['custom'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
        'city_city' => (string) ($city['city'] ?? ''),
        'city_region' => (string) ($city['region'] ?? ''),
        'city_country' => (string) (($city['country'] ?? '') ?: 'Canada'),
        'city_primary_service' => (string) ($city['primary_service'] ?? ''),
        'city_related_services' => implode("\n", (array) ($city['related_services'] ?? [])),
        'city_phone' => (string) ($city['phone'] ?? ''),
        'city_email' => (string) ($city['email'] ?? ''),
        'city_address' => (string) ($city['address'] ?? ''),
        'city_hours' => $hours,
        'city_map_url' => (string) ($city['map_url'] ?? ''),
        'city_map_embed_url' => (string) ($city['map_embed_url'] ?? ''),
        'city_nearby' => implode("\n", array_map(static fn ($n) => trim(($n['name'] ?? '') . '|' . ($n['url'] ?? ''), '|'), (array) ($city['nearby'] ?? []))),
        'city_cta_label' => (string) ($cta['label'] ?? ''),
        'city_cta_url' => (string) ($cta['url'] ?? ''),
        'city_cta_text' => (string) ($cta['text'] ?? ''),
        'city_local_business' => (int) ($city['local_business'] ?? 0),
    ];
}

/**
 * Overlay submitted (or autosaved) input on stored values so the editor redisplays what the user typed.
 *
 * @return array<string, mixed>
 */
function cms_admin_form_overlay(array $values, array $input): array
{
    foreach ($input as $key => $value) {
        if ($key === 'csrf_token' || $key === 'workflow_action') {
            continue;
        }
        if (array_key_exists($key, $values) || str_starts_with((string) $key, 'city_') || str_starts_with((string) $key, 'faq_')) {
            $values[$key] = $value;
        }
    }
    if (isset($input['faq_q'])) {
        $values['faqs'] = [];
        foreach ((array) $input['faq_q'] as $i => $q) {
            $values['faqs'][] = ['question' => (string) $q, 'answer' => (string) ($input['faq_a'][$i] ?? '')];
        }
    }
    if (isset($input['city_hours_days'])) {
        $values['city_hours'] = [];
        foreach ((array) $input['city_hours_days'] as $i => $d) {
            $values['city_hours'][] = ['days' => (string) $d, 'opens' => (string) ($input['city_hours_opens'][$i] ?? ''), 'closes' => (string) ($input['city_hours_closes'][$i] ?? '')];
        }
    }
    foreach (['sitemap_include', 'city_local_business'] as $checkbox) {
        if (!isset($input[$checkbox]) && isset($input['title'])) {
            $values[$checkbox] = 0;
        }
    }
    if (!isset($input['schema_disabled']) && isset($input['title'])) {
        $values['schema_disabled'] = [];
    }
    if (!isset($input['robots_extra']) && isset($input['title'])) {
        $values['robots_extra'] = [];
    }
    $values['term_ids'] = array_map('intval', isset($input['title']) ? (array) ($input['term_ids'] ?? []) : (array) $values['term_ids']);
    return $values;
}

/** Folder <option>s, indented by depth. */
function cms_admin_folder_options(?int $selected, bool $includeNone = true, ?int $exclude = null): string
{
    $html = $includeNone ? '<option value="">— None (site root) —</option>' : '';
    $skipBelow = null;
    foreach ((new CmsFolderRepository())->flatTree() as $folder) {
        if ($skipBelow !== null && (int) $folder['depth'] > $skipBelow) {
            continue;
        }
        $skipBelow = null;
        if ($exclude !== null && (int) $folder['id'] === $exclude) {
            $skipBelow = (int) $folder['depth'];
            continue;
        }
        $label = str_repeat('— ', (int) $folder['depth']) . (!empty($folder['landing_content_id']) ? 'Page: ' : '') . $folder['name'] . ($folder['url_prefix'] !== '' ? '  (' . $folder['url_prefix'] . ')' : '');
        $html .= '<option value="' . (int) $folder['id'] . '" data-prefix="' . e((string) $folder['url_prefix']) . '"'
            . ((int) $folder['id'] === (int) $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}
