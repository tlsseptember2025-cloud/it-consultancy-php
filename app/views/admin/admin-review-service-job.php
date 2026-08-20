<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid service request.');

}


/*
|--------------------------------------------------------------------------
| Load Service Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        r.id AS request_id,
        r.description,
        r.quoted_price,
        r.workflow_stage,
        r.job_status,
        r.review_type,
        r.incomplete_reason,
        r.admin_review_comments,

        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,

        a.name AS agent_name,

        s.title AS service_name,

        sb.id AS service_booking_id,
        sb.agent_id,

        ss.service_date,
        ss.service_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN service_bookings sb
        ON sb.request_id = r.id

    INNER JOIN service_slots ss
        ON ss.id = sb.slot_id

    LEFT JOIN agents a
        ON a.id = sb.agent_id

    WHERE
        r.id = ?

    LIMIT 1
");

$stmt->execute([
    $requestId
]);

$serviceJob = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$serviceJob) {

    die('Service job not found.');

}


/*
|--------------------------------------------------------------------------
| Make sure this is a Service Review
|--------------------------------------------------------------------------
*/

if (
    $serviceJob['workflow_stage'] !== 'Needs Admin Review'
    || !in_array(
        $serviceJob['review_type'],
        ['service_missed', 'service_overdue'],
        true
    )
) {

    die(
        'This request is not currently awaiting service-job review.'
    );

}

$isServiceMissed =
    $serviceJob['review_type'] === 'service_missed';

$isServiceOverdue =
    $serviceJob['review_type'] === 'service_overdue';


require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">

    <?= $isServiceOverdue
        ? 'Review Overdue Service Job'
        : 'Review Missed Service Job'
    ?>

</h2>

            <p class="text-muted mb-0">

                Request #<?= (int) $serviceJob['request_id'] ?>

            </p>

        </div>


        <div class="text-end">

            <small class="text-muted">
                Current Status
            </small>

            <br>

            <span class="badge bg-warning text-dark fs-6 px-4 py-2">

                Needs Admin Review

            </span>

        </div>

    </div>


    <!-- Customer + Service -->

    <div class="row">

        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header">
                    Customer Information
                </div>

                <div class="card-body">

                    <p>
                        <strong>Name:</strong>
                        <?= htmlspecialchars(
                            $serviceJob['customer_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= htmlspecialchars(
                            $serviceJob['customer_email']
                        ) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Phone:</strong>
                        <?= htmlspecialchars(
                            $serviceJob['customer_phone']
                        ) ?>
                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-6 mb-4">

            <div class="card shadow-sm h-100">

                <div class="card-header">
                    Service Information
                </div>

                <div class="card-body">

                    <p>
                        <strong>Service:</strong>
                        <?= htmlspecialchars(
                            $serviceJob['service_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Quoted Price:</strong>

                        <?php if (
                            $serviceJob['quoted_price'] !== null
                            && (float) $serviceJob['quoted_price'] > 0
                        ): ?>

                            AED
                            <?= number_format(
                                (float) $serviceJob['quoted_price'],
                                2
                            ) ?>

                        <?php else: ?>

                            <span class="text-muted">
                                Pending
                            </span>

                        <?php endif; ?>

                    </p>

                    <p class="mb-0">
                        <strong>Booking #:</strong>
                        <?= (int) $serviceJob['service_booking_id'] ?>
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

                <div class="col-md-4">

                    <strong>Date</strong><br>

                    <?= formatDate(
                        $serviceJob['service_date']
                    ) ?>

                </div>


                <div class="col-md-4">

                    <strong>Time</strong><br>

                    <?= formatTime(
                        $serviceJob['service_time']
                    ) ?>

                </div>


                <div class="col-md-4">

                    <strong>Assigned Agent</strong><br>

                    <?= !empty($serviceJob['agent_name'])

                        ? htmlspecialchars(
                            $serviceJob['agent_name']
                        )

                        : '<span class="text-muted">
                            Not Assigned
                           </span>'
                    ?>

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
                        $serviceJob['description']
                    )
                ) ?>

            </div>

        </div>

    </div>


    <!-- Investigation -->

    <div class="card shadow-sm border-danger mb-4">

        <div class="card-header bg-danger text-white">

            Service Job Investigation

        </div>

        <div class="card-body">

            <div class="alert alert-warning">

                <?php if ($isServiceMissed): ?>

    <div class="alert alert-warning">

        <strong>
            Service Start Window Expired
        </strong>

        <br>

        The scheduled one-hour window expired
        before the service was started.

    </div>

<?php elseif ($isServiceOverdue): ?>

    <div class="alert alert-warning">

        <strong>
            Service Session Overdue
        </strong>

        <br>

        The service was started by the assigned agent,
        but it remained In Progress after the scheduled
        one-hour service session ended.

    </div>

<?php endif; ?>

            </div>


            <h5 class="mb-3">
                Agent Explanation
            </h5>


            <?php if (
                !empty($serviceJob['incomplete_reason'])
            ): ?>

                <div class="border rounded bg-light p-3">

                    <?= nl2br(
                        htmlspecialchars(
                            $serviceJob['incomplete_reason']
                        )
                    ) ?>

                </div>

            <?php else: ?>

                <div class="text-muted">

                    No agent explanation was provided.

                </div>

            <?php endif; ?>


            <div class="row mt-4">

                <div class="col-md-4">

                    <strong>Agent</strong><br>

                    <?= !empty($serviceJob['agent_name'])

                        ? htmlspecialchars(
                            $serviceJob['agent_name']
                        )

                        : 'Not Assigned'
                    ?>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Date</strong><br>

                    <?= formatDate(
                        $serviceJob['service_date']
                    ) ?>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Time</strong><br>

                    <?= formatTime(
                        $serviceJob['service_time']
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Administrator Review Notes -->

    <?php if (
        !empty($serviceJob['admin_review_comments'])
    ): ?>

        <div class="card shadow-sm border-warning mb-4">

            <div class="card-header bg-warning">

                Administrator Review

            </div>

            <div class="card-body">

                <div class="border rounded bg-light p-3">

                    <?= nl2br(
                        htmlspecialchars(
                            $serviceJob['admin_review_comments']
                        )
                    ) ?>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- Decision Area -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            Administrator Decision

        </div>

        <div class="card-body">

            <p class="text-muted">

                This service job requires an administrator's
                decision before it can continue.

            </p>


            <div class="alert alert-info mb-0">

                <strong>Service-specific review</strong>

                <br>

                The available service decisions will be handled
                separately from the consultation workflow.

            </div>

        </div>

    </div>


    <!-- Back -->

    <div class="d-flex justify-content-between">

        <a
            href="?page=needs-admin-review"
            class="btn btn-secondary">

            ← Back to Needs Admin Review

        </a>

    </div>

</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>