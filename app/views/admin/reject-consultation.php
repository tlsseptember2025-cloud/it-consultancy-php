<?php

require_once APP_PATH . '/helpers/DateHelper.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid request.');

}

/*
|--------------------------------------------------------------------------
| Load Consultation Details
|--------------------------------------------------------------------------
*/

$consultationStmt = $pdo->prepare("
    SELECT
        r.id,
        c.name,
        s.title AS service_title,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.id = ?
");

$consultationStmt->execute([$requestId]);

$request = $consultationStmt->fetch();

if (!$request) {

    die('Consultation not found.');

}

/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($reason === '') {

        die('Please enter a rejection reason.');

    }

    /*
    |--------------------------------------------------------------------------
    | Load Administrator
    |--------------------------------------------------------------------------
    */

    $adminStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
    ");

    $adminStmt->execute([$_SESSION['user']]);

    $admin = $adminStmt->fetch();

    if (!$admin) {

        die('Admin not found.');

    }

    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Consultation Rejected',
            consultation_rejection_reason = ?,
            consultation_rejected_at = NOW(),
            consultation_rejected_by = ?,
            consultation_reschedules = 0
        WHERE id = ?
    ");

    $updateStmt->execute([
        $reason,
        $admin['id'],
        $requestId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Record Audit Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $pdo,
        $requestId,
        RequestEventHelper::EVENT_CONSULTATION_REJECTED,
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation Rejected',
        'The administrator rejected the scheduled consultation. Reason: ' . $reason,
        true
    );

    /*
    |--------------------------------------------------------------------------
    | Load Customer
    |--------------------------------------------------------------------------
    */

    $customerStmt = $pdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title

        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id

        JOIN services s
            ON s.id = r.service_id

        WHERE r.id = ?
    ");

    $customerStmt->execute([$requestId]);

    $customer = $customerStmt->fetch();

    if (!$customer) {

        die('Customer not found.');

    }

    /*
    |--------------------------------------------------------------------------
    | Send Email
    |--------------------------------------------------------------------------
    */

    sendEmail(
        $customer['email'],
        'Consultation Rejected',
        "
        <h2>Hello {$customer['name']},</h2>

        <p>
            Unfortunately, your scheduled consultation has been rejected.
        </p>

        <p>
            <strong>Service</strong><br>
            {$customer['service_title']}
        </p>

        <p>
            <strong>Reason</strong><br>
            " . nl2br(htmlspecialchars($reason)) . "
        </p>

        <p>
            Please log in to your customer portal for more information
            or to arrange another consultation if applicable.
        </p>

        <p>
            <a
                href='" . APP_URL . "/?page=public-login'
                style='
                    background:#0d6efd;
                    color:#ffffff;
                    padding:12px 22px;
                    text-decoration:none;
                    border-radius:6px;
                    display:inline-block;
                    font-weight:600;
                '>
                Customer Portal
            </a>
        </p>

        <p>
            Kind Regards,<br>
            <strong>WAHBIB Consultancy Team</strong>
        </p>
        "
    );

    /*
    |--------------------------------------------------------------------------
    | Create Customer Notification
    |--------------------------------------------------------------------------
    */

    createNotification(
        $pdo,
        'customer',
        $customer['customer_id'],
        'Consultation Rejected',
        'Your consultation has been rejected. Please review the rejection reason and arrange another consultation if required.',
        '?page=customer-requests'
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header('Location: ?page=requests');
    exit;
}

?>

<?php require APP_PATH . '/views/layouts/header-admin.php'; ?>

<div class="container py-4">

    <!-- Page Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">

                <i class="bi bi-x-circle-fill text-danger"></i>

                Reject Consultation

            </h2>

            <p class="text-muted mb-0">

                Review the consultation details before rejecting this consultation.

            </p>

        </div>

        <a
            href="?page=review-consultation&id=<?= $request['id'] ?>"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

    <!-- Consultation Details -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-danger text-white">

            <h5 class="mb-0">

                <i class="bi bi-calendar-event"></i>

                Consultation Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-6">

                    <label class="text-muted small">

                        Customer

                    </label>

                    <div class="fw-semibold fs-5">

                        <?= htmlspecialchars($request['name']) ?>

                    </div>

                </div>

                <div class="col-md-6">

                    <label class="text-muted small">

                        Service

                    </label>

                    <div class="fw-semibold fs-5">

                        <?= htmlspecialchars($request['service_title']) ?>

                    </div>

                </div>

                <div class="col-md-4">

                    <label class="text-muted small">

                        Consultation Date

                    </label>

                    <div>

                        <i class="bi bi-calendar3"></i>

                        <?= formatDate($request['slot_date']) ?>

                    </div>

                </div>

                <div class="col-md-4">

                    <label class="text-muted small">

                        Consultation Time

                    </label>

                    <div>

                        <i class="bi bi-clock"></i>

                        <?= htmlspecialchars($request['slot_time']) ?>

                    </div>

                </div>

                <div class="col-md-4">

                    <label class="text-muted small">

                        Meeting Method

                    </label>

                    <div>

                        <i class="bi bi-camera-video"></i>

                        <?= htmlspecialchars($request['consultation_method']) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Rejection Form -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-light">

            <h5 class="mb-0">

                <i class="bi bi-exclamation-triangle-fill text-danger"></i>

                Rejection Details

            </h5>

        </div>

        <div class="card-body">

            <div class="alert alert-warning mb-4">

                <strong>Important:</strong>

                The customer will receive an email and a notification
                containing the rejection reason you provide below.

            </div>

            <form method="post">

                <div class="mb-4">

                    <label
                        for="rejection_reason"
                        class="form-label fw-semibold">

                        Reason for Rejection

                    </label>

                    <textarea
                        id="rejection_reason"
                        name="rejection_reason"
                        class="form-control"
                        rows="6"
                        placeholder="Explain clearly why this consultation is being rejected..."
                        required></textarea>

                    <div class="form-text">

                        Provide enough information so the customer understands
                        why the consultation is being rejected.

                    </div>

                </div>

                <hr>

                <div class="d-flex justify-content-between">

                    <a
                        href="?page=review-consultation&id=<?= $request['id'] ?>"
                        class="btn btn-secondary">

                        <i class="bi bi-arrow-left"></i>

                        Cancel

                    </a>

                    <button
                        type="submit"
                        class="btn btn-danger">

                        <i class="bi bi-x-circle"></i>

                        Reject Consultation

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>