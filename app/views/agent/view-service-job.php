<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$agentId = (int) $_SESSION['agent']['id'];

$bookingId = (int) ($_GET['id'] ?? 0);

if ($bookingId <= 0) {

    die('Invalid service booking.');

}


/*
|--------------------------------------------------------------------------
| Load the exact service booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        sb.id AS service_booking_id,

        r.id AS request_id,
        r.description,
        r.quoted_price,
        r.workflow_stage,
        r.job_status,
        r.completed_at,
        r.completion_notes,
        r.incomplete_reason,

        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,

        s.title AS service_name,

        ss.service_date,
        ss.service_time

    FROM service_bookings sb

    INNER JOIN requests r
        ON r.id = sb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE
        sb.id = ?
        AND sb.agent_id = ?

    LIMIT 1
");

$stmt->execute([
    $bookingId,
    $agentId
]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {

    die('Service job not found.');

}



/*
|--------------------------------------------------------------------------
| Start Service
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['start_service'])
) {

    /*
    |--------------------------------------------------------------------------
    | Only Pending service jobs can be started
    |--------------------------------------------------------------------------
    */

    if ($job['job_status'] !== 'Pending') {

        die(
            'This service job cannot be started from its current status.'
        );

    }


    $serviceStart = new DateTimeImmutable(
    $job['service_date'] . ' ' . $job['service_time'],
    new DateTimeZone('Asia/Dubai')
);

$serviceEnd = $serviceStart->modify('+1 hour');

$now = new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Dubai')
);


/*
|--------------------------------------------------------------------------
| Too early
|--------------------------------------------------------------------------
*/

$serviceAvailableAt = $serviceStart->modify('-10 minutes');

if ($now < $serviceAvailableAt) {

    die(
        'The Start Service button will be available 10 minutes before the scheduled service time.'
    );

}


/*
|--------------------------------------------------------------------------
| Start window expired
|--------------------------------------------------------------------------
*/

if ($now >= $serviceEnd) {

    die(
        'The one-hour service start window has expired.'
    );

}


    /*
    |--------------------------------------------------------------------------
    | Start Service
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests

        SET
            job_status = 'In Progress'

        WHERE
            id = ?

            AND job_status = 'Pending'
    ");

    $update->execute([
        $job['request_id']
    ]);


    header(
        'Location: ?page=view-service-job&id='
        . (int) $job['service_booking_id']
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Status badge
|--------------------------------------------------------------------------
*/

$status = $job['job_status'] ?? 'Pending';

$badge = 'secondary';

switch ($status) {

    case 'Pending':
        $badge = 'warning';
        break;

    case 'In Progress':
        $badge = 'primary';
        break;

    case 'Completed':
        $badge = 'success';
        break;

    case 'Could Not Complete':
        $badge = 'danger';
        break;

}

$serviceTimeStatus = 'not_applicable';

if ($status === 'Pending') {

    $serviceStart = new DateTimeImmutable(
        $job['service_date'] . ' ' . $job['service_time'],
        new DateTimeZone('Asia/Dubai')
    );

    $serviceEnd = $serviceStart->modify('+1 hour');

    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Dubai')
    );

    $serviceAvailableAt = $serviceStart->modify('-10 minutes');

if ($now < $serviceAvailableAt) {

    $serviceTimeStatus = 'future';

} elseif ($now < $serviceEnd) {

    $serviceTimeStatus = 'active';

} else {

    $serviceTimeStatus = 'expired';

}
}


