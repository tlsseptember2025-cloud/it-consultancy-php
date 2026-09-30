<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Environment
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheckStmt = $db->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id = ?
        LIMIT 1
    ");

    $customerCheckStmt->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheckStmt->fetch(PDO::FETCH_ASSOC)) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require CONFIG_PATH . '/database.php';

    $db = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Refund ID
|--------------------------------------------------------------------------
*/

$refundId = (int) ($_GET['id'] ?? 0);

if ($refundId <= 0) {

    header('Location: ?page=customer-refunds');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Refund
|--------------------------------------------------------------------------
|
| Demo:
| - authenticated Demo customer
| - same Demo tenant
| - Demo service
|
| Normal:
| - authenticated customer
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            rr.*,

            r.customer_id,
            r.service_id,

            c.name AS customer_name,
            c.email AS customer_email,

            s.title AS service_title

        FROM refund_requests rr

        JOIN requests r
            ON rr.request_id = r.id

        JOIN customers c
            ON r.customer_id = c.id

        JOIN services s
            ON r.service_id = s.id

        WHERE rr.id = ?
          AND r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id

        LIMIT 1
    ");

    $stmt->execute([
        $refundId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            rr.*,

            r.customer_id,
            r.service_id,

            c.name AS customer_name,
            c.email AS customer_email,

            s.title AS service_title

        FROM refund_requests rr

        JOIN requests r
            ON rr.request_id = r.id

        JOIN customers c
            ON r.customer_id = c.id

        JOIN services s
            ON r.service_id = s.id

        WHERE rr.id = ?
          AND r.customer_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $refundId,
        $customerId
    ]);
}


$refund = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Refund Not Found
|--------------------------------------------------------------------------
*/

