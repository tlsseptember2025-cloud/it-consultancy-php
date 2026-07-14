<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        customers.name AS customer_name,
        customers.email,
        customers.phone,
        customers.company,
        services.title AS service_title
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    WHERE requests.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found');
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Details
        </h2>

        <p><strong>Customer:</strong> <?= htmlspecialchars($request['customer_name']) ?></p>

        <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>

        <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>

        <p><strong>Company:</strong> <?= htmlspecialchars($request['company']) ?></p>

        <hr>

        <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

        <p><strong>Quoted Price:</strong> $<?= number_format($request['quoted_price'], 2) ?></p>

        <p><strong>Status:</strong> <?= htmlspecialchars($request['status']) ?></p>

        <p><strong>Description:</strong></p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($request['description'])) ?>

        </div>

        <p><strong>Date:</strong> <?= $request['created_at'] ?></p>

        <a
            href="?page=requests"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>