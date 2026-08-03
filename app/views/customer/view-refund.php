<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['customer'])) {
    header('Location: ?page=public-login');
    exit;
}

$customerId = $_SESSION['customer']['id'];
$refundId   = (int)($_GET['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Load Refund
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    rr.*,

    s.title AS service_title,

    c.name,
    c.email

FROM refund_requests rr

JOIN requests r
    ON r.id = rr.request_id

JOIN customers c
    ON c.id = r.customer_id

JOIN services s
    ON s.id = r.service_id

WHERE

    rr.id = ?

AND

    r.customer_id = ?

LIMIT 1

");

$stmt->execute([
    $refundId,
    $customerId
]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {

    header('Location: ?page=refund-history');
    exit;
}

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

if ($refund['status'] == 'Pending') {

    $statusBadge = '
        <span class="badge rounded-pill bg-primary fs-6 px-3 py-2">
            Pending
        </span>';

} elseif ($refund['refund_status'] == 'Processing') {

    $statusBadge = '
        <span class="badge rounded-pill bg-warning text-dark fs-6 px-3 py-2">
            Processing
        </span>';

} elseif ($refund['status'] == 'Rejected') {

    $statusBadge = '
        <span class="badge rounded-pill bg-danger fs-6 px-3 py-2">
            Rejected
        </span>';

} else {

    $statusBadge = '
        <span class="badge rounded-pill bg-success fs-6 px-3 py-2">
            Completed
        </span>';

}

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">

                Refund Details

            </h2>

            <small class="text-muted">

                Refund Reference

                <strong>

                    RF-<?= str_pad($refund['id'], 6, '0', STR_PAD_LEFT) ?>

                </strong>

            </small>

        </div>

        <a
            href="?page=refund-history"
            class="btn btn-secondary">

            ← Back

        </a>

    </div>

    <div class="row">

        <!-- Service Information -->

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-primary text-white">

                    <strong>

                        Service Information

                    </strong>

                </div>

                <div class="card-body">

                    <p class="mb-0">

                        <strong>Service</strong>

                        <br>

                        <?= htmlspecialchars($refund['service_title']) ?>

                    </p>

                </div>

            </div>

        </div>

        <!-- Refund Information -->

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-success text-white">

                    <strong>

                        Refund Information

                    </strong>

                </div>

                <div class="card-body">

                    <p>

                        <strong>

                            Refund Amount

                        </strong>

                        <br>

                        <?php if ($refund['status'] == 'Rejected'): ?>

                            -

                        <?php else: ?>

                            AED <?= number_format($refund['refund_amount'],2) ?>

                        <?php endif; ?>

                    </p>

                    <p class="mb-0">

                        <strong>

                            Status

                        </strong>

                        <br><br>

                        <?= $statusBadge ?>

                    </p>

                </div>

            </div>

        </div>

                <!-- Refund Reason -->

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-info text-white">

                    <strong>

                        Refund Reason

                    </strong>

                </div>

                <div class="card-body">

                    <p>

                        <strong>

                            Reason Type

                        </strong>

                        <br>

                        <?= htmlspecialchars($refund['reason_type']) ?>

                    </p>

                    <p class="mb-0">

                        <strong>

                            Reason Details

                        </strong>

                        <br>

                        <?= nl2br(htmlspecialchars($refund['reason_details'])) ?>

                    </p>

                </div>

            </div>

        </div>

        <!-- Timeline -->

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header bg-secondary text-white">

                    <strong>

                        Timeline

                    </strong>

                </div>

                <div class="card-body">

                    <div class="mb-4">

                        <strong>

                            Refund Submitted

                        </strong>

                        <br>

                        <small class="text-muted">

                           <?= formatDateTime($refund['created_at']) ?>

                        </small>

                    </div>

                    <?php if (!empty($refund['reviewed_at'])): ?>

                        <div class="mb-4">

                            <strong>

                                <?php
                                if ($refund['status'] == 'Rejected') {
                                    echo 'Refund Rejected';
                                } elseif ($refund['refund_status'] == 'Processing') {
                                    echo 'Refund Approved';
                                } else {
                                    echo 'Refund Approved';
                                }
                                ?>

                            </strong>

                            <br>

                            <small class="text-muted">

                                <?= date(
                                    'l, d M Y - h:i A',
                                    strtotime($refund['reviewed_at'])
                                ) ?>

                            </small>

                        </div>

                    <?php endif; ?>

                    <?php if ($refund['refund_status'] == 'Completed'): ?>

                        <div class="mb-0">

                            <strong>

                                Refund Completed

                            </strong>

                            <br>

                            <small class="text-muted">

                                <?= date(
                                    'l, d M Y - h:i A',
                                    strtotime($refund['reviewed_at'])
                                ) ?>

                            </small>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>