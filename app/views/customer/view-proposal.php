<?php

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Environment
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

    $db = $demoPdo;

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

    $customerCheckStmt = $db->prepare("
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

    $db = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id']
    ?? $_GET['id']
    ?? 0
);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Customer Proposal
|--------------------------------------------------------------------------
|
| Demo:
| - authenticated Demo customer
| - same Demo tenant
| - Demo service
|
| Normal:
| - authenticated customer
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.proposal,
            r.quoted_price,
            r.workflow_stage
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          
          AND s.demo_tenant_id = c.demo_tenant_id
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            proposal,
            quoted_price,
            workflow_stage
        FROM requests
        WHERE id = ?
          AND customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}


$proposal = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$proposal) {
    die('Proposal not found.');
}


/*
|--------------------------------------------------------------------------
| Mark Proposal as Viewed
|--------------------------------------------------------------------------
*/

if ($proposal['workflow_stage'] === 'Proposal Sent') {

    if ($isDemoCustomer) {

        $update = $db->prepare("
            UPDATE requests r

            INNER JOIN customers c
                ON c.id = r.customer_id

            INNER JOIN services s
                ON s.id = r.service_id

            SET r.workflow_stage = 'Proposal Viewed'

            WHERE r.id = ?
              AND r.customer_id = ?
              AND c.is_demo_account = 1
              AND c.demo_tenant_id = ?
              
              AND s.demo_tenant_id = c.demo_tenant_id
        ");

        $update->execute([
            $requestId,
            $customerId,
            $demoTenantId
        ]);

    } else {

        $update = $db->prepare("
            UPDATE requests
            SET workflow_stage = 'Proposal Viewed'
            WHERE id = ?
              AND customer_id = ?
        ");

        $update->execute([
            $requestId,
            $customerId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Keep Page In Sync
    |--------------------------------------------------------------------------
    */

    $proposal['workflow_stage'] = 'Proposal Viewed';
}


require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<p class="mb-3">

    <strong>Status:</strong>

    <span class="badge bg-primary">

        <?= htmlspecialchars(
            $proposal['workflow_stage']
        ) ?>

    </span>

</p>


<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Service Proposal
        </h2>

        <p>

            <strong>Quoted Price:</strong>

            <?= number_format(
                (float) $proposal['quoted_price'],
                2
            ) ?>

        </p>

        <hr>

        <pre><?= nl2br(
            htmlspecialchars(
                $proposal['proposal']
            )
        ) ?></pre>


        <?php if (
            $proposal['workflow_stage'] === 'Proposal Viewed'
            || $proposal['workflow_stage'] === 'Proposal Sent'
        ): ?>

            <a
                href="?page=accept-proposal-confirm&request_id=<?= $requestId ?>"
                class="btn btn-success">

                Accept Proposal

            </a>

            <a
                href="?page=reject-proposal&request_id=<?= $requestId ?>"
                class="btn btn-danger">

                Reject Proposal

            </a>

        <?php endif; ?>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>