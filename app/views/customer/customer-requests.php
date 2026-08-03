<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/meeting.php';
require dirname(__DIR__) . '/layouts/header-customer.php';
require_once HELPER_PATH . '/auth.php';

$customerId = (int) $_SESSION['customer']['id'];

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        r.*,

        s.title AS service_title,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        ss.service_date,
        ss.service_time

    FROM requests r

    JOIN services s
        ON r.service_id = s.id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE r.customer_id = ?

    ORDER BY r.id DESC
");

$stmt->execute([$customerId]);

$requests = $stmt->fetchAll();

?>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>


<h1 class="mb-4">My Requests</h1>

<?php if (!empty($_SESSION['error'])): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars($_SESSION['error']) ?>

    </div>

    <?php unset($_SESSION['error']); ?>

<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>

    <div class="alert alert-success">

        <?= htmlspecialchars($_SESSION['success']) ?>

    </div>

    <?php unset($_SESSION['success']); ?>

<?php endif; ?>

<div class="mb-3">

    <a
        href="?page=customer-request-service"
        class="btn btn-primary">

        ➕ Request New Service

    </a>

</div>

<div class="card">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th>Service</th>
                    <th>Quoted Price</th>
                    <th>Status</th>
                    <th>Workflow Stage</th>
                    <th>Date</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($requests as $request): ?>

                    <?php

                        $refundEligible = false;

                        if (
                            !empty($request['service_date']) &&
                            !empty($request['service_time'])
                        ) {

                            $serviceDateTime = strtotime(
                                $request['service_date'] . ' ' .
                                $request['service_time']
                            );

                            $refundEligible =
                                time() <= ($serviceDateTime - (48 * 60 * 60));

                        }

                    ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($request['service_title']) ?>
                        </td>

                        <td>

                            <?php if ($request['quoted_price'] > 0): ?>

                                AED <?= number_format($request['quoted_price'], 2) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Awaiting Quote
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($request['job_status']) ?>
                        </td>

                        <td>

<?php

if (

    $request['workflow_stage'] === 'Needs Admin Review'
    &&
    (
        $request['admin_instruction'] === '__RESCHEDULE_ALLOWED__'
        ||
        (
            $request['admin_instruction'] !== null
            &&
            trim($request['admin_instruction']) !== ''
        )
    )

) {

    echo '<span class="badge bg-warning text-dark">Ready to Reschedule</span>';

} else {

    echo htmlspecialchars($request['workflow_stage']);

}

?>

</td>

                        <td>
                           <?= formatDateTime($request['created_at']) ?>
                        </td>

    <td>


    <?php if ($request['workflow_stage'] === 'Submitted'): ?>

    <?php if (!empty($request['agent_id'])): ?>

        <a
            href="?page=schedule-consultation&request_id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">

            Schedule Consultation

        </a>

    <?php else: ?>

        <span class="badge bg-secondary">

            Waiting for Agent Assignment

        </span>

    <?php endif; ?>

   <?php elseif ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

    <div>

        <span class="badge bg-warning text-dark">

            <?= date('M d, Y', strtotime($request['slot_date'])) ?>

            @

            <?= formatTime($request['slot_time']) ?>

            @

            <?= htmlspecialchars($request['consultation_method']) ?>

        </span>

        <br>

        <small class="text-muted">
            Waiting for admin confirmation.
        </small>

    </div>

<?php elseif (

    $request['workflow_stage'] === 'Consultation Confirmed'

): ?>

    <?php

   $meetingLink = getMeetingLink(
    $request['consultation_method'],
    $request['slot_time']
    );

    ?>

    <span class="badge bg-danger">

        <?= date('M d, Y', strtotime($request['slot_date'])) ?>

        @

        <?= formatTime($request['slot_time']) ?>

        @

        <?= htmlspecialchars($request['consultation_method']) ?>

    </span>

    <a
        href="<?= htmlspecialchars($meetingLink) ?>"
        target="_blank"
        class="btn btn-success btn-sm ms-2">

        Join <?= htmlspecialchars($request['consultation_method']) ?>

    </a>

    <?php if ((int)$request['consultation_reschedules'] < 1): ?>

<a
    href="?page=reschedule-consultation&request_id=<?= $request['id'] ?>"
    class="btn btn-warning btn-sm ms-2">

    Reschedule

