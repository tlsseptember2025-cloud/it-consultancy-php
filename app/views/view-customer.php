<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE id = ?
");

$stmt->execute([$id]);

$customer = $stmt->fetch();

$requestsStmt = $pdo->prepare("
    SELECT
        requests.*,
        services.title AS service_title
    FROM requests
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.customer_id = ?
    ORDER BY requests.created_at DESC
");

$requestsStmt->execute([$id]);

$requests = $requestsStmt->fetchAll();

$paymentsStmt = $pdo->prepare("
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

$paymentsStmt->execute([$id]);

$payments = $paymentsStmt->fetchAll();

if (!$customer) {
    die('Customer not found');
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Customer Details
        </h2>

        <p>
            <strong>Name:</strong>
            <?= htmlspecialchars($customer['name']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($customer['email']) ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?= htmlspecialchars($customer['phone']) ?>
        </p>

        <p>
            <strong>Company:</strong>
            <?= htmlspecialchars($customer['company']) ?>
        </p>

        <p>
            <strong>Notes:</strong><br>
            <?= nl2br(htmlspecialchars($customer['notes'])) ?>
        </p>

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

        <?php foreach ($requests as $request): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($request['service_title']) ?>
                </td>

                <td>
                    $<?= number_format($request['quoted_price'], 2) ?>
                </td>

                <td>
                    <?= htmlspecialchars($request['status']) ?>
                </td>

            </tr>

        <?php endforeach; ?>

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

        <?php foreach ($payments as $payment): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($payment['service_title']) ?>
                </td>

                <td>
                    $<?= number_format($payment['amount'], 2) ?>
                </td>

                <td>
                    <?= htmlspecialchars($payment['status']) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

        <a
            href="?page=customers"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>