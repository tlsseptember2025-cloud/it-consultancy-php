<?php

require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/notifications.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        rr.*,
        c.name AS customer_name,
        s.title AS service_title,
        r.id AS request_id,
        r.quoted_price
    FROM refund_requests rr
    JOIN requests r
        ON rr.request_id = r.id
    JOIN customers c
        ON r.customer_id = c.id
    JOIN services s
        ON r.service_id = s.id
    WHERE rr.id = ?
");

$stmt->execute([$id]);

$refundRequest = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate total paid for this request
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE request_id = ?
");

$stmt->execute([
    $refundRequest['request_id']
]);

$totalPaid = (float) $stmt->fetchColumn();

// Calculate total already refunded
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM refunds
    WHERE request_id = ?
");

$stmt->execute([
    $refundRequest['request_id']
]);

$totalRefunded = (float) $stmt->fetchColumn();

$maximumRefund = max(
    0,
    $totalPaid - $totalRefunded
);

if (!$refundRequest) {
    die('Refund request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float) $_POST['amount'];

    if ($amount <= 0) {

        $error = 'Refund amount must be greater than zero.';

    } elseif ($amount > $maximumRefund) {

        $error = 'Refund amount exceeds the maximum refundable balance.';

    } else {

        // Create the actual refund
        $stmt = $pdo->prepare("
    INSERT INTO refunds
    (
        request_id,
        amount,
        refund_date,
        reason,
        status
    )
    VALUES
    (
        ?,
        ?,
        NOW(),
        ?,
        'Processing'
    )
");

        $stmt->execute([
            $refundRequest['request_id'],
            $amount,
            $refundRequest['reason_type']
        ]);

        // Mark the request as approved
        $stmt = $pdo->prepare("
            UPDATE refund_requests
            SET status = 'Approved'
            WHERE id = ?
        ");

        $stmt->execute([
            $refundRequest['id']
        ]);

        $stmt = $pdo->prepare("
    SELECT
	    c.id AS customer_id,
    	c.name,
    	c.email,
    	s.title AS service_title
    FROM requests r
    JOIN customers c
        ON r.customer_id = c.id
    JOIN services s
        ON r.service_id = s.id
    WHERE r.id = ?
");

$stmt->execute([
    $refundRequest['request_id']
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {

    $subject = "Your Refund Request Has Been Approved";

    $formattedAmount = number_format($amount, 2);

$body = "
Dear {$customer['name']},

Your refund request has been approved.

Service:
{$customer['service_title']}

Approved Refund Amount:
AED {$formattedAmount}

Your refund is now being processed by our finance team.
Please allow up to 7 working days for the funds to be processed.

Thank you for your patience.

Kind regards,
IT Consultancy Team
";

    sendEmail(
        $customer['email'],
        $subject,
        nl2br($body)
    );
}
      
createNotification(
    $pdo,
    'customer',
    $customer['customer_id'],
    'Refund Approved',
    'Your refund request has been approved and is now being processed.',
    '?page=customer-refunds'
);

        header('Location: ?page=refund-requests');
        exit;
    }
}

require __DIR__ . '/layouts/header.php';
?>

<h2>Approve Refund Request</h2>

<div class="alert alert-info">

    <strong>Customer:</strong>
    <?= htmlspecialchars($refundRequest['customer_name']) ?>

    <br>

    <strong>Service:</strong>
    <?= htmlspecialchars($refundRequest['service_title']) ?>

    <br>

    <strong>Reason:</strong>
    <?= htmlspecialchars($refundRequest['reason_type']) ?>

</div>

<div class="alert alert-warning">

    <strong>Total Paid:</strong>
    $<?= number_format($totalPaid, 2) ?>

    <br>

    <strong>Already Refunded:</strong>
    $<?= number_format($totalRefunded, 2) ?>

    <br>

    <strong>Maximum Refund Available:</strong>
    $<?= number_format($maximumRefund, 2) ?>

    <?php if (!empty($error)): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

    <form method="POST">

    <div class="mb-3">

        <label class="form-label">
            Refund Amount
        </label>

        <input
            type="number"
            name="amount"
            class="form-control"
            step="0.01"
            min="0"
            max="<?= $maximumRefund ?>"
            value="<?= $maximumRefund ?>"
            required>

        <small class="text-muted">
            You may enter a smaller amount for a partial refund.
        </small>

    </div>

    <div class="mb-3">

        <label class="form-label">
            Admin Notes (Optional)
        </label>

        <textarea
            name="admin_notes"
            class="form-control"
            rows="4"></textarea>

    </div>

    <button
        type="submit"
        class="btn btn-success">

        Confirm Approval

    </button>

    <a
        href="?page=refund-requests"
        class="btn btn-secondary ms-2">

        Cancel

    </a>

</form>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>