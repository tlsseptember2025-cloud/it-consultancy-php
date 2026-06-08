<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

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
        refunds.*
    FROM refunds
    JOIN requests
        ON requests.id = refunds.request_id
    WHERE requests.customer_id = ?
    ORDER BY refunds.id DESC
");

$refunds->execute([$customerId]);

$refunds = $refunds->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="container mt-4">

    <h1>

        Welcome,
        <?= htmlspecialchars($_SESSION['customer']['name']) ?>

    </h1>

    <p class="text-muted">

        Customer Dashboard

    </p>

    <div class="card shadow-sm mt-4">

    <div class="card-body">

        <h3 class="mb-4">

            My Requests

        </h3>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>

                    <th>Quoted Price</th>

                    <th>Status</th>

                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($requests as $request): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($request['title']) ?>
                        </td>

                        <td>
                            $<?= number_format($request['quoted_price'], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['status']) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($request['created_at'])) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <div class="card shadow-sm mt-4">

        <div class="card-body">

        <h3 class="mb-4">

            My Payments

        </h3>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Amount</th>

                    <th>Status</th>

                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($payments): ?>

                    <?php foreach ($payments as $payment): ?>

                        <tr>

                            <td>
                                $<?= number_format($payment['amount'], 2) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($payment['status']) ?>
                            </td>

                            <td>
                                <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="3" class="text-center">

                            No payments found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="card shadow-sm mt-4">

    <div class="card-body">

        <h3 class="mb-4">

            My Refunds

        </h3>

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Amount</th>

                    <th>Date</th>

                    <th>Reason</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($refunds): ?>

                    <?php foreach ($refunds as $refund): ?>

                        <tr>

                            <td>
                                $<?= number_format($refund['amount'], 2) ?>
                            </td>

                            <td>
                                <?= date('M d, Y', strtotime($refund['refund_date'])) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($refund['reason']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="3" class="text-center">

                            No refunds found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>

</div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>