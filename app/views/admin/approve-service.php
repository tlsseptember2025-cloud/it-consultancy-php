<?php
// CSRF protection for this state-changing GET action.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

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
                )
          AND service_id IN (
              SELECT id
              FROM services
              WHERE demo_tenant_id = ?
                )
    ");

    $stmt->execute([
        $id,
        $demoTenantId,
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

if (!$stmt) {
    die('The service could not be approved.');
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;
