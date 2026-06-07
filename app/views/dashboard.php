<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalCustomers = $pdo->query("
    SELECT COUNT(*)
    FROM customers
")->fetchColumn();

$totalRequests = $pdo->query("
    SELECT COUNT(*)
    FROM requests
")->fetchColumn();

$totalServices = $pdo->query("
    SELECT COUNT(*)
    FROM services
")->fetchColumn();

$unreadMessages = $pdo->query("
    SELECT COUNT(*)
    FROM messages
    WHERE status = 'unread'
")->fetchColumn();

$totalPayments = $pdo->query("
    SELECT COUNT(*)
    FROM payments
")->fetchColumn();

$totalRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
")->fetchColumn();

$totalQuoted = $pdo->query("
    SELECT COALESCE(SUM(quoted_price),0)
    FROM requests
")->fetchColumn();

$outstandingBalance = $totalQuoted - $totalRevenue;

/*
|--------------------------------------------------------------------------
| Recent Activity
|--------------------------------------------------------------------------
*/

$latestRequest = $pdo->query("
    SELECT
        customers.name,
        services.title,
        requests.status
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY requests.id DESC
    LIMIT 1
")->fetch();

$latestPayment = $pdo->query("
    SELECT
        customers.name,
        payments.amount,
        payments.status
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    ORDER BY payments.id DESC
    LIMIT 1
")->fetch();

$latestMessage = $pdo->query("
    SELECT *
    FROM messages
    ORDER BY created_at DESC
    LIMIT 1
")->fetch();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row mb-4">

    <div class="col-md-3">
        <div class="card border-primary text-center">
            <div class="card-body">
                <h6>Total Customers</h6>
                <h2 class="text-primary"><?= $totalCustomers ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-success text-center">
            <div class="card-body">
                <h6>Total Requests</h6>
                <h2 class="text-success"><?= $totalRequests ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-warning text-center">
            <div class="card-body">
                <h6>Total Services</h6>
                <h2 class="text-warning"><?= $totalServices ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-danger text-center">
            <div class="card-body">
                <h6>Unread Messages</h6>
                <h2 class="text-danger"><?= $unreadMessages ?></h2>
            </div>
        </div>
    </div>

</div>

<div class="row g-4">

    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <h4>Total Revenue</h4>
                <h1>$<?= number_format($totalRevenue, 2) ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body">
                <h4>Outstanding Balance</h4>
                <h1>$<?= number_format($outstandingBalance, 2) ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <h4>Total Payments</h4>
                <h1><?= $totalPayments ?></h1>
            </div>
        </div>
    </div>

</div>

<div class="card shadow-sm mt-5">

    <div class="card-body">

        <h3 class="mb-4">Recent Activity</h3>

        <div class="row">

            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <h5 class="text-primary">Latest Request</h5>

                    <?php if ($latestRequest): ?>

                        <strong><?= htmlspecialchars($latestRequest['name']) ?></strong><br>

                        <?= htmlspecialchars($latestRequest['title']) ?><br>

                        <span class="badge bg-primary">
                            <?= htmlspecialchars($latestRequest['status']) ?>
                        </span>

                    <?php else: ?>

                        No requests found.

                    <?php endif; ?>

                </div>

            </div>

            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <h5 class="text-success">Latest Payment</h5>

                    <?php if ($latestPayment): ?>

                        <strong><?= htmlspecialchars($latestPayment['name']) ?></strong><br>

                        $<?= number_format($latestPayment['amount'], 2) ?><br>

                        <span class="badge bg-success">
                            <?= htmlspecialchars($latestPayment['status']) ?>
                        </span>

                    <?php else: ?>

                        No payments found.

                    <?php endif; ?>

                </div>

            </div>

            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <h5 class="text-danger">Latest Message</h5>

                    <?php if ($latestMessage): ?>

                        <strong><?= htmlspecialchars($latestMessage['name']) ?></strong><br>

                        <?= htmlspecialchars($latestMessage['service']) ?><br>

                        <?= htmlspecialchars(substr($latestMessage['message'], 0, 80)) ?>

                    <?php else: ?>

                        No messages found.

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
