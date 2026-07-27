<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$customerId = (int) $_SESSION['customer']['id'];

require CONFIG_PATH . '/database.php';

$customerId = $_SESSION['customer']['id'];

$requests = $pdo->prepare("
    SELECT
        requests.*,
        services.title
    FROM requests
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    ORDER BY requests.id DESC
");

$requests->execute([$customerId]);
$requests = $requests->fetchAll();

$payments = $pdo->prepare("
    SELECT
        payments.*
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    WHERE requests.customer_id = ?
    ORDER BY payments.id DESC
");

$payments->execute([$customerId]);
$payments = $payments->fetchAll();

$refunds = $pdo->prepare("
    SELECT
        rr.*
    FROM refund_requests rr
    JOIN requests r
        ON r.id = rr.request_id
    WHERE r.customer_id = ?
    ORDER BY rr.id DESC
");

$refunds->execute([$customerId]);
$refunds = $refunds->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>

<div class="container mt-4">

    <h1>

        Welcome,
        <?= htmlspecialchars($_SESSION['customer']['name']) ?>

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

</div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>