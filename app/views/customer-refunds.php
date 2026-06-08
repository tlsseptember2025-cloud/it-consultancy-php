<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

require __DIR__ . '/layouts/header.php';

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        rf.*,
        s.title AS service_title
    FROM refunds rf
    JOIN requests r
        ON rf.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    WHERE r.customer_id = ?
    ORDER BY rf.id DESC
");

$stmt->execute([$customerId]);

$refunds = $stmt->fetchAll();

?>

<h1 class="mb-4">

    My Refunds

</h1>

<div class="card shadow-sm">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>

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
                                <?= htmlspecialchars($refund['service_title']) ?>
                            </td>

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

                        <td colspan="4" class="text-center">

                            No refunds found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>