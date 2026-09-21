<?php

$pageTitle = 'Approved Closures';

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
| Load Approved Closures
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Admin
    |--------------------------------------------------------------------------
    |
    | Demo Admin can only see requests belonging to their own Demo tenant.
    |
    */

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }

    $stmt = $approvedClosuresPdo->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE
            r.workflow_stage = ?
            AND c.demo_tenant_id = ?
            AND c.is_demo_account = 1
        ORDER BY r.id DESC
    ");

    $stmt->execute([
        'Closure Approved',
        $demoTenantId
    ]);

} else {

    /*
    |--------------------------------------------------------------------------
    | Main Admin / Demo Super Admin
    |--------------------------------------------------------------------------
    |
    | Main Admin sees Main database records.
    |
    | Demo Super Admin sees Demo database records.
    |
    */

    $stmt = $approvedClosuresPdo->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE r.workflow_stage = ?
        ORDER BY r.id DESC
    ");

    $stmt->execute([
        'Closure Approved'
    ]);
}


$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/admin/approved-closures.php';