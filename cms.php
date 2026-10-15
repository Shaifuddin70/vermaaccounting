<?php

declare(strict_types=1);

/**
 * Front controller for every public URL that is not a real file or hand-written page:
 * CMS pages, articles, city pages, redirects, previews and the 404 page.
 */

require_once __DIR__ . '/lib/bootstrap.php';

try {
    $response = CmsRouter::resolve(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        $_GET,
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    );
} catch (Throwable $e) {
    error_log('CMS render failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 60');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>Temporarily unavailable</title></head><body><h1>Sorry, something went wrong.</h1><p>Please try again in a minute.</p></body></html>';
    exit;
}

CmsRouter::emit($response);
