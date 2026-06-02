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

        <a
            href="?page=customers"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>