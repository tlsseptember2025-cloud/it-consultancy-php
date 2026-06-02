<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT
        payments.*,
        customers.name AS customer_name,
        customers.email,
        customers.phone,
        services.title AS service_title,
        requests.description AS request_description
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    WHERE payments.id = ?
");

$stmt->execute([$id]);

$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Payment Details
        </h2>

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($payment['customer_name']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($payment['email']) ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?= htmlspecialchars($payment['phone']) ?>
        </p>

        <hr>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($payment['service_title']) ?>
        </p>

        <p>
            <strong>Amount:</strong>
            $<?= number_format($payment['amount'], 2) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars($payment['status']) ?>
        </p>

        <p>
            <strong>Payment Date:</strong>
            <?= $payment['payment_date'] ?>
        </p>

        <p>
            <strong>Request:</strong>
        </p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($payment['request_description'])) ?>

        </div>

        <p>
            <strong>Notes:</strong>
        </p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($payment['notes'])) ?>

        </div>

        <a
            href="?page=payments"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>