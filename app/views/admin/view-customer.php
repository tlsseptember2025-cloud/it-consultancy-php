<?php

require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Context
|--------------------------------------------------------------------------
*/

$isMainAdmin      = isset($_SESSION['user']);
$isDemoAdmin      = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if (!$isMainAdmin && !$isDemoAdmin && !$isDemoSuperAdmin) {

    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

} else {

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Customer ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {

    $_SESSION['error'] = 'Invalid customer ID.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Admin
    |--------------------------------------------------------------------------
    | Demo Admin may only view customers belonging to their own tenant.
    */

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        $_SESSION['error'] =
            'Demo tenant information is missing.';

        header('Location: ?page=customers');
        exit;
    }

    $stmt = $customerPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} elseif ($isDemoSuperAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Super Admin
    |--------------------------------------------------------------------------
    | Demo Super Admin may view Demo customers across all tenants.
    */

    $stmt = $customerPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

} else {

    /*
    |--------------------------------------------------------------------------
    | Main Admin
    |--------------------------------------------------------------------------
    */

    $stmt = $customerPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);
}

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Customer Not Found / Not Accessible
|--------------------------------------------------------------------------
*/

if (!$customer) {

    $_SESSION['error'] =
        'Customer not found or you do not have access to this customer.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Requests
|--------------------------------------------------------------------------
*/

$requestsStmt = $customerPdo->prepare("
    SELECT
        requests.*,
        services.title AS service_title
    FROM requests
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    ORDER BY requests.created_at DESC
");

$requestsStmt->execute([
    $id
]);

$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Customer Payments
|--------------------------------------------------------------------------
*/

$paymentsStmt = $customerPdo->prepare("
    SELECT
        payments.*,
        services.title AS service_title
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    ORDER BY payments.created_at DESC
");

$paymentsStmt->execute([
    $id
]);

$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Calculate Financial Summary
|--------------------------------------------------------------------------
*/

$totalRequestsValue = 0;
$totalPaid = 0;


foreach ($requests as $request) {

    $totalRequestsValue += (float) $request['quoted_price'];
}


foreach ($payments as $payment) {

    if (
        $payment['status'] === 'Paid' ||
        $payment['status'] === 'Partially Paid'
    ) {

        $totalPaid += (float) $payment['amount'];
    }
}


$outstandingBalance =
    $totalRequestsValue - $totalPaid;

?>


<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>


<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Customer Details
        </h2>


        <p>

            <strong>Name:</strong>

            <?= htmlspecialchars(
                $customer['name']
            ) ?>

        </p>


        <p>

            <strong>Email:</strong>

            <?= htmlspecialchars(
                $customer['email']
            ) ?>

        </p>


        <p>

            <strong>Phone:</strong>

            <?= htmlspecialchars(
                $customer['phone']
            ) ?>

        </p>


        <p>

            <strong>Company:</strong>

            <?= htmlspecialchars(
                $customer['company'] ?? ''
            ) ?>

        </p>


        <p>

            <strong>Notes:</strong><br>

            <?= nl2br(
                htmlspecialchars(
                    $customer['notes'] ?? ''
                )
            ) ?>

        </p>


        <hr>


        <div class="row mb-4">


            <div class="col-md-4">

                <div class="card text-center border-primary">

                    <div class="card-body">

                        <h6>
                            Total Requests Value
                        </h6>

                        <h4 class="text-primary">

                            $<?= number_format(
                                $totalRequestsValue,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card text-center border-success">

                    <div class="card-body">

                        <h6>
                            Total Paid
                        </h6>

                        <h4 class="text-success">

                            $<?= number_format(
                                $totalPaid,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card text-center border-danger">

                    <div class="card-body">

                        <h6>
                            Outstanding Balance
                        </h6>

                        <h4 class="text-danger">

                            $<?= number_format(
                                $outstandingBalance,
                                2
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>


        </div>


        <hr>


        <h3 class="mb-3">
            Requests
        </h3>


        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>
                    <th>Price</th>
                    <th>Status</th>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($requests)): ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No requests found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($requests as $request): ?>

                        <tr>

                            <td>

                                <?= htmlspecialchars(
                                    $request['service_title']
                                ) ?>

                            </td>


                            <td>

                                $<?= number_format(
                                    $request['quoted_price'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $request['status']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>


        <h3 class="mb-3 mt-5">
            Payments
        </h3>


        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>
                    <th>Amount</th>
                    <th>Status</th>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($payments)): ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted">

                            No payments found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($payments as $payment): ?>

                        <tr>

                            <td>

                                <?= htmlspecialchars(
                                    $payment['service_title']
                                ) ?>

                            </td>


                            <td>

                                $<?= number_format(
                                    $payment['amount'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $payment['status']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>


        <a
            href="?page=customers"
            class="btn btn-secondary">

            Back

        </a>


    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>