/*
|--------------------------------------------------------------------------
| Agent page
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">
                Service Job Details
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $job['request_id'] ?>
            </p>

        </div>

        <div class="text-end">

            <span class="badge bg-<?= $badge ?> fs-5 px-4 py-2">

                <?= htmlspecialchars($status) ?>

            </span>

            <?php if (
                $status === 'Completed'
                || $status === 'Could Not Complete'
            ): ?>

                <?php if (!empty($job['completed_at'])): ?>

                    <small class="text-muted d-block mt-2">

                        Completed on<br>

                        <?= date(
                            'd M Y h:i A',
                            strtotime($job['completed_at'])
                        ) ?>

                    </small>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>


    <!-- Customer + Service -->

    <div class="row">

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">
                    Customer Information
                </div>

                <div class="card-body">

                    <p>
                        <strong>Name:</strong>
                        <?= htmlspecialchars(
                            $job['customer_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= htmlspecialchars(
                            $job['customer_email']
                        ) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Phone:</strong>
                        <?= htmlspecialchars(
                            $job['customer_phone']
                        ) ?>
                    </p>

                </div>

            </div>

        </div>


        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">
                    Service Information
                </div>

                <div class="card-body">

                    <p>
                        <strong>Service:</strong>
                        <?= htmlspecialchars(
                            $job['service_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Quoted Price:</strong>

                        AED
                        <?= number_format(
                            (float) $job['quoted_price'],
                            2
                        ) ?>

                    </p>

                    <p class="mb-0">
                        <strong>Booking #:</strong>
                        <?= (int) $job['service_booking_id'] ?>
                    </p>

                </div>

            </div>

        </div>

    </div>


    <!-- Service Schedule -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Service Schedule
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <strong>Date</strong><br>

                    <?= formatDate(
                        $job['service_date']
                    ) ?>

                </div>

                <div class="col-md-6">

                    <strong>Time</strong><br>

                    <?= formatTime(
                        $job['service_time']
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Customer Request -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Customer Request
        </div>

        <div class="card-body">

            <div class="border rounded bg-light p-3">

                <?= nl2br(
                    htmlspecialchars(
                        $job['description']
                    )
                ) ?>

            </div>

        </div>

    </div>


    <!-- Workflow -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Workflow Information
        </div>

        <div class="card-body">

            <p>
                <strong>Workflow Stage:</strong>

                <?= htmlspecialchars(
                    $job['workflow_stage']
                ) ?>

            </p>

            <p class="mb-0">

                <strong>Job Status:</strong>

                <span class="badge bg-<?= $badge ?>">

                    <?= htmlspecialchars(
                        $status
                    ) ?>

                </span>

            </p>

        </div>

    </div>

    <?php if (
    $status === 'Pending'
    && $serviceTimeStatus === 'future'
): ?>

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Service Action
        </div>

        <div class="card-body">

            <p class="text-muted mb-3">
                The Start Service button will be available
                10 minutes before the scheduled service time.
            </p>

            <button
                type="button"
                class="btn btn-secondary"
                disabled>

                ▶ Start Service

            </button>

        </div>

    </div>

<?php endif; ?>

<?php if (
    $status === 'Pending'
    && $serviceTimeStatus === 'active'
): ?>

    <div class="card shadow-sm mb-4 border-primary">

        <div class="card-header bg-primary text-white">
            Service Action
        </div>

        <div class="card-body">

            <p class="mb-3">
                This service job is ready to be started
                at the scheduled service time.
            </p>

            <form method="POST">

                <button
                    type="submit"
                    name="start_service"
                    class="btn btn-primary">

                    ▶ Start Service

                </button>

            </form>

        </div>

    </div>

<?php endif; ?>

<?php if (
    $status === 'Pending'
    && $serviceTimeStatus === 'expired'
): ?>

    <div class="card shadow-sm mb-4 border-danger">

        <div class="card-header bg-danger text-white">
            Service Start Window Expired
        </div>

        <div class="card-body">

            <p class="mb-0">
                The one-hour window to start this service has expired.
                The service will be handled by the missed-service workflow
                and may require administrator review.
            </p>

        </div>

    </div>

<?php endif; ?>


    <?php if ($status === 'Completed'): ?>

        <div class="card shadow-sm mb-4 border-success">

            <div class="card-header bg-success text-white">
                Service Completed
            </div>

            <div class="card-body">

                <p>
                    The service was completed by the assigned agent.
                </p>

                <strong>Completion Notes</strong>

                <div class="border rounded bg-light p-3 mt-2">

                    <?= !empty($job['completion_notes'])

                        ? nl2br(
                            htmlspecialchars(
                                $job['completion_notes']
                            )
                        )

                        : '<span class="text-muted">
                            No completion notes provided.
                           </span>'
                    ?>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <?php if ($status === 'Could Not Complete'): ?>

        <div class="card shadow-sm mb-4 border-danger">

            <div class="card-header bg-danger text-white">
                Service Could Not Be Completed
            </div>

            <div class="card-body">

                <strong>Reason</strong>

                <div class="border rounded bg-light p-3 mt-2">

                    <?= !empty($job['incomplete_reason'])

                        ? nl2br(
                            htmlspecialchars(
                                $job['incomplete_reason']
                            )
                        )

                        : '<span class="text-muted">
                            No reason provided.
                           </span>'
                    ?>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- Back -->

    <div class="mt-4">

        <a
            href="?page=agent-jobs"
            class="btn btn-secondary">

            ← Back to My Service Jobs

        </a>

    </div>

</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>