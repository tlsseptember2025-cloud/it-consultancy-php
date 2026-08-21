<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;

}

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = (int) ($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Load Pending Service Reschedule
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        r.*,

        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,

        s.title AS service_name,

        a.name AS agent_name,

        sb.slot_id AS current_slot_id,

        current_ss.service_date AS current_service_date,
        current_ss.service_time AS current_service_time,

        pending_ss.service_date AS pending_service_date,
        pending_ss.service_time AS pending_service_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots current_ss
        ON current_ss.id = sb.slot_id

    LEFT JOIN service_slots pending_ss
        ON pending_ss.id = r.pending_reschedule_slot_id

    WHERE
        r.id = ?
        AND r.workflow_stage = 'Awaiting Reschedule Approval'
        AND r.pending_reschedule_slot_id IS NOT NULL

    LIMIT 1
");

$stmt->execute([$requestId]);

$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {

    die('Pending service reschedule not found.');

}


/*
|--------------------------------------------------------------------------
| Approve Service Reschedule
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['approve_reschedule'])
) {

    try {

        $pdo->beginTransaction();


        /*
        |------------------------------------------------------------------
        | Check Pending Slot Again
        |------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT is_booked
            FROM service_slots
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $service['pending_reschedule_slot_id']
        ]);

        $pendingSlotBooked = $stmt->fetchColumn();


        if ($pendingSlotBooked === false) {

            throw new Exception(
                'The requested service slot no longer exists.'
            );

        }


        if ((int) $pendingSlotBooked === 1) {

            throw new Exception(
                'The requested service slot is no longer available.'
            );

        }


        /*
        |------------------------------------------------------------------
        | Release Current Slot
        |------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE service_slots
            SET is_booked = 0
            WHERE id = ?
        ");

        $stmt->execute([
            $service['current_slot_id']
        ]);


        /*
        |------------------------------------------------------------------
        | Book Approved New Slot
        |------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE service_slots
            SET is_booked = 1
            WHERE id = ?
        ");

        $stmt->execute([
            $service['pending_reschedule_slot_id']
        ]);


        /*
        |------------------------------------------------------------------
        | Update Service Booking
        |------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE service_bookings
            SET slot_id = ?
            WHERE request_id = ?
        ");

        $stmt->execute([
            $service['pending_reschedule_slot_id'],
            $service['id']
        ]);


        /*
        |------------------------------------------------------------------
        | Update Request Workflow
        |------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE requests
            SET
                service_reschedules = service_reschedules + 1,

                workflow_stage = 'Service Scheduled',

                job_status = 'Pending',

                pending_reschedule_slot_id = NULL,
                pending_reschedule_reason = NULL,
                pending_reschedule_requested_at = NULL,

                service_rejection_reason = NULL,
                service_rejected_at = NULL,
                service_rejected_by = NULL

            WHERE id = ?
        ");

        $stmt->execute([
            $service['id']
        ]);


        /*
        |------------------------------------------------------------------
        | Record Approval Event
        |------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            (int) $service['id'],
            'SERVICE_RESCHEDULE_APPROVED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Reschedule Approved',
            'The administrator approved the customer requested service reschedule.',
            false
        );


        $pdo->commit();


        $_SESSION['success'] =
            'The service reschedule request has been approved successfully.';


        header(
            'Location: ?page=needs-admin-review'
        );

        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        $_SESSION['error'] = $e->getMessage();

        header(
            'Location: ?page=review-reschedule-service&id=' .
            $service['id']
        );

        exit;

    }

}

/*
|--------------------------------------------------------------------------
| Reject Service Reschedule
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['reject_reschedule'])
) {

    $rejectionReason = trim(
        $_POST['rejection_reason'] ?? ''
    );

    if ($rejectionReason === '') {

        $_SESSION['error'] =
            'Please provide a reason for rejecting the service reschedule request.';

        header(
            'Location: ?page=review-reschedule-service&id=' .
            $service['id']
        );

        exit;
    }

    try {

        $pdo->beginTransaction();


        /*
        |------------------------------------------------------------------
        | Restore Request To Customer Reschedule
        |------------------------------------------------------------------
        |
        | IMPORTANT:
        | The current service booking remains unchanged.
        | The customer's pending requested slot is simply removed.
        |
        */

        $stmt = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Service Rejected',

                job_status = 'Pending',

                pending_reschedule_slot_id = NULL,
                pending_reschedule_reason = NULL,
                pending_reschedule_requested_at = NULL,

                service_rejection_reason = ?,
                service_rejected_at = NOW(),
                service_rejected_by = ?

            WHERE id = ?
        ");

        $stmt->execute([
    $rejectionReason,
    (int) $_SESSION['user'],
    $service['id']
]);


        /*
        |------------------------------------------------------------------
        | Record Rejection Event
        |------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            (int) $service['id'],
            'SERVICE_RESCHEDULE_REJECTED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Reschedule Rejected',
            'The administrator rejected the customer requested service reschedule. Reason: ' .
                $rejectionReason,
            false
        );


        $pdo->commit();


        $_SESSION['success'] =
            'The service reschedule request has been rejected. The customer can choose another available appointment.';


        header(
            'Location: ?page=needs-admin-review'
        );

        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        $_SESSION['error'] = $e->getMessage();

        header(
            'Location: ?page=review-reschedule-service&id=' .
            $service['id']
        );

        exit;

    }

}


require VIEW_PATH . '/layouts/header-admin.php';

?>


<div class="container py-4">


    <!-- Header -->

    <div class="row mb-4">

        <div class="col-md-8">

            <h2>

                Review Service Reschedule

            </h2>

            <p class="text-muted mb-0">

                Request #<?= (int) $service['id'] ?>

            </p>

        </div>

    </div>


    <!-- Customer + Service Information -->

    <div class="row mb-4">


        <div class="col-md-6">

            <div class="card shadow-sm h-100">

                <div class="card-header">

                    Customer Information

                </div>

                <div class="card-body">

                    <p>

                        <strong>Name:</strong>

                        <?= htmlspecialchars($service['customer_name']) ?>

                    </p>

                    <p>

                        <strong>Email:</strong>

                        <?= htmlspecialchars($service['customer_email']) ?>

                    </p>

                    <p class="mb-0">

                        <strong>Phone:</strong>

                        <?= htmlspecialchars($service['customer_phone']) ?>

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-6">

            <div class="card shadow-sm h-100">

                <div class="card-header">

                    Service Information

                </div>

                <div class="card-body">

                    <p>

                        <strong>Service:</strong>

                        <?= htmlspecialchars($service['service_name']) ?>

                    </p>

                    <p>

                        <strong>Assigned Agent:</strong>

                        <?= !empty($service['agent_name'])
                            ? htmlspecialchars($service['agent_name'])
                            : 'Not Assigned' ?>

                    </p>

                    <p class="mb-0">

                        <strong>Quoted Price:</strong>

                        <?php if ((float) $service['quoted_price'] > 0): ?>

                            AED <?= number_format(
                                (float) $service['quoted_price'],
                                2
                            ) ?>

                        <?php else: ?>

                            <span class="text-muted">

                                Pending

                            </span>

                        <?php endif; ?>

                    </p>

                </div>

            </div>

        </div>


    </div>


    <!-- Current Service Appointment -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-secondary text-white">

            Current Service Appointment

        </div>

        <div class="card-body bg-light">

            <div class="row">


                <div class="col-md-6">

                    <strong>Date</strong><br>

                    <?= formatDate($service['current_service_date']) ?>

                </div>


                <div class="col-md-6">

                    <strong>Time</strong><br>

                    <?= formatTime($service['current_service_time']) ?>

                </div>


            </div>

        </div>

    </div>


    <!-- Requested New Appointment -->

    <div class="card shadow-sm mb-4 border-primary">

        <div class="card-header bg-primary text-white">

            Customer Requested New Service Appointment

        </div>

        <div class="card-body">

            <div class="row">


                <div class="col-md-6">

                    <strong>Requested Date</strong><br>

                    <?= formatDate($service['pending_service_date']) ?>

                </div>


                <div class="col-md-6">

                    <strong>Requested Time</strong><br>

                    <?= formatTime($service['pending_service_time']) ?>

                </div>


            </div>

        </div>

    </div>


    <!-- Requested At -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <strong>Customer Submitted Request:</strong>

            <?= !empty($service['pending_reschedule_requested_at'])
                ? date(
                    'M d, Y h:i A',
                    strtotime($service['pending_reschedule_requested_at'])
                )
                : 'Unknown' ?>

        </div>

    </div>

<!-- Admin Decision -->

<div class="card shadow-sm border-warning">

    <div class="card-header bg-warning">

        Administrator Decision

    </div>

    <div class="card-body">

        <p class="mb-4">

            Review the customer's requested service appointment.
            Approve the request if the requested appointment is acceptable,
            or reject it and allow the customer to choose another available
            service appointment.

        </p>


        <form method="POST">

            <div class="row">


                <!-- Reject Reschedule -->

                <div class="col-md-6 mb-3">

                    <div class="card border-danger h-100">

                        <div class="card-header bg-danger text-white">

                            Reject Reschedule

                        </div>

                        <div class="card-body d-flex flex-column">

                            <div class="mb-3">

                                <label
                                    for="rejection_reason"
                                    class="form-label">

                                    Rejection Reason

                                </label>

                                <textarea
                                    id="rejection_reason"
                                    name="rejection_reason"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Explain why this requested service appointment cannot be approved."></textarea>

                            </div>


                            <div class="mt-auto">

                                <button
                                    type="submit"
                                    name="reject_reschedule"
                                    value="1"
                                    class="btn btn-danger"
                                    onclick="return confirm('Reject this service reschedule request? The customer will be asked to choose another appointment.');">

                                    Reject Reschedule

                                </button>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Approve Reschedule -->

                <div class="col-md-6 mb-3">

                    <div class="card border-success h-100">

                        <div class="card-header bg-success text-white">

                            Approve Reschedule

                        </div>

                        <div class="card-body d-flex flex-column">

                            <p class="text-muted">

                                Approving this request will release the current
                                service appointment and book the customer's
                                requested appointment.

                            </p>


                            <div class="mt-auto">

                                <button
                                    type="submit"
                                    name="approve_reschedule"
                                    value="1"
                                    class="btn btn-success"
                                    onclick="return confirm('Approve this service reschedule request?');">

                                    Approve Reschedule

                                </button>

                            </div>

                        </div>

                    </div>

                </div>


            </div>

        </form>


        <!-- Cancel -->

        <div class="mt-3">

            <a
                href="?page=dashboard"
                class="btn btn-secondary">

                Cancel

            </a>

        </div>

    </div>

</div>


<!-- Back to Dashboard -->

<div class="mt-4">

    <a
        href="?page=dashboard"
        class="btn btn-secondary">

        Back to Dashboard

    </a>

</div>


</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>