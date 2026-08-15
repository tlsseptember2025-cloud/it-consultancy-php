<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/meeting.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Pending Reschedule
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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

    LIMIT 1
");

$stmt->execute([$requestId]);

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

    $decision = $_POST['decision'];


    /*
    |--------------------------------------------------------------------------
    | Approve Reschedule
    |--------------------------------------------------------------------------
    */

    if ($decision === 'approve') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Requested Slot
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
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
                $pdo,
                $requestId,
                'CONSULTATION_RESCHEDULE_APPROVED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Reschedule Approved',
                'The administrator approved the customer requested consultation date and time.',
                true
            );


            $pdo->commit();


            $_SESSION['success'] =
                'The consultation reschedule was approved successfully.';


            header(
                'Location: ?page=review-reschedule-consultation&id='
                . $requestId
            );

            exit;


        } catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
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

        $stmt = $pdo->prepare("
            UPDATE requests

            SET
                workflow_stage = 'Consultation Confirmed',

                admin_instruction = '__RESCHEDULE_ALLOWED__',

                pending_reschedule_slot_id = NULL,

                pending_reschedule_reason = NULL,

                pending_reschedule_requested_at = NULL

            WHERE id = ?
        ");

        $stmt->execute([
            $requestId
        ]);


        RequestEventHelper::addCurrentUser(
            $pdo,
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
            'Location: ?page=review-reschedule-consultation&id='
            . $requestId
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

                            <?= htmlspecialchars(
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

                            <?= htmlspecialchars(
                                $consultation['new_slot_date']
                            ) ?>

                            <br>

                            <?= htmlspecialchars(
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