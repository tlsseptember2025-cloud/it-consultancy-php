<?php

$pageTitle = 'Review Closed Request';


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
|--------------------------------------------------------------------------
*/


require_once APP_PATH . '/helpers/auth.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| This page is part of the normal Admin / Demo Admin workflow.
| Demo Super Admin uses its own dashboard/workflow.
|
*/

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

$reviewPdo = $pdo;

$demoTenantId = null;


/*
|--------------------------------------------------------------------------
| Demo Admin Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $reviewPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );


    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $reviewPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (
              expires_at IS NULL
              OR expires_at > NOW()
          )
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Admin Belongs To This Tenant
    |--------------------------------------------------------------------------
    */

    $adminStmt = $reviewPdo->prepare("
        SELECT id
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

    if (!$adminStmt->fetch(PDO::FETCH_ASSOC)) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id']
    ?? 0
);


if ($requestId <= 0) {

    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Closed Request
|--------------------------------------------------------------------------
*/

$requestSql = "
    SELECT

        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name,

        a.name AS agent_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    WHERE r.id = ?
";


$requestParams = [
    $requestId
];


/*
|--------------------------------------------------------------------------
| Demo Tenant Restriction
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $requestSql .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?
    ";

    $requestParams[] = $demoTenantId;
    $requestParams[] = $demoTenantId;
}


$requestSql .= "
    LIMIT 1
";


$stmt = $reviewPdo->prepare($requestSql);

$stmt->execute($requestParams);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Request Not Found
|--------------------------------------------------------------------------
*/

if (!$request) {

    die('Request not found.');
}


/*
|--------------------------------------------------------------------------
| Load Closure Agreement
|--------------------------------------------------------------------------
*/

$stmt = $reviewPdo->prepare("
    SELECT *
    FROM consultation_closure_agreements
    WHERE request_id = ?
    LIMIT 1
");


$stmt->execute([
    $requestId
]);


$agreement = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/admin/review-closed-request.php';