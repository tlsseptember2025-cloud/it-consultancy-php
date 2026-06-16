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
        services.title,

        (
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE payments.request_id = requests.id
        ) AS total_paid,

        (
            SELECT COALESCE(SUM(amount), 0)
            FROM refunds
            WHERE refunds.request_id = requests.id
        ) AS total_refunded

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    ORDER BY requests.id DESC
")->fetchAll();

$requestId = (int)($_POST['request_id'] ?? ($requests[0]['id'] ?? 0));

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

        $refundReason = trim($_POST['refund_reason']);
        $details = trim($_POST['reason']);

        $fullReason =
            $refundReason .
            (!empty($details) ? "\n\nDetails:\n" . $details : '');

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
            $fullReason
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

                       <option
                            value="<?= $request['id'] ?>"
                            data-paid="<?= $request['total_paid'] ?>"
                            data-refunded="<?= $request['total_refunded'] ?>">

                        <?= htmlspecialchars($request['name']) ?>

                        -

                        <?= htmlspecialchars($request['title']) ?>

</option>

                    <?php endforeach; ?>

                </select>

                <div class="alert alert-info">

    <strong>Total Paid:</strong>
<span id="totalPaid">
    $<?= number_format($totalPaid, 2) ?>
</span>

<br>

<strong>Already Refunded:</strong>
<span id="totalRefunded">
    $<?= number_format($totalRefunded, 2) ?>
</span>

<br>

<strong>Maximum Refund:</strong>
<span id="availableRefund">
    $<?= number_format($availableRefund, 2) ?>
</span>

</div>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Refund Amount
                </label>

                <input
                    id="refundAmount"
                    type="number"
                    step="0.01"
                    name="amount"
                    class="form-control"
                    value="0.00"
                    min="0.01"
                    max="<?= number_format($availableRefund, 2, '.', '') ?>"
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
        Refund Reason
    </label>

    <select
        name="refund_reason"
        class="form-select"
        required>

        <option value="">
            -- Select a reason --
        </option>

        <option value="Cancellation">
            Cancellation (48+ hours before service)
        </option>

        <option value="Service Unsuccessful">
            Service was not successful
        </option>

        <option value="Duplicate Payment">
            Duplicate payment
        </option>

        <option value="Other">
            Other
        </option>

    </select>

</div>

<div class="mb-3">

    <label class="form-label">
        Additional Details
    </label>

    <textarea
        name="reason"
        class="form-control"
        rows="4"
        placeholder="Please provide more information..."
        required></textarea>

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

<script>

const requestSelect = document.querySelector('select[name="request_id"]');

requestSelect.addEventListener('change', function () {

    const option = this.options[this.selectedIndex];

    const paid = parseFloat(option.dataset.paid || 0);
    const refunded = parseFloat(option.dataset.refunded || 0);
    const available = paid - refunded;

    document.getElementById('totalPaid').textContent =
        '$' + paid.toFixed(2);

    document.getElementById('totalRefunded').textContent =
        '$' + refunded.toFixed(2);

    document.getElementById('availableRefund').textContent =
        '$' + available.toFixed(2);

    // Reset refund amount to 0.00
    const amountInput = document.getElementById('refundAmount');

    amountInput.value = '0.00';

    amountInput.max = available.toFixed(2);

});

</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>