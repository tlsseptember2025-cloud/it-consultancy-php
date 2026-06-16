<?php

require_once __DIR__ . '/../helpers/auth.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("
    SELECT
        payments.*,
        customers.name AS customer_name,
        services.title AS service_title
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY payments.created_at DESC
");

$payments = $stmt->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>
        Payments
    </h2>

    <a
        href="?page=add-payment"
        class="btn btn-primary">

        Add Payment

    </a>

</div>

<table class="table table-bordered table-hover">

    <thead>

        <tr>

            <th>Customer</th>
            <th>Service</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>

        </tr>

    </thead>

    <tbody>

        <?php foreach ($payments as $payment): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($payment['customer_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($payment['service_title']) ?>
                </td>

                <td>
                    $<?= number_format($payment['amount'], 2) ?>
                </td>

                <td>

                    <?php if ($payment['status'] === 'Paid'): ?>

                        <span class="badge bg-success">
                            Paid
                        </span>

                    <?php elseif ($payment['status'] === 'Unpaid'): ?>

                        <span class="badge bg-danger">
                            Unpaid
                        </span>

                    <?php elseif ($payment['status'] === 'Partially Paid'): ?>

                        <span class="badge bg-warning">
                            Partial
                        </span>

                    <?php else: ?>

                        <span class="badge bg-secondary">
                            <?= htmlspecialchars($payment['status']) ?>
                        </span>

                    <?php endif; ?>

                </td>

                <td>
                    <?= $payment['payment_date'] ?>
                </td>

                <td>

                    <a
                        href="?page=view-payment&id=<?= $payment['id'] ?>"
                        class="btn btn-info btn-sm">

                        View

                    </a>

                    <a
                        href="?page=edit-payment&id=<?= $payment['id'] ?>"
                        class="btn btn-warning btn-sm">

                        Edit

                    </a>

                    <a
                        href="?page=delete-payment&id=<?= $payment['id'] ?>"
                        class="btn btn-danger btn-sm"
                        onclick="return confirm('Delete payment?')">

                        Delete

                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<?php require __DIR__ . '/layouts/footer.php'; ?>