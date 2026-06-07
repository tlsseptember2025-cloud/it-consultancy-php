<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$requests = $pdo->query("
    SELECT
        requests.id,
        customers.name,
        services.title
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY requests.id DESC
")->fetchAll();

$requestId = $requests[0]['id'] ?? 0;

$totalPaidStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
    WHERE request_id = ?
");

$totalPaidStmt->execute([$requestId]);

$totalPaid = $totalPaidStmt->fetchColumn();

$totalRefundedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM refunds
    WHERE request_id = ?
");

$totalRefundedStmt->execute([$requestId]);

$totalRefunded = $totalRefundedStmt->fetchColumn();

$availableRefund = $totalPaid - $totalRefunded;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($availableRefund <= 0) {

        $error = 'This request has already been fully refunded.';

    } elseif ($_POST['amount'] <= 0) {

        $error = 'Refund amount must be greater than zero.';

    } elseif ($_POST['amount'] > $availableRefund) {

        $error = 'Refund exceeds available refundable amount.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO refunds
            (
                request_id,
                amount,
                refund_date,
                reason
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['request_id'],
            $_POST['amount'],
            $_POST['refund_date'],
            $_POST['reason']
        ]);

        header("Location: ?page=refunds");
        exit;
    }
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<h1 class="mb-4">
    Add Refund
</h1>

<div class="card shadow-sm">

    <div class="card-body">

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Payment
                </label>

                <select
                    name="request_id"
                    class="form-select"
                    required>

                    <?php foreach ($requests as $request): ?>

                       <option value="<?= $request['id'] ?>">

                        <?= htmlspecialchars($request['name']) ?>

                        -

                        <?= htmlspecialchars($request['title']) ?>

</option>

                    <?php endforeach; ?>

                </select>

                <div class="alert alert-info">

    <strong>Total Paid:</strong>
    $<?= number_format($totalPaid, 2) ?>

    <br>

    <strong>Already Refunded:</strong>
    $<?= number_format($totalRefunded, 2) ?>

    <br>

    <strong>Available Refund:</strong>
    $<?= number_format($availableRefund, 2) ?>

</div>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Refund Amount
                </label>

                <input
    type="number"
    step="0.01"
    name="amount"
    class="form-control"
    value="<?= max(0, $availableRefund) ?>"
    max="<?= $availableRefund ?>"
    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Refund Date
                </label>

                <input
                    type="datetime-local"
                    name="refund_date"
                    class="form-control"
                    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Reason
                </label>

                <textarea
                    name="reason"
                    class="form-control"></textarea>

            </div>

            <?php if ($availableRefund > 0): ?>

    <button class="btn btn-danger">
        Save Refund
    </button>

<?php else: ?>

    <button class="btn btn-secondary" disabled>
        Fully Refunded
    </button>

<?php endif; ?>

        </form>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>