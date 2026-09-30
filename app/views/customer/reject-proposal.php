<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Type
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheckStmt = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id = ?
        LIMIT 1
    ");

    $customerCheckStmt->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheckStmt->fetch(PDO::FETCH_ASSOC)) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Reject Proposal
|--------------------------------------------------------------------------
|
| The request must belong to the authenticated customer.
|
| Demo customers are additionally restricted to:
| - their Demo tenant
| - Demo customer
| - Demo service belonging to the same tenant
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        UPDATE requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        SET r.workflow_stage = 'Proposal Rejected'
        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerPdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Proposal Rejected'
        WHERE id = ?
          AND customer_id = ?
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}


/*
|--------------------------------------------------------------------------
| Verify Request Was Updated
|--------------------------------------------------------------------------
*/

if ($stmt->rowCount() !== 1) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Record Proposal Rejected Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $customerPdo,
    $requestId,
    RequestEventHelper::EVENT_PROPOSAL_REJECTED,
    RequestEventHelper::TYPE_PROPOSAL,
    'Proposal Rejected',
    'The customer rejected the proposal.',
    true
);


header('Location: ?page=customer-requests');
exit;