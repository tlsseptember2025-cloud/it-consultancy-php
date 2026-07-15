<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

$refunds = $pdo->query("
    SELECT
        refunds.*,
        customers.name,
        services.title
    FROM refunds
    JOIN requests
        ON requests.id = refunds.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY refunds.id DESC
")->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h1>
        Refund Management
    </h1>

</div>

<div class="card shadow-sm">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Customer</th>

                    <th>Service</th>

                    <th>Amount</th>

                    <th>Date</th>

                    <th>Reason</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($refunds as $refund): ?>

                    <tr>

                        <td>
                            <?= $refund['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($refund['name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($refund['title']) ?>
                        </td>

                        <td>
                            AED <?= number_format($refund['amount'], 2) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($refund['refund_date'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($refund['reason']) ?>
                        </td>

                        <td>

                           <?php
                            $status = trim((string)($refund['status'] ?? ''));

                            if ($status === 'Processing'):
                            ?>

                                <span class="badge bg-warning text-dark">
                                    Processing
                                </span>

                                <br>

                                <small class="text-muted">
                                    Awaiting completion by finance team.
                                </small>

                                <br><br>

                                <a
                                    href="?page=complete-refund&id=<?= $refund['id'] ?>"
                                    class="btn btn-success btn-sm">

                                    Complete Refund

                                </a>

                            <?php elseif ($status === 'Completed'): ?>

                                <span class="badge bg-success">
                                    Completed
                                </span>

                                <br>

                                <small class="text-muted">
                                    Refund successfully processed.
                                </small>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($status ?: 'Unknown') ?>
                                </span>

                            <?php endif; ?>
                           
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>