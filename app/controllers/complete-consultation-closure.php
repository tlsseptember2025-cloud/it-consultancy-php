<?php

$pageTitle = 'Complete Consultation Closure';

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();

    require_once CONFIG_PATH . '/demo-database.php';

    $closurePdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

    require_once CONFIG_PATH . '/database.php';

    $closurePdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}

if ($isDemoAdmin) {

    $stmt = $closurePdo->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $closurePdo->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE r.id = ?
        LIMIT 1
    ");

    $stmt->execute([$requestId]);
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($isDemoAdmin) {

    $stmt = $closurePdo->prepare("
        SELECT cca.*
        FROM consultation_closure_agreements cca
        INNER JOIN requests r
            ON r.id = cca.request_id
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE cca.request_id = ?
          AND cca.status = 'Approved'
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $closurePdo->prepare("
        SELECT *
        FROM consultation_closure_agreements
        WHERE request_id = ?
          AND status = 'Approved'
        LIMIT 1
    ");

    $stmt->execute([$requestId]);
}

$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

if (!$agreement) {
    die('Approved closure agreement not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['confirm_closure'])) {

        die('Please confirm the consultation closure.');

    }

    try {

    $closurePdo->beginTransaction();

    if ($isDemoAdmin) {

        $stmt = $closurePdo->prepare("
            UPDATE requests
            SET
                workflow_stage = ?,
                job_status = ?,
                completed_at = NOW()
            WHERE id = ?
              AND customer_id IN (
                  SELECT id
                  FROM customers
                  WHERE demo_tenant_id = ?
                    AND is_demo_account = 1
              )
              AND service_id IN (
                  SELECT id
                  FROM services
                  WHERE demo_tenant_id = ?
                    AND is_demo_account = 1
              )
        ");

        $stmt->execute([
            'Closed',
            'Completed',
            $requestId,
            $demoTenantId,
            $demoTenantId
        ]);

    } else {

        $stmt = $closurePdo->prepare("
            UPDATE requests
            SET
                workflow_stage = ?,
                job_status = ?,
                completed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            'Closed',
            'Completed',
            $requestId
        ]);
    }

    /*
|--------------------------------------------------------------------------
| Record Consultation Closed Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $requestId,
    RequestEventHelper::EVENT_CONSULTATION_CLOSED,
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Closed',
    'The consultation request was permanently closed after the approved closure agreement was completed.',
    false
);

    $closurePdo->commit();

    header('Location: index.php?page=approved-closures&success=closure_completed');
    exit;

} catch (Exception $e) {

    if ($closurePdo->inTransaction()) {
        $closurePdo->rollBack();
    }

    die($e->getMessage());

}

    exit;

}

require VIEW_PATH . '/admin/complete-consultation-closure.php';