</a>

<?php endif; 

elseif (

    $request['workflow_stage'] === 'Needs Admin Review'
    &&
    (
        $request['admin_instruction'] === '__RESCHEDULE_ALLOWED__'
        ||
        (
            $request['admin_instruction'] !== null
            &&
            trim($request['admin_instruction']) !== ''
        )
    )

): ?>

    <?php if (empty($request['admin_instruction'])): ?>

    <span class="badge bg-warning text-dark">

        Awaiting Administrator Review

    </span>

<?php else: ?>

    <a
        href="?page=reschedule-consultation&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm">

        Reschedule Consultation

    </a>

<?php endif; ?>


<?php elseif ($request['workflow_stage'] === 'Consultation Rejected'): ?>

    <span class="badge bg-danger">

        Consultation Rejected

    </span>

    <div class="small text-danger mt-2">

        <?= htmlspecialchars($request['consultation_rejection_reason']) ?>

    </div>

    <a
        href="?page=reschedule-consultation&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm mt-2">

        <i class="bi bi-calendar-event"></i>

        Reschedule Consultation

    </a>

<?php endif; ?>

    <?php if (
    $request['workflow_stage'] === 'Proposal Sent' ||
    $request['workflow_stage'] === 'Proposal Viewed'
): ?>

    <a
        href="?page=view-proposal&request_id=<?= $request['id'] ?>"
        class="btn btn-primary btn-sm">

        View Proposal

    </a>

<?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Awaiting Payment'): ?>

    <a
        href="?page=customer-upload-slip&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm">

        Upload Payment Receipt

    </a>

<?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Payment Submitted'): ?>

    <span class="badge bg-info">
        Payment Under Review
    </span>

    <?php endif; ?>


   <?php if (
    $request['workflow_stage'] === 'Consultation Completed' ||
    $request['workflow_stage'] === 'Proposal Draft'
): ?>

    <span class="badge bg-secondary">

        Awaiting Proposal

    </span>


   <?php elseif ($request['workflow_stage'] === 'Proposal Rejected'): ?>

    <span class="badge bg-warning">

        Awaiting Revised Proposal

    </span>

    <?php elseif ($request['workflow_stage'] === 'Awaiting Service Scheduling'): ?>

    <a
        href="?page=schedule-service&request_id=<?= $request['id'] ?>"
        class="btn btn-success btn-sm">

        Book Service

    </a>

<?php endif; ?>


<?php if (
    $request['workflow_stage'] === 'Service Scheduled'
): ?>

    <small>
    <?= date('M d, Y', strtotime($request['service_date'])) ?>
    <br>
    <?= date('h:i A', strtotime($request['service_time'])) ?>
</small>

<div class="mt-2">

    <a
    href="?page=customer-request-refund&request_id=<?= $request['id'] ?>"
    class="btn btn-outline-danger btn-sm">

    Request Refund

</a>

<?php if ((int)$request['service_reschedules'] === 0): ?>

    <a
        href="?page=reschedule-service&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm ms-2">

        Reschedule Service

    </a>

<?php else: ?>

    <span class="badge bg-secondary ms-2">
        Reschedule Used
    </span>

<?php endif; ?>

</div>

<?php elseif (
    $request['workflow_stage'] === 'Service Rejected'
): ?>

    <div class="alert alert-danger mb-2">

        <strong>Service Rejected</strong>

        <hr>

        <?= nl2br(htmlspecialchars($request['service_rejection_reason'])) ?>

    </div>

    <a
        href="?page=reschedule-service&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm">

        Reschedule Service

    </a>

<?php elseif (
    $request['workflow_stage'] === 'Service Active' ||
    $request['workflow_stage'] === 'Completed'
): ?>

    <div class="alert alert-success mb-2">
    <strong>Service In Progress</strong><br>
    Your service is currently being performed.
    </div>

    <small>
        <?= date('M d, Y', strtotime($request['service_date'])) ?>
        <br>
        <?= date('h:i A', strtotime($request['service_time'])) ?>
    </small>

    <div class="mt-2">

        <a
            href="?page=customer-request-refund&request_id=<?= $request['id'] ?>"
            class="btn btn-outline-danger btn-sm">

            Request Refund

        </a>

    </div>

<?php endif; ?>


</td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>