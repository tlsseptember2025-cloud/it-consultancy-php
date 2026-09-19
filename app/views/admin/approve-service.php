<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid service request.');
}

$isDemoAdmin = isset($_SESSION['demo_user']);

$approvePdo = $pdo;
$demoTenantId = null;

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvePdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}

/*
|--------------------------------------------------------------------------
| Approve Service
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Active',
            status = 'In Progress'
        WHERE id = ?
          AND customer_id IN (
              SELECT id
              FROM customers
              WHERE demo_tenant_id = ?
                AND is_demo_account = 1
          )
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    // Original Main Admin update preserved.
    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Active',
            status = 'In Progress'
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);
}

if ($stmt->rowCount() !== 1) {
    die('The service could not be approved.');
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;
