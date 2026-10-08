<?php

require_once APP_PATH . '/helpers/auth.php';
require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/meeting.php';

requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

$isDemoAdmin = isset($_SESSION['demo_user']);
$reviewPdo = $pdo;
$adminTenantId = 0;

if ($isDemoAdmin) {
    $demoDbPath = CONFIG_PATH . '/demo-database.php';
    if (!is_file($demoDbPath)) {
        die('Demo database configuration not found.');
    }

    require $demoDbPath;

    if (!isset($demoPdo) || !($demoPdo instanceof PDO)) {
        die('Demo database connection unavailable.');
    }

    $reviewPdo = $demoPdo;
    $adminTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($adminTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $reviewPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1
    ");
    $tenantStmt->execute([$adminTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminId = (int) ($_SESSION['demo_user']['id'] ?? 0);

    $adminStmt = $reviewPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $adminStmt->execute([$adminId, $adminTenantId]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    $adminId = 0;
    $adminEmail = (string) ($_SESSION['user']['email'] ?? '');

    if ($adminEmail === '') {
        session_destroy();
        header('Location: ?page=login');
        exit;
    }

    $adminStmt = $reviewPdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
          AND is_demo_account = 0
          AND is_super_admin = 0
        LIMIT 1
    ");
    $adminStmt->execute([$adminEmail]);
    $adminId = (int) ($adminStmt->fetchColumn() ?: 0);

    if ($adminId <= 0) {
        session_destroy();
        header('Location: ?page=login');
        exit;
    }
}

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Pending Reschedule
|--------------------------------------------------------------------------
*/

$stmt = $reviewPdo->prepare("
    SELECT
        r.id,
        r.customer_id,
        r.agent_id,
        r.workflow_stage,
        r.pending_reschedule_slot_id,
        r.pending_reschedule_reason,
        r.pending_reschedule_requested_at,

        c.name AS customer_name,
        c.email AS customer_email,

        s.title AS service_name,

        a.name AS agent_name,

        old_cs.slot_date AS old_slot_date,
        old_cs.slot_time AS old_slot_time,

        new_cs.slot_date AS new_slot_date,
        new_cs.slot_time AS new_slot_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots old_cs
        ON old_cs.id = cb.slot_id

    LEFT JOIN consultation_slots new_cs
        ON new_cs.id = r.pending_reschedule_slot_id

    WHERE
        r.id = ?
        AND r.workflow_stage = 'Awaiting Reschedule Approval'
        AND r.pending_reschedule_slot_id IS NOT NULL
        AND (
            ? = 0
            OR (
                c.demo_tenant_id = ?
                AND c.is_demo_account = 1
                AND s.demo_tenant_id = ?
            )
        )

    LIMIT 1
");

$stmt->execute([$requestId, $adminTenantId, $adminTenantId, $adminTenantId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {

    die('Pending reschedule request not found.');
}


/*
|--------------------------------------------------------------------------
| Admin Decision
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['decision'])
) {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: ?page=review-reschedule-consultation&id=' . $requestId);
        exit;
    }

    $decision = (string) $_POST['decision'];

    if (!in_array($decision, ['approve', 'reject'], true)) {
        $_SESSION['error'] = 'Invalid decision.';
        header('Location: ?page=review-reschedule-consultation&id=' . $requestId);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Approve Reschedule
    |--------------------------------------------------------------------------
    */

    if ($decision === 'approve') {

        try {

            $reviewPdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Requested Slot
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                SELECT
                    id,
                    is_booked,
                    slot_date,
                    slot_time
                FROM consultation_slots
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $consultation['pending_reschedule_slot_id']
            ]);

            $newSlot = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$newSlot) {

                throw new Exception(
                    'The requested consultation slot no longer exists.'
                );
            }

            if ((int) $newSlot['is_booked'] === 1) {

                throw new Exception(
                    'The requested consultation slot is no longer available.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Load Current Booking
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                SELECT
                    cb.id AS booking_id,
                    cb.slot_id AS old_slot_id,
                    cs.consultation_method
                FROM consultation_bookings cb

                INNER JOIN consultation_slots cs
                    ON cs.id = cb.slot_id

                WHERE cb.request_id = ?

                LIMIT 1
            ");

            $stmt->execute([
                $requestId
            ]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {

                throw new Exception(
                    'Consultation booking not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Generate Meeting Link
            |--------------------------------------------------------------------------
            */

            $meetingLink = getMeetingLink(
                $booking['consultation_method'],
                $newSlot['slot_time']
            );


            /*
            |--------------------------------------------------------------------------
            | Release Old Slot
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                UPDATE consultation_slots

                SET
                    is_booked = 0,
                    consultation_method = NULL,
                    meeting_link = NULL

                WHERE id = ?
            ");

            $stmt->execute([
                $booking['old_slot_id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Book New Slot
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                UPDATE consultation_slots

                SET
                    is_booked = 1,
                    consultation_method = ?,
                    meeting_link = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $booking['consultation_method'],
                $meetingLink,
                $newSlot['id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Consultation Booking
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                UPDATE consultation_bookings

                SET
                    slot_id = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $newSlot['id'],
                $booking['booking_id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Confirm Consultation
            |--------------------------------------------------------------------------
            */

            $stmt = $reviewPdo->prepare("
                UPDATE requests

                SET
                    consultation_reschedules =
                        consultation_reschedules + 1,

                    workflow_stage = 'Consultation Confirmed',

                    job_status = 'Pending',

                    admin_instruction = NULL,

                    pending_reschedule_slot_id = NULL,

                    pending_reschedule_reason = NULL,

                    pending_reschedule_requested_at = NULL,

                    consultation_rejection_reason = NULL,

                    consultation_rejected_at = NULL,

                    consultation_rejected_by = NULL

                WHERE id = ?
            ");

            $stmt->execute([
                $requestId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Audit Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::addCurrentUser(
                $reviewPdo,
                $requestId,
                'CONSULTATION_RESCHEDULE_APPROVED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Reschedule Approved',
                'The administrator approved the customer requested consultation date and time.',
                true
            );


            $reviewPdo->commit();


            $_SESSION['success'] =
                'The consultation reschedule was approved successfully.';


            header(
                'Location: ?page=needs-admin-review'
            );

            exit;


        } catch (Throwable $e) {

    if ($reviewPdo->inTransaction()) {
        $reviewPdo->rollBack();
    }

    die(
        '<pre>' .
        htmlspecialchars($e->getMessage()) .
        "\n\nFile: " .
        htmlspecialchars($e->getFile()) .
        "\nLine: " .
        (int) $e->getLine() .
        '</pre>'
    );
}
    }


    /*
    |--------------------------------------------------------------------------
    | Reject Requested Slot
    |--------------------------------------------------------------------------
    */

    if ($decision === 'reject') {

    $stmt = $reviewPdo->prepare("
    UPDATE consultation_slots
    SET
        is_booked = 0,
        consultation_method = NULL,
        meeting_link = NULL
    WHERE id = ?
");

$stmt->execute([
    $consultation['pending_reschedule_slot_id']
]);

        $stmt = $reviewPdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Awaiting Customer Reschedule',
        job_status = 'Pending',
        status = 'Pending',
        admin_instruction = 'Please choose another available consultation date and time.',
        pending_reschedule_slot_id = NULL,
        pending_reschedule_reason = NULL,
        pending_reschedule_requested_at = NULL
    WHERE id = ?
");

        $stmt->execute([
            $requestId
        ]);


        RequestEventHelper::addCurrentUser(
            $reviewPdo,
            $requestId,
            'CONSULTATION_RESCHEDULE_REJECTED',
            RequestEventHelper::TYPE_CONSULTATION,
            'Consultation Reschedule Rejected',
            'The administrator rejected the requested consultation date and time. The customer may choose another time.',
            true
        );


        $_SESSION['success'] =
            'The requested time was rejected. The customer can choose another time.';


       header(
            'Location: ?page=needs-admin-review'
        );

        exit;
    }
}


require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Reschedule Approval
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $consultation['id'] ?>
            </p>

        </div>

        <span class="badge bg-warning text-dark fs-6">
            Awaiting Reschedule Approval
        </span>

    </div>


    <?php if (!empty($_SESSION['success'])): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($_SESSION['success']) ?>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <?php if (!empty($_SESSION['error'])): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($_SESSION['error']) ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- Customer -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Customer Information
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4">

                    <strong>Name</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['customer_name']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <strong>Email</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['customer_email']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <strong>Agent</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['agent_name'] ?? 'Not assigned'
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Appointment Comparison -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Appointment Change
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <h6 class="text-muted">
                            Current Appointment
                        </h6>

                        <div class="fs-5">

                            <?= htmlspecialchars(
                                $consultation['old_slot_date']
                            ) ?>

                            <br>

                            <?= formatTime(
                                $consultation['old_slot_time']
                            ) ?>

                        </div>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="border border-primary rounded p-3">

                        <h6 class="text-primary">
                            Customer Requested
                        </h6>

                        <div class="fs-5 fw-bold text-primary">

                            <?= formatDate(
                                $consultation['new_slot_date']
                            ) ?>

                            <br>

                            <?= formatTime(
                                $consultation['new_slot_time']
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Reason -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Customer Request
        </div>

        <div class="card-body">

            <?php if (
                !empty($consultation['pending_reschedule_reason'])
            ): ?>

                <?= nl2br(
                    htmlspecialchars(
                        $consultation['pending_reschedule_reason']
                    )
                ) ?>

            <?php else: ?>

                <span class="text-muted">
                    No reason provided.
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!-- Actions -->

    <div class="card shadow-sm">

        <div class="card-header">
            Administrator Decision
        </div>

        <div class="card-body">

            <p class="text-muted">

                The current appointment will remain unchanged until
                the administrator approves the requested new time.

            </p>


            <div class="d-flex gap-2">


                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken) ?>">

                    <input
                        type="hidden"
                        name="decision"
                        value="approve">

                    <button
                        type="submit"
                        class="btn btn-success">

                        ✓ Approve Reschedule

                    </button>

                </form>


                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken) ?>">

                    <input
                        type="hidden"
                        name="decision"
                        value="reject">

                    <button
                        type="submit"
                        class="btn btn-danger">

                        Reject & Let Customer Choose Again

                    </button>

                </form>


                <a
                    href="?page=dashboard"
                    class="btn btn-secondary">

                    Back

                </a>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>