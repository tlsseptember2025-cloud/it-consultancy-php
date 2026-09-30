<?php

if (
    !isset($_SESSION['customer']) &&
    !isset($_SESSION['demo_customer'])
) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Select Customer Context / Database
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    $customerId = (int) $_SESSION['demo_customer']['id'];

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

} else {

    $customerId = (int) $_SESSION['customer']['id'];

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;
}

/*
|--------------------------------------------------------------------------
| Verify Demo Customer
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id IS NOT NULL
        LIMIT 1
    ");

    $stmt->execute([
        $customerId
    ]);

    if (!$stmt->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Customer Requests
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.*,
            s.title
        FROM requests r
        JOIN services s
            ON s.id = r.service_id
        JOIN customers c
            ON c.id = r.customer_id
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id IS NOT NULL
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
        ORDER BY r.id DESC
    ");

} else {

    $stmt = $db->prepare("
        SELECT
            r.*,
            s.title
        FROM requests r
        JOIN services s
            ON s.id = r.service_id
        WHERE r.customer_id = ?
        ORDER BY r.id DESC
    ");
}

$stmt->execute([
    $customerId
]);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Customer Payments
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            p.*
        FROM payments p
        JOIN requests r
            ON r.id = p.request_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id IS NOT NULL
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
        ORDER BY p.id DESC
    ");

} else {

    $stmt = $db->prepare("
        SELECT
            p.*
        FROM payments p
        JOIN requests r
            ON r.id = p.request_id
        WHERE r.customer_id = ?
        ORDER BY p.id DESC
    ");
}

$stmt->execute([
    $customerId
]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Customer Refunds
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            rr.*
        FROM refund_requests rr
        JOIN requests r
            ON r.id = rr.request_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id IS NOT NULL
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
        ORDER BY rr.id DESC
    ");

} else {

    $stmt = $db->prepare("
        SELECT
            rr.*
        FROM refund_requests rr
        JOIN requests r
            ON r.id = rr.request_id
        WHERE r.customer_id = ?
        ORDER BY rr.id DESC
    ");
}

$stmt->execute([
    $customerId
]);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>

<div class="container mt-4">

    <h1>

        Welcome,
        <?= htmlspecialchars(
            $isDemoCustomer
                ? $_SESSION['demo_customer']['username']
                : $_SESSION['customer']['name']
        ) ?>

    </h1>

    <p class="text-muted">

        Customer Dashboard

    </p>

    <div class="row">

        <div class="col-md-4 mb-3">

            <div class="card border-primary">

                <div class="card-body text-center">

                    <h5>My Requests</h5>

                    <h2><?= count($requests) ?></h2>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card border-success">

                <div class="card-body text-center">

                    <h5>My Payments</h5>

                    <h2><?= count($payments) ?></h2>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card border-danger">

                <div class="card-body text-center">

                    <h5>My Refunds</h5>

                    <h2><?= count($refunds) ?></h2>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>