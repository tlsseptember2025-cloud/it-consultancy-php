<?php

$pageTitle = 'Approved Closures';

require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$offset = getPageOffset($page, $limit);

/*
|--------------------------------------------------------------------------
| Determine Database Context
|--------------------------------------------------------------------------
|
| Main Admin:
|   Uses the Main database ($pdo).
|
| Demo Admin / Demo Super Admin:
|   Uses the Demo database ($demoPdo).
|
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

$approvedClosuresPdo = $pdo;


/*
|--------------------------------------------------------------------------
| Demo Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvedClosuresPdo = $demoPdo;
}


/*
|--------------------------------------------------------------------------
| Determine Database Context
|--------------------------------------------------------------------------
*/

require_once APP_PATH . '/helpers/auth.php';

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

if (!$isDemoAdmin && !isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

requireAdminLogin();

require_once CONFIG_PATH . '/database.php';

$approvedClosuresPdo = $pdo;
$demoTenantId = 0;

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvedClosuresPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $approvedClosuresPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Load Approved Closures
|--------------------------------------------------------------------------
*/

$where = "WHERE r.workflow_stage = ?";
$params = ['Closure Approved'];

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
            r.id LIKE ?
            OR r.description LIKE ?
            OR c.name LIKE ?
            OR s.title LIKE ?
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
        r.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    {$where}
    ORDER BY r.id DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $approvedClosuresPdo->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countWhere = "WHERE r.workflow_stage = ?";
$countParams = ['Closure Approved'];

if ($isDemoAdmin) {
    $countWhere .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?
    ";

    $countParams[] = $demoTenantId;
    $countParams[] = $demoTenantId;
}

if ($search !== '') {
    $countWhere .= "
        AND (
            r.id LIKE ?
            OR r.description LIKE ?
            OR c.name LIKE ?
            OR s.title LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
}

$countSql = "
    SELECT COUNT(*)
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    {$countWhere}
";

$countStmt = $approvedClosuresPdo->prepare($countSql);
$countStmt->execute($countParams);

$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = getTotalPages($totalRecords, $limit);

/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/admin/approved-closures.php';