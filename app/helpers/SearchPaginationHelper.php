<?php

/**
 * Reusable Search + Pagination Helper
 *
 * Handles common request parameters and pagination calculations.
 * Individual pages remain responsible for their own SQL/business rules.
 */

function getSearchTerm(): string
{
    return trim($_GET['search'] ?? '');
}


function getPageNumber(): int
{
    $page = (int) ($_GET['p'] ?? 1);

    return max(1, $page);
}


function getPageLimit(int $default = 10): int
{
    return max(1, $default);
}


function getPageOffset(int $page, int $limit): int
{
    return ($page - 1) * $limit;
}


function getTotalPages(int $totalRecords, int $limit): int
{
    if ($totalRecords <= 0) {
        return 1;
    }

    return (int) ceil($totalRecords / $limit);
}


/**
 * Build a SQL search condition for multiple columns.
 *
 * Example:
 *
 * $searchCondition = buildSearchCondition(
 *     ['name', 'email', 'phone', 'company'],
 *     $search,
 *     $params
 * );
 *
 * The function adds the required LIKE parameters to $params.
 */
function buildSearchCondition(
    array $columns,
    string $search,
    array &$params
): string {
    if ($search === '' || empty($columns)) {
        return '';
    }

    $conditions = [];
    $searchValue = '%' . $search . '%';

    foreach ($columns as $column) {
        $conditions[] = $column . ' LIKE ?';
        $params[] = $searchValue;
    }

    return ' AND (' . implode(' OR ', $conditions) . ')';
}


/**
 * Build a pagination URL while preserving selected GET parameters.
 */
function buildPaginationUrl(
    string $pageName,
    int $page,
    array $params = []
): string {
    $params['page'] = $pageName;
    $params['p'] = $page;

    return '?' . http_build_query($params);
}