if (!$refund) {

    header('Location: ?page=customer-refunds');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determine Customer-Facing Status
|--------------------------------------------------------------------------
*/

$refundStatus  = $refund['status'] ?? '';
$paymentStatus = $refund['refund_status'] ?? '';

$displayStatus = 'Pending';
$statusClass   = 'bg-warning text-dark';

if ($refundStatus === 'Rejected') {

    $displayStatus = 'Rejected';
    $statusClass   = 'bg-danger';

} elseif (
    $refundStatus === 'Approved'
    && $paymentStatus === 'Completed'
) {

    $displayStatus = 'Completed';
    $statusClass   = 'bg-success';

} elseif (
    $refundStatus === 'Approved'
    && $paymentStatus === 'Processing'
) {

    $displayStatus = 'Processing';
    $statusClass   = 'bg-warning text-dark';

} elseif ($refundStatus === 'Approved') {

    $displayStatus = 'Approved';
    $statusClass   = 'bg-primary';

} elseif ($refundStatus !== '') {

    $displayStatus = $refundStatus;

    if ($refundStatus === 'Pending') {

        $statusClass = 'bg-warning text-dark';

    } else {

        $statusClass = 'bg-secondary';
    }
}

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>


<div class="container py-5">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Refund Details
            </h2>

            <p class="text-muted mb-0">
                Review the details and history of your refund request.
            </p>

        </div>

        <div>

            <a
                href="?page=customer-refunds"
                class="btn btn-outline-primary">

                Back to My Refunds

            </a>

        </div>

    </div>


    <!-- Refund Summary -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">

            <strong>
                Refund Information
            </strong>

        </div>

        <div class="card-body">

            <div class="row g-4">

                <!-- Reference -->

                <div class="col-md-4">

                    <strong>
                        Refund Reference
                    </strong>

                    <div class="mt-1">

                        RF-<?= str_pad(
                            (int) $refund['id'],
                            6,
                            '0',
                            STR_PAD_LEFT
                        ) ?>

                    </div>

                </div>


                <!-- Service -->

                <div class="col-md-4">

                    <strong>
                        Service
                    </strong>

                    <div class="mt-1">

                        <?= htmlspecialchars(
                            $refund['service_title']
                        ) ?>

                    </div>

                </div>


                <!-- Status -->

                <div class="col-md-4">

                    <strong>
                        Status
                    </strong>

                    <div class="mt-1">

                        <span class="badge <?= $statusClass ?>">

                            <?= htmlspecialchars(
                                $displayStatus
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- Refund Amount -->

                <div class="col-md-4">

                    <strong>
                        Refund Amount
                    </strong>

                    <div class="mt-1">

                        AED <?= number_format(
                            (float) $refund['refund_amount'],
                            2
                        ) ?>

                    </div>

                </div>


                <!-- Requested On -->

                <div class="col-md-4">

                    <strong>
                        Requested On
                    </strong>

                    <div class="mt-1">

                        <?= formatDateTime(
                            $refund['created_at']
                        ) ?>

                    </div>

                </div>


                <!-- Request ID -->

                <div class="col-md-4">

                    <strong>
                        Request ID
                    </strong>

                    <div class="mt-1">

                        #<?= (int) $refund['request_id'] ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Reason -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">

            <strong>
                Refund Reason
            </strong>

        </div>

        <div class="card-body">

            <div class="mb-3">

                <strong>
                    Reason:
                </strong>

                <?= htmlspecialchars(
                    $refund['reason_type']
                ) ?>

            </div>


            <?php if (!empty($refund['reason_details'])): ?>

                <div>

                    <strong>
                        Details:
                    </strong>

                    <div class="mt-2">

                        <?= nl2br(
                            htmlspecialchars(
                                $refund['reason_details']
                            )
                        ) ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- Refund Timeline -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">

            <strong>
                Refund History
            </strong>

        </div>

        <div class="card-body">

            <div class="timeline">


                <!-- Refund Submitted -->

                <div class="mb-4">

                    <div class="d-flex align-items-start">

                        <div
                            class="me-3"
                            style="
                                width: 14px;
                                height: 14px;
                                border-radius: 50%;
                                background-color: #0d6efd;
                                margin-top: 5px;
                            ">

                        </div>

                        <div>

                            <strong>
                                Refund Submitted
                            </strong>

                            <div class="text-muted small">

                                <?= formatDateTime(
                                    $refund['created_at']
                                ) ?>

                            </div>

                            <div class="mt-1">

                                Your refund request was submitted
                                for review.

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Approval / Rejection -->

                <?php if (!empty($refund['reviewed_at'])): ?>

                    <div class="mb-4">

                        <div class="d-flex align-items-start">

                            <div
                                class="me-3"
                                style="
                                    width: 14px;
                                    height: 14px;
                                    border-radius: 50%;
                                    background-color:
                                        <?= $refundStatus === 'Rejected'
                                            ? '#dc3545'
                                            : '#198754' ?>;
                                    margin-top: 5px;
                                ">

                            </div>

                            <div>

                                <?php if (
                                    $refundStatus === 'Rejected'
                                ): ?>

                                    <strong>
                                        Refund Rejected
                                    </strong>

                                    <div class="mt-1">

                                        Your refund request was
                                        not approved.

                                    </div>

                                <?php else: ?>

                                    <strong>
                                        Refund Approved
                                    </strong>

                                    <div class="mt-1">

                                        Your refund request was
                                        approved.

                                    </div>

                                <?php endif; ?>

                                <div class="text-muted small">

                                    <?= formatDateTime(
                                        $refund['reviewed_at']
                                    ) ?>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- Processing -->

                <?php if (
                    $refundStatus === 'Approved'
                    && $paymentStatus === 'Processing'
                ): ?>

                    <div class="mb-4">

                        <div class="d-flex align-items-start">

                            <div
                                class="me-3"
                                style="
                                    width: 14px;
                                    height: 14px;
                                    border-radius: 50%;
                                    background-color: #ffc107;
                                    margin-top: 5px;
                                ">

                            </div>

                            <div>

                                <strong>
                                    Refund Processing
                                </strong>

                                <div class="mt-1">

                                    Your refund has been approved
                                    and is currently being processed.

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- Completed -->

                <?php if (
                    $refundStatus === 'Approved'
                    && $paymentStatus === 'Completed'
                ): ?>

                    <div class="mb-2">

                        <div class="d-flex align-items-start">

                            <div
                                class="me-3"
                                style="
                                    width: 14px;
                                    height: 14px;
                                    border-radius: 50%;
                                    background-color: #198754;
                                    margin-top: 5px;
                                ">

                            </div>

                            <div>

                                <strong>
                                    Refund Completed
                                </strong>

                                <div class="mt-1">

                                    Your refund has been successfully
                                    completed.

                                </div>

                                <?php if (!empty($refund['reviewed_at'])): ?>

                                    <div class="text-muted small">

                                        <?= formatDateTime(
                                            $refund['reviewed_at']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


            </div>

        </div>

    </div>


    <!-- Bottom Navigation -->

    <div class="d-flex justify-content-between">

        <a
            href="?page=customer-refunds"
            class="btn btn-outline-primary">

            ← Back to My Refunds

        </a>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>