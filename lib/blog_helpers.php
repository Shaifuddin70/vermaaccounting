<?php

declare(strict_types=1);

function blog_normalize_slug(string $slug, string $fallbackTitle = 'post'): string
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        $slug = strtolower(trim($fallbackTitle));
    }
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'post';
}

function blog_normalize_date(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m)) {
        return $m[0];
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

function blog_normalize_time(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
        $parts = explode(':', $raw);
        $h = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT);
        $m = str_pad((string) ((int) ($parts[1] ?? 0)), 2, '0', STR_PAD_LEFT);
        $s = str_pad((string) ((int) ($parts[2] ?? 0)), 2, '0', STR_PAD_LEFT);
        return $h . ':' . $m . ':' . $s;
    }
    return null;
}

/** @return list<string> */
function blog_decode_json_list(mixed $json): array
{
    if (is_array($json)) {
        $decoded = $json;
    } else {
        $decoded = json_decode((string) $json, true);
    }
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        if (is_string($item) && trim($item) !== '') {
            $out[] = trim($item);
        } elseif (is_array($item) && isset($item['name'])) {
            $out[] = trim((string) $item['name']);
        }
    }
    return $out;
}

/** @return array<string, mixed> */
function blog_decode_json_object(mixed $json): array
{
    if (is_array($json)) {
        return $json;
    }
    $decoded = json_decode((string) $json, true);
    return is_array($decoded) ? $decoded : [];
}

function blog_public_url(array $blog): string
{
    $slug = (string) ($blog['slug'] ?? '');
    return '/blog/' . rawurlencode($slug);
}

function blog_format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('F j, Y', $ts) : $date;
}

function blog_reading_time(array $blog): string
{
    $custom = $blog['custom_fields'] ?? [];
    if (is_array($custom) && !empty($custom['readingTime'])) {
        return (string) $custom['readingTime'];
    }
    $text = trim(strip_tags((string) ($blog['content_html'] ?? '')));
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $minutes = max(1, (int) ceil(count($words) / 200));
    return $minutes . ' min read';
}

/**
 * Drop leading hero image + duplicate H1 from Uplift HTML when the page already renders them.
 */
function blog_prepare_content_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // Remove leading <figure class="blog-hero">...</figure>
    $html = preg_replace(
        '/^\s*<figure\b[^>]*class=(["\'])[^"\']*\bblog-hero\b[^"\']*\1[^>]*>.*?<\/figure>\s*/is',
        '',
        $html,
        1
    ) ?? $html;

    // Unwrap a single outer <article> so body styles apply cleanly
    if (preg_match('/^\s*<article\b[^>]*>(.*)<\/article>\s*$/is', $html, $m)) {
        $html = trim($m[1]);
    }

    // Remove the first H1 (title is shown in the page hero)
    $html = preg_replace('/^\s*<h1\b[^>]*>.*?<\/h1>\s*/is', '', $html, 1) ?? $html;

    return blog_transform_faqs_html(trim($html));
}

/**
 * Convert Uplift FAQ blocks (H2 + H3/P pairs) into the site accordion FAQ markup.
 */
function blog_transform_faqs_html(string $html): string
{
    if ($html === '' || !preg_match('/<h2\b[^>]*>\s*(FAQs?|Frequently Asked Questions)\s*<\/h2>/i', $html)) {
        return $html;
    }

    $transformed = preg_replace_callback(
        '/(<h2\b[^>]*>\s*(?:FAQs?|Frequently Asked Questions)\s*<\/h2>)\s*(.*?)(?=<h2\b|<section\b|$)/is',
        static function (array $match): string {
            $heading = $match[1];
            $body = trim($match[2]);
            if ($body === '') {
                return $match[0];
            }

            if (!preg_match_all('/<h3\b[^>]*>(.*?)<\/h3>\s*(.*?)(?=<h3\b|$)/is', $body, $items, PREG_SET_ORDER)) {
                return $match[0];
            }

            $out = '<div class="blog-faq faq-container">' . $heading;
            foreach ($items as $item) {
                $question = trim(html_entity_decode(strip_tags($item[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $answer = trim($item[2]);
                if ($question === '' || $answer === '') {
                    continue;
                }
                if (!preg_match('/^\s*<(p|ul|ol|div)\b/i', $answer)) {
                    $answer = '<p>' . $answer . '</p>';
                }
                $out .= '<div class="faq-item">'
                    . '<button type="button" class="faq-question" onclick="toggleFAQ(this)">'
                    . '<span>' . htmlspecialchars($question, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
                    . '<i class="fas fa-chevron-down" aria-hidden="true"></i>'
                    . '</button>'
                    . '<div class="faq-answer">' . $answer . '</div>'
                    . '</div>';
            }
            $out .= '</div>';

            return $out;
        },
        $html
    );

    return is_string($transformed) ? $transformed : $html;
}

/**
 * Sync all blogs from Uplift into the local database.
 *
 * @return array{fetched: int, created: int, updated: int}
 */
function blog_sync_from_uplift(?UpliftAiClient $client = null, ?BlogRepository $repo = null): array
{
    $client ??= new UpliftAiClient();
    $repo ??= new BlogRepository();

    if (!$client->isConfigured()) {
        throw new RuntimeException('Add your Uplift AI API token in config.local.php (uplift_ai.api_token).');
    }

    $remoteBlogs = $client->listAllBlogs('ALL');
    $created = 0;
    $updated = 0;

    foreach ($remoteBlogs as $remote) {
        if (!is_array($remote)) {
            continue;
        }
        $result = $repo->upsertFromUplift($remote);
        if ($result['created']) {
            $created++;
        } elseif ($result['updated']) {
            $updated++;
        }
    }

    return [
        'fetched' => count($remoteBlogs),
        'created' => $created,
        'updated' => $updated,
    ];
}
