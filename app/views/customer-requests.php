

<?php require __DIR__ . '/layouts/header.php'; ?>

<?php

require_once __DIR__ . '/../helpers/auth.php';

requireCustomerLogin();

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
                            <?= htmlspecialchars($request['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['workflow_stage']) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($request['created_at'])) ?>
                        </td>

                        <td>


    <?php if ($request['workflow_stage'] === 'Consultation Approved'): ?>

        <a
            href="?page=schedule-consultation&request_id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">

            Schedule Consultation

        </a>

   <?php elseif ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

    <div>

        <span class="badge bg-warning text-dark">

            <?= date('M d, Y', strtotime($request['slot_date'])) ?>

            @

            <?= date('h:i A', strtotime($request['slot_time'])) ?>

            @

            <?= htmlspecialchars($request['consultation_method']) ?>

        </span>

        <br>

        <small class="text-muted">
            Waiting for admin confirmation.
        </small>

    </div>

<?php elseif ($request['workflow_stage'] === 'Consultation Confirmed'): ?>

    <?php

    $minute = date('i', strtotime($request['slot_time']));

    $meetingLink = ($minute === '00')
        ? ZOOM_LINK_HOUR
        : ZOOM_LINK_HALF;

    ?>

    <span class="badge bg-danger">

        <?= date('M d, Y', strtotime($request['slot_date'])) ?>

        @

        <?= date('h:i A', strtotime($request['slot_time'])) ?>

        @

        <?= htmlspecialchars($request['consultation_method']) ?>

    </span>

    <a
        href="<?= htmlspecialchars($meetingLink) ?>"
        target="_blank"
        class="btn btn-success btn-sm ms-2">

        Join Zoom Meeting

    </a>

    <a
        href="?page=reschedule-consultation&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm ms-2">

        Reschedule

    </a>

<?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Proposal Sent'): ?>

        <a
            href="?page=view-proposal&request_id=<?= $request['id'] ?>"
            class="btn btn-primary btn-sm">

            View Proposal

        </a>

    <?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Proposal Accepted'): ?>

    <a
        href="?page=customer-upload-slip&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm">

        Upload Deposit Slip

    </a>

    <?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Payment Submitted'): ?>

    <span class="badge bg-info">
        Payment Under Review
    </span>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Consultation Completed'): ?>

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

    <a
        href="?page=reschedule-service&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm ms-2">

        Reschedule Service

    </a>

</div>

<?php elseif (
    $request['workflow_stage'] === 'Service Active' ||
    $request['workflow_stage'] === 'Completed'
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

    </div>

<?php endif; ?>


</td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>