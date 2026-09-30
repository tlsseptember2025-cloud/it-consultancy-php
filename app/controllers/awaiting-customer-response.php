<?php

require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once APP_PATH . '/helpers/auth.php';


/*
|--------------------------------------------------------------------------
| Admin Context
|--------------------------------------------------------------------------
|
| Normal Admin:
|   $_SESSION['user']
|   Main database ($pdo)
|
| Demo Admin:
|   $_SESSION['demo_user']
|   Demo database ($demoPdo)
|   Restricted to its own demo_tenant_id
|
| Demo Super Admin:
|   Separate workflow and is not handled here.
|
|--------------------------------------------------------------------------
*/


$isDemoAdmin   = isset($_SESSION['demo_user']);
$isNormalAdmin = isset($_SESSION['user']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isNormalAdmin && !$isDemoAdmin) {

    if (isset($_SESSION['demo_super_admin'])) {

        header('Location: ?page=dashboard');

    } elseif (!empty($_SESSION['demo_logged_out'])) {

        header('Location: ?page=demo-login');

    } else {

        header('Location: ?page=login');
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

$requestsPdo = $pdo;

$demoTenantId = 0;


/*
|--------------------------------------------------------------------------
| Demo Admin
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $requestsPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Demo Tenant
    |--------------------------------------------------------------------------
    */

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Demo Tenant
    |--------------------------------------------------------------------------
    */

    $tenantStmt = $requestsPdo->prepare("
        SELECT
            id,
            status,
            expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");

    $tenantStmt->execute([
        $demoTenantId
    ]);

    $demoTenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);


    if (
        !$demoTenant
        || $demoTenant['status'] !== 'Active'
        || (
            $demoTenant['expires_at'] !== null
            && strtotime($demoTenant['expires_at']) <= time()
        )
    ) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Demo Admin Belongs To Tenant
    |--------------------------------------------------------------------------
    */

    $adminStmt = $requestsPdo->prepare("
        SELECT
            id
        FROM users
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
        LIMIT 1
    ");

    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    $demoAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);


    if (!$demoAdmin) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Search / Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();

$page = getPageNumber();

$limit = 10;

$params = [];


/*
|--------------------------------------------------------------------------
| Workflow Stages
|--------------------------------------------------------------------------
*/

$where = "
    WHERE r.workflow_stage IN (
        'Waiting Customer Response',
        'Closure Agreement Sent'
    )
";


/*
|--------------------------------------------------------------------------
| Demo Tenant Restriction
|--------------------------------------------------------------------------
|
| A Demo Admin may only see requests belonging to
| customers inside its own Demo tenant.
|
*/

if ($isDemoAdmin) {

    $where .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?
        AND s.is_demo_account = 1
    ";

    $params[] = $demoTenantId;
    $params[] = $demoTenantId;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

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
| Count Matching Requests
|--------------------------------------------------------------------------
*/

$countStmt = $requestsPdo->prepare("
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


/*
|--------------------------------------------------------------------------
| Prevent Invalid Page
|--------------------------------------------------------------------------
*/

$page = min(
    $page,
    $totalPages
);


/*
|--------------------------------------------------------------------------
| Pagination Offset
|--------------------------------------------------------------------------
*/

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load Paginated Requests
|--------------------------------------------------------------------------
*/

$stmt = $requestsPdo->prepare("
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


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/admin/awaiting-customer-response.php';