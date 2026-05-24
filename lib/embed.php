<?php

declare(strict_types=1);

/**
 * Render an iframe embed for a published form.
 */
function form_embed_html(string $slug, array $options = []): string
{
    $slug = slugify($slug);
    if ($slug === '') {
        return '';
    }

    $repo = new FormRepository();
    $form = $repo->findBySlug($slug, true);
    if (!$form) {
        return '<!-- Form not found or not published: ' . e($slug) . ' -->';
    }

    $height = max(400, (int) ($options['height'] ?? 720));
    $title = e($form['title']);
    $src = '/form/' . rawurlencode($slug) . '?embed=1';

    return '<div class="form-embed" data-form-slug="' . e($slug) . '">'
        . '<iframe src="' . e($src) . '" title="' . $title . '" '
        . 'width="100%" height="' . $height . '" frameborder="0" '
        . 'loading="lazy" class="form-embed-iframe"></iframe>'
        . '</div>';
}

/**
 * Replace [form slug="my-form"] or [form slug="my-form" height="600"] shortcodes.
 */
function process_form_shortcodes(string $content): string
{
    return (string) preg_replace_callback(
        '/\[form\s+slug=(["\']?)([a-zA-Z0-9_-]+)\1(?:\s+height=(["\']?)(\d+)\3)?\s*\]/i',
        static function (array $m): string {
            $opts = [];
            if (!empty($m[4])) {
                $opts['height'] = (int) $m[4];
            }
            return form_embed_html($m[2], $opts);
        },
        $content
    );
}

function form_shortcode_literal(string $slug, int $height = 720): string
{
    return '[form slug="' . slugify($slug) . '" height="' . $height . '"]';
}
