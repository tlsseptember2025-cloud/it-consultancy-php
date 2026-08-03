<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/notifications.php';

$refundId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        rr.*,

        r.id AS request_id,
        r.customer_id,
        r.workflow_stage,

        c.name,
        c.email,
        c.phone,

        s.title AS service_title,

        sb.slot_id,

        p.amount AS payment_amount,
        p.status AS payment_status,

        ss.service_date,
        ss.service_time

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    LEFT JOIN payments p
        ON p.request_id = r.id

    WHERE rr.id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die('Refund request not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notes = trim($_POST['review_notes'] ?? '');
    $decision = $_POST['decision'] ?? '';
    $refundAmount = trim($_POST['refund_amount'] ?? '');

    if ($decision == '') {

    $error = 'Please select a decision.';

    } elseif ($decision == 'approve' && $refundAmount == '') {

        $error = 'Please enter the refund amount.';

    } elseif ($decision == 'approve' && !is_numeric($refundAmount)) {

        $error = 'Refund amount must be a valid number.';

    } elseif ($decision == 'approve' && $refundAmount > $refund['payment_amount']) {

        $error = 'Refund amount cannot exceed the amount paid by the customer.';

    } elseif ($decision == 'reject' && $notes == '') {

        $error = 'Please provide review notes when rejecting a refund request.';

    } else {

        $status = ($decision == 'approve')
            ? 'Approved'
            : 'Rejected';

        $stmt = $pdo->prepare("
            UPDATE refund_requests
            SET
                status = ?,
                review_notes = ?,
                refund_amount = ?,
                refund_status = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
        $status,
        $notes,
        $decision == 'approve' ? $refundAmount : null,
        $decision == 'approve' ? 'Processing' : null,
        $refundId
        ]);

        createNotification(
            $pdo,
            'customer',
            $refund['customer_id'],
            'Refund Request Updated',
            'Your refund request for "' .
                $refund['service_title'] .
                '" has been ' .
                strtolower($status) .
                '.',
            '?page=customer-refunds'
        );

        header('Location: ?page=refund-requests');
        exit;
    }
}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2 class="mb-4">Review Refund Request</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <!-- Customer Information -->

        <div class="card mb-4">

            <div class="card-header">
                <strong>Customer Information</strong>
            </div>

            <div class="card-body">

                <p><strong>Name:</strong>
                    <?= htmlspecialchars($refund['name']) ?>
                </p>

                <p><strong>Email:</strong>
                    <?= htmlspecialchars($refund['email']) ?>
                </p>

                <p><strong>Phone:</strong>
                    <?= htmlspecialchars($refund['phone']) ?>
                </p>

            </div>

        </div>

        <!-- Service Information -->

        <div class="card mb-4">

            <div class="card-header">
                <strong>Service Information</strong>
            </div>

            <div class="card-body">

                <p><strong>Service:</strong>
                    <?= htmlspecialchars($refund['service_title']) ?>
                </p>

                <p><strong>Date:</strong>
                    <?= date('M d, Y', strtotime($refund['service_date'])) ?>
                </p>

                <p><strong>Time:</strong>
                    <?= date('h:i A', strtotime($refund['service_time'])) ?>
                </p>

            </div>

        </div>

        <!-- Refund Information -->

        <div class="card mb-4">

            <div class="card-header">
                <strong>Refund Information</strong>
            </div>

            <div class="card-body">

                <p><strong>Reason:</strong>
                    <?= htmlspecialchars($refund['reason_type']) ?>
                </p>

                <p><strong>Details:</strong><br>
                    <?= nl2br(htmlspecialchars($refund['reason_details'])) ?>
                </p>

                <p><strong>Status:</strong>
                    <?= htmlspecialchars($refund['status']) ?>
                </p>

                <p><strong>Requested On:</strong>
                    <?= formatDateTime($refund['created_at']) ?>
                </p>

            </div>

        </div>

        <!-- Refund Review -->

        <div class="card mb-4">

            <div class="card-header">
                <strong>Refund Review</strong>
            </div>

            <div class="card-body">

                <div class="mb-3">

                    <label class="form-label">
                        Review Notes
                    </label>

                    <textarea
                        name="review_notes"
                        class="form-control"
                        rows="5"
                        placeholder="Enter your review notes here..."><?= htmlspecialchars($refund['review_notes'] ?? '') ?></textarea>

                </div>

            </div>

        </div>

        <div class="card border-primary mb-3">

    <div class="card-header bg-primary text-white">
        Payment Information
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">
                <strong>Payment Status</strong><br>
                <?= htmlspecialchars($refund['payment_status'] ?? 'N/A') ?>
            </div>

            <div class="col-md-6">
                <strong>Amount Paid</strong><br>
                AED <?= number_format($refund['payment_amount'] ?? 0, 2) ?>
            </div>

        </div>

        <hr>

        <strong class="text-success">
            Maximum Refund Allowed:
            AED <?= number_format($refund['payment_amount'] ?? 0, 2) ?>
        </strong>

    </div>

</div>

        <div class="mb-3">
    <label for="refund_amount" class="form-label">
        Refund Amount (AED)
    </label>
    <input
    type="number"
    name="refund_amount"
    class="form-control"
    min="0.01"
    max="<?= $refund['payment_amount'] ?>"
    step="0.01"
    value="<?= htmlspecialchars($refund['refund_amount'] ?? '') ?>">
    <small class="text-muted">
        Required when approving a refund.
    </small>
</div>

        <!-- Decision -->

        <div class="card mb-4">

            <div class="card-header">
                <strong>Decision</strong>
            </div>

            <div class="card-body">

                <div class="form-check mb-3">

                    <input
                        class="form-check-input"
                        type="radio"
                        name="decision"
                        id="approve"
                        value="approve"
                        checked>

                    <label class="form-check-label" for="approve">
                        Approve Refund
                    </label>

                </div>

                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="radio"
                        name="decision"
                        id="reject"
                        value="reject">

                    <label class="form-check-label" for="reject">
                        Reject Refund
                    </label>

                </div>

            </div>

        </div>

        <hr>

        <div class="d-flex justify-content-end gap-2">

            <a href="?page=refund-requests"
               class="btn btn-secondary">
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-success">
                Save Decision
            </button>

        </div>

    </form>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>