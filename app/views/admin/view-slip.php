<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        ps.*,
        c.name AS customer_name,
        s.title AS service_title

    FROM payment_slips ps

    JOIN customers c
        ON ps.customer_id = c.id

    JOIN requests r
        ON ps.request_id = r.id

    JOIN services s
        ON r.service_id = s.id

    WHERE ps.id = ?
");

$stmt->execute([$id]);

$slip = $stmt->fetch();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Deposit Slip Review</h2>

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($slip['customer_name']) ?>
        </p>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($slip['service_title']) ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?= htmlspecialchars($slip['status']) ?>
        </p>

        <hr>

        <?php

        $fileExtension = strtolower(
            pathinfo($slip['file_name'], PATHINFO_EXTENSION)
        );

        ?>

       <?php if ($fileExtension === 'pdf'): ?>

    <iframe
        src="uploads/slips/<?= htmlspecialchars($slip['file_name']) ?>"
        width="100%"
        height="600"
        class="border">

    </iframe>

<?php else: ?>

    <img
        src="uploads/slips/<?= htmlspecialchars($slip['file_name']) ?>"
        class="img-fluid border"
        style="max-width: 100%;">

<?php endif; ?>

       <hr>

            <a
                href="uploads/slips/<?= htmlspecialchars($slip['file_name']) ?>"
                target="_blank"
                class="btn btn-primary me-2">

                Download Receipt

            </a>

            <a
                href="?page=approve-slip&id=<?= $slip['id'] ?>"
                class="btn btn-success">

                Approve Payment

            </a>

            <a
                href="?page=reject-slip&id=<?= $slip['id'] ?>"
                class="btn btn-danger">

                Reject Payment

            </a>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>