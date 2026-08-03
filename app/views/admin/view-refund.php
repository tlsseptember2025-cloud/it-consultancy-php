<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';

require CONFIG_PATH . '/database.php';

$refundId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        rr.*,
        c.name AS customer_name,
        c.email,
        s.title AS service_title

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    WHERE rr.id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die('Refund not found.');
}

?>

<h2 class="mb-4">Refund Details</h2>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <strong><i class="bi bi-person-fill"></i> Customer Information</strong>

    </div>

    <div class="card-body">

        <table class="table table-bordered mb-0">

            <tr>
                <th width="220">Customer Name</th>
                <td><?= htmlspecialchars($refund['customer_name']) ?></td>
            </tr>

            <tr>
                <th>Email Address</th>
                <td><?= htmlspecialchars($refund['email']) ?></td>
            </tr>

        </table>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <strong><i class="bi bi-briefcase-fill"></i> Service Information</strong>
    </div>

    <div class="card-body">

        <table class="table table-bordered mb-0">

            <tr>
                <th width="220">Service</th>
                <td><?= htmlspecialchars($refund['service_title']) ?></td>
            </tr>

        </table>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <strong><i class="bi bi-cash-stack"></i> Refund Information</strong>

    </div>

    <div class="card-body">

        <table class="table table-bordered mb-0">

            <tr>
                <th width="220">Reason Type</th>
                <td><?= htmlspecialchars($refund['reason_type']) ?></td>
            </tr>

            <tr>
                <th>Reason Details</th>
                <td><?= nl2br(htmlspecialchars($refund['reason_details'])) ?></td>
            </tr>

            <tr>
                <th>Refund Amount</th>
                <td>AED <?= number_format($refund['refund_amount'], 2) ?></td>
            </tr>

            <tr>
                <th>Decision</th>

                <td>

                    <?php if (
                        $refund['status'] === 'Approved' &&
                        $refund['refund_status'] === 'Completed'
                    ): ?>

                        <span class="badge rounded-pill bg-success fs-6 px-3 py-2">
                            Completed
                        </span>

                    <?php elseif ($refund['status'] === 'Rejected'): ?>

                        <span class="badge rounded-pill bg-danger fs-6 px-3 py-2">
                            Rejected
                        </span>

                    <?php endif; ?>

                </td>

            </tr>

            <tr>
                <th>Review Notes</th>
                <td><?= nl2br(htmlspecialchars($refund['review_notes'])) ?></td>
            </tr>

        </table>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <strong><i class="bi bi-clock-history"></i> Timeline</strong>

    </div>

    <div class="card-body">

        <table class="table table-bordered mb-0">

            <tr>
                <th width="220">Refund Requested</th>
                <td>
                   <?= formatDateTime($refund['created_at']) ?>
                </td>
            </tr>

            <tr>
                <th>Refund Closed</th>
                <td>
                    <?= date(
                        'l, d M Y - h:i A',
                        strtotime($refund['reviewed_at'])
                    ) ?>
                </td>
            </tr>

        </table>

    </div>

</div>

<div class="text-end mt-4">

    <a href="?page=archived-refunds"
       class="btn btn-secondary">

        <i class="bi bi-arrow-left"></i>

        Back to Archived Refunds

    </a>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>