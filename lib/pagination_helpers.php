<?php

declare(strict_types=1);

/** @return list<int> */
function admin_per_page_options(): array
{
    return [10, 20, 50, 100];
}

function pagination_default_per_page(): int
{
    return 20;
}

function pagination_per_page_from_request(): int
{
    $requested = (int) ($_GET['per_page'] ?? pagination_default_per_page());
    return in_array($requested, admin_per_page_options(), true)
        ? $requested
        : pagination_default_per_page();
}

function pagination_page_from_request(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

/** @return array{page: int, per_page: int, offset: int, total: int, total_pages: int} */
function pagination_meta(int $total, int $page, ?int $perPage = null): array
{
    $perPage = max(1, $perPage ?? pagination_per_page_from_request());
    $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $totalPages);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
    ];
}

function pagination_url(string $path, array $query, int $page, ?int $perPage = null): string
{
    if ($page > 1) {
        $query['page'] = $page;
    } else {
        unset($query['page']);
    }

    $perPage = $perPage ?? pagination_per_page_from_request();
    if ($perPage !== pagination_default_per_page()) {
        $query['per_page'] = $perPage;
    } else {
        unset($query['per_page']);
    }

    return $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

/**
 * Page numbers to render, with 'ellipsis' for gaps.
 *
 * @return list<int|'ellipsis'>
 */
function pagination_page_list(int $page, int $totalPages, int $siblings = 1): array
{
    if ($totalPages <= 1) {
        return [];
    }

    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $pages = [1];
    $start = max(2, $page - $siblings);
    $end = min($totalPages - 1, $page + $siblings);

    if ($start > 2) {
        $pages[] = 'ellipsis';
    }

    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }

    if ($end < $totalPages - 1) {
        $pages[] = 'ellipsis';
    }

    $pages[] = $totalPages;

    return $pages;
}

/** @deprecated Use pagination_per_page_from_request() */
function admin_per_page(): int
{
    return pagination_per_page_from_request();
}
