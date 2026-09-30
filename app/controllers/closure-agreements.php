<?php

$pageTitle = 'Closure Agreements';

require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once APP_PATH . '/helpers/auth.php';

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {
    requireDemoAdmin();
} elseif (isset($_SESSION['user'])) {
    requireAdminLogin();
} else {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$closurePdo = $pdo;
$demoTenantId = 0;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';

    $closurePdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$offset = getPageOffset($page, $limit);

$params = [];

$where = "WHERE cca.status = 'Pending'";

if ($isDemoAdmin) {
    $where .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?

    ";

    $params[] = $demoTenantId;
    $params[] = $demoTenantId;
}

if ($search !== '') {
    $where .= "
    AND (
        cca.request_id LIKE ?
        OR c.name LIKE ?
        OR s.title LIKE ?
        OR cca.typed_name LIKE ?
    )
";

$searchValue = '%' . $search . '%';

$params[] = $searchValue;
$params[] = $searchValue;
$params[] = $searchValue;
$params[] = $searchValue;
}

$sql = "
    SELECT
        cca.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM consultation_closure_agreements cca
    INNER JOIN requests r
        ON r.id = cca.request_id
    INNER JOIN customers c
        ON c.id = cca.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    {$where}
    ORDER BY cca.signed_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $closurePdo->prepare($sql);
$stmt->execute($params);

$agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "
    SELECT COUNT(*)
    FROM consultation_closure_agreements cca
    INNER JOIN requests r
        ON r.id = cca.request_id
    INNER JOIN customers c
        ON c.id = cca.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    {$where}
";

$countStmt = $closurePdo->prepare($countSql);
$countStmt->execute($params);

$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = getTotalPages($totalRecords, $limit);

require VIEW_PATH . '/admin/closure-agreements.php';