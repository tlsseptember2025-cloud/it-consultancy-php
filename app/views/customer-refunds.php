<?php

require_once __DIR__ . '/../helpers/auth.php';

requireCustomerLogin();

$customerId = (int) $_SESSION['customer']['id'];

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

require __DIR__ . '/layouts/header.php';

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        rr.*,
        s.title AS service_title,
        rf.amount AS refund_amount,
        rf.status AS refund_status
    FROM refund_requests rr
    JOIN requests r
        ON rr.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    LEFT JOIN refunds rf
        ON rf.request_id = r.id
    WHERE r.customer_id = ?
    ORDER BY rr.id DESC
");

$stmt->execute([$customerId]);

$refunds = $stmt->fetchAll();

?>

<h1 class="mb-4">

    My Refund Requests

</h1>

<div class="card shadow-sm">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

    <tr>

        <th>Service</th>

        <th>Reason</th>

        <th>Status</th>

        <th>Requested On</th>

    </tr>

</thead>

            <tbody>

                <?php if ($refunds): ?>

                    <?php foreach ($refunds as $refund): ?>

                       <tr>

    <td>
        <?= htmlspecialchars($refund['service_title']) ?>
    </td>

    <td>
        <?= htmlspecialchars($refund['reason_type']) ?>
    </td>

    <td>

<?php if ($refund['status'] === 'Pending'): ?>

    <span class="badge bg-warning text-dark">
        Pending
    </span>

    <br>

    <small class="text-muted">
        Your refund request is under review.
    </small>

<?php elseif ($refund['status'] === 'Approved'): ?>

    <?php if (($refund['refund_status'] ?? '') === 'Completed'): ?>

        <span class="badge bg-primary">
            Completed
        </span>

        <br>

        <small class="text-muted">
            Refund Amount:
            <strong>
                AED <?= number_format($refund['refund_amount'], 2) ?>
            </strong>

            <br>

            Your refund has been successfully processed.
        </small>

    <?php else: ?>

        <span class="badge bg-success">
            Approved
        </span>

        <br>

        <small class="text-muted">
            Refund Amount:
            <strong>
                AED <?= number_format($refund['refund_amount'], 2) ?>
            </strong>

            <br>

            Under processing. Expected completion within
            <strong>7 working days</strong>.
        </small>

    <?php endif; ?>

<?php elseif ($refund['status'] === 'Rejected'): ?>

    <span class="badge bg-danger">
        Rejected
    </span>

    <br>

    <small class="text-muted">
        Unfortunately, this refund request was not approved.
    </small>

<?php else: ?>

    <span class="badge bg-secondary">
        <?= htmlspecialchars($refund['status'] ?? 'Unknown') ?>
    </span>

<?php endif; ?>

</td>

    <td>
        <?= date('M d, Y', strtotime($refund['created_at'])) ?>
    </td>

</tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="4" class="text-center">

                            No refunds found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>