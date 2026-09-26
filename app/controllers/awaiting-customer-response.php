<?php


if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}


require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$params = [];

$where = "
    WHERE r.workflow_stage IN (
        'Waiting Customer Response',
        'Closure Agreement Sent'
    )
";

$where .= buildSearchCondition(
    [
        'r.id',
        'c.name',
        's.title',
        'r.job_status',
        'r.description',
        'r.workflow_stage'
    ],
    $search,
    $params
);

/*
|--------------------------------------------------------------------------
| Count matching requests
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    $where
");

$countStmt->execute($params);

$totalRequests = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalRequests,
    $limit
);

$page = min(
    $page,
    $totalPages
);

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load paginated requests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.verification_email_count,
        r.customer_response_deadline,
        r.job_status,
        r.workflow_stage,
        c.name AS customer_name,
        s.title AS service_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    $where

    ORDER BY
        r.customer_response_deadline ASC

    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


require VIEW_PATH . '/admin/awaiting-customer-response.php';