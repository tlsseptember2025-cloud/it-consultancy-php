<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid payment slip.');
}

$stmt = $pdo->prepare("
    SELECT
        ps.*,
        c.name AS customer_name,
        c.email AS customer_email,
        s.title AS service_title,
        r.quoted_price
    FROM payment_slips ps
    JOIN customers c
        ON ps.customer_id = c.id
    JOIN requests r
        ON ps.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    WHERE ps.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$slip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slip) {
    die('Payment slip not found.');
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Deposit Slip Review</h2>

        <div class="row mb-3">

            <div class="col-md-6">

                <p class="mb-2">
                    <strong>Customer:</strong><br>
                    <?= htmlspecialchars($slip['customer_name']) ?>
                </p>

            </div>

            <div class="col-md-6">

                <p class="mb-2">
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($slip['customer_email']) ?>
                </p>

            </div>

        </div>

        <p>
            <strong>Service:</strong><br>
            <?= htmlspecialchars($slip['service_title']) ?>
        </p>

        <div class="alert alert-warning">

            <strong>Amount Required:</strong>

            AED <?= number_format((float) $slip['quoted_price'], 2) ?>

            <br>

            <small class="text-muted">
                Verify that the payment receipt shows the full required amount
                before approving the payment.
            </small>

        </div>

        <p>
            <strong>Status:</strong>

            <?php if ($slip['status'] === 'Pending'): ?>

                <span class="badge bg-warning text-dark">
                    Pending Review
                </span>

            <?php elseif ($slip['status'] === 'Approved'): ?>

                <span class="badge bg-success">
                    Approved
                </span>

            <?php elseif ($slip['status'] === 'Rejected'): ?>

                <span class="badge bg-danger">
                    Rejected
                </span>

            <?php else: ?>

                <span class="badge bg-secondary">
                    <?= htmlspecialchars($slip['status']) ?>
                </span>

            <?php endif; ?>

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

        <?php if ($slip['status'] === 'Pending'): ?>

            <a
                href="?page=approve-slip&id=<?= $slip['id'] ?>"
                class="btn btn-success"
                onclick="return confirm('Confirm that the receipt has been checked and shows the full required amount before approving this payment.');">

                Approve Payment

            </a>

            <a
                href="?page=reject-slip&id=<?= $slip['id'] ?>"
                class="btn btn-danger"
                onclick="return confirm('Reject this payment receipt?');">

                Reject Payment

            </a>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>