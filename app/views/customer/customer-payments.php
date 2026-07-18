<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$customerId = (int) $_SESSION['customer']['id'];

require dirname(__DIR__) . '/layouts/header-customer.php';

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        p.*,
        s.title AS service_title
    FROM payments p
    JOIN requests r
        ON p.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    WHERE r.customer_id = ?
    ORDER BY p.id DESC
");

$stmt->execute([$customerId]);

$payments = $stmt->fetchAll();

?>

<h1 class="mb-4">

    My Payments

</h1>

<div class="card shadow-sm">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>

                    <th>Amount</th>

                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($payments): ?>

                    <?php foreach ($payments as $payment): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($payment['service_title']) ?>
                            </td>

                            <td>
                                AED <?= number_format($payment['amount'], 2) ?>
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

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>