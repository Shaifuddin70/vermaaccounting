<?php

declare(strict_types=1);

/**
 * Body HTML sanitising (HTMLPurifier) and analysis helpers (links, headings, images, words).
 */
final class CmsHtml
{
    private static ?HTMLPurifier $purifier = null;

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        // Placeholder links such as href="{{company_phone_link}}" are not valid URLs, so park them on a path the purifier keeps.
        $html = (string) preg_replace_callback(
            '#\bhref=(["\'])(?:https?://[^"\'/\s]+)?/?(\{\{?)([a-z_]{1,40})\}?\}\1#i',
            static fn (array $m): string => 'href=' . $m[1] . '/__cms-ph__/' . (strlen($m[2]) === 2 ? 'd' : 's') . '/' . strtolower($m[3]) . $m[1],
            $html
        );
        $clean = trim(self::purifier()->purify($html));
        return (string) preg_replace_callback(
            '#/__cms-ph__/([ds])/([a-z_]{1,40})#',
            static fn (array $m): string => $m[1] === 'd' ? '{{' . $m[2] . '}}' : '{' . $m[2] . '}',
            $clean
        );
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }
        if (!class_exists('HTMLPurifier', false)) {
            require_once PROJECT_ROOT . '/lib/vendor/htmlpurifier/library/HTMLPurifier.auto.php';
        }
        $cacheDir = DATA_DIR . '/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', is_writable($cacheDir) ? $cacheDir : null);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('Attr.EnableID', true);
        $config->set('Attr.IDBlacklistRegexp', '/^(admin|cms-)/');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedRel', ['nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed|maps\.google\.com/maps\?)%');
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('Output.Newline', "\n");
        $config->set('HTML.DefinitionID', 'verma-cms');
        $config->set('HTML.DefinitionRev', 3);

        if ($def = $config->maybeGetRawHTMLDefinition()) {
            foreach (['section', 'article', 'aside', 'header', 'footer', 'nav', 'main'] as $block) {
                $def->addElement($block, 'Block', 'Flow', 'Common');
            }
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Block', 'Flow', 'Common');
            $def->addElement('details', 'Block', 'Flow', 'Common', ['open' => 'Bool#open']);
            $def->addElement('summary', 'Block', 'Flow', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addElement('time', 'Inline', 'Inline', 'Common', ['datetime' => 'Text']);
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('img', 'srcset', 'Text');
            $def->addAttribute('img', 'sizes', 'Text');
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $def->addAttribute('iframe', 'loading', 'Enum#lazy,eager');
            $def->addAttribute('a', 'target', 'Enum#_blank');
        }
        self::$purifier = new HTMLPurifier($config);
        return self::$purifier;
    }

    private static function dom(string $html): DOMDocument
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    public static function wordCount(string $html): int
    {
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/u', $text) ?: []);
    }

    public static function plainText(string $html, int $max = 0): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($max > 0 && mb_strlen($text) > $max) {
            $cut = mb_substr($text, 0, $max - 1);
            $space = mb_strrpos($cut, ' ');
            $text = rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
        }
        return $text;
    }

    /** @return list<array{href: string, text: string, rel: string}> */
    public static function links(string $html): array
    {
        if (stripos($html, '<a') === false) {
            return [];
        }
        $out = [];
        foreach (self::dom($html)->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }
            $out[] = ['href' => $href, 'text' => trim($a->textContent), 'rel' => $a->getAttribute('rel')];
        }
        return $out;
    }

    /** @return list<array{src: string, alt: ?string, width: string, height: string, loading: string}> */
    public static function images(string $html): array
    {
        if (stripos($html, '<img') === false) {
            return [];
        }
        $out = [];
        foreach (self::dom($html)->getElementsByTagName('img') as $img) {
            $out[] = [
                'src' => $img->getAttribute('src'),
                'alt' => $img->hasAttribute('alt') ? $img->getAttribute('alt') : null,
                'width' => $img->getAttribute('width'),
                'height' => $img->getAttribute('height'),
                'loading' => $img->getAttribute('loading'),
            ];
        }
        return $out;
    }

    /** @return list<array{level: int, text: string}> */
    public static function headings(string $html): array
    {
        if (!preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        return array_map(static fn ($h) => ['level' => (int) $h[1], 'text' => trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))], $m);
    }

    /**
     * Add lazy loading, intrinsic sizes and srcset to body images from the media library.
     * The first image keeps eager loading because it may be the largest contentful paint.
     */
    public static function enhanceImages(string $html, ?CmsMediaRepository $media = null): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }
        $index = 0;
        return preg_replace_callback('/<img\b[^>]*>/i', static function (array $m) use (&$index, $media): string {
            $tag = $m[0];
            $index++;
            if ($index > 1 && !preg_match('/\sloading=/i', $tag)) {
                $tag = preg_replace('/^<img\b/i', '<img loading="lazy"', $tag) ?? $tag;
            }
            if (!preg_match('/\sdecoding=/i', $tag)) {
                $tag = preg_replace('/^<img\b/i', '<img decoding="async"', $tag) ?? $tag;
            }
            if ($media !== null && preg_match('/\ssrc="(\/media\/[^"]+)"/i', $tag, $src) && !preg_match('/\ssrcset=/i', $tag)) {
                $item = CmsRenderer::mediaByUrl($src[1]);
                if ($item !== null) {
                    if ($item['srcset'] !== '') {
                        $tag = preg_replace('/^<img\b/i', '<img srcset="' . htmlspecialchars($item['srcset'], ENT_QUOTES) . '" sizes="(max-width: 960px) 100vw, 960px"', $tag) ?? $tag;
                    }
                    if (!preg_match('/\swidth=/i', $tag) && $item['width'] > 0) {
                        $tag = preg_replace('/^<img\b/i', '<img width="' . $item['width'] . '" height="' . $item['height'] . '"', $tag) ?? $tag;
                    }
                }
            }
            return $tag;
        }, $html) ?? $html;
    }

    /**
     * Similarity of two texts as a 0–100 score using word 3-gram Jaccard overlap.
     * City names are stripped first so pages that only swap the city still match.
     */
    public static function similarity(string $a, string $b, array $strip = []): int
    {
        $norm = static function (string $t) use ($strip): array {
            $t = mb_strtolower(self::plainText($t));
            foreach ($strip as $s) {
                if ($s !== '') {
                    $t = str_replace(mb_strtolower($s), ' ', $t);
                }
            }
            $words = preg_split('/[^\p{L}\p{N}]+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $grams = [];
            for ($i = 0, $n = count($words) - 2; $i < $n; $i++) {
                $grams[$words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2]] = true;
            }
            return $grams;
        };
        $ga = $norm($a);
        $gb = $norm($b);
        if ($ga === [] || $gb === []) {
            return 0;
        }
        $inter = count(array_intersect_key($ga, $gb));
        $union = count($ga + $gb);
        return $union === 0 ? 0 : (int) round($inter / $union * 100);
    }
}
