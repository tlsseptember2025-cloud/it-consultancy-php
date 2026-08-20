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


/*
|--------------------------------------------------------------------------
| Handle Service Review Decision
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_service_review'])
) {

    $decision = $_POST['admin_decision'] ?? '';

    $comments = trim(
        $_POST['admin_review_comments'] ?? ''
    );


    if ($decision === '') {

        die('Please select an administrator decision.');

    }


    if ($comments === '') {

        die('Administrator comments are required.');

    }


    /*
    |--------------------------------------------------------------------------
    | Reject Explanation
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| Reject Explanation
|--------------------------------------------------------------------------
*/

if ($decision === 'reject') {

    /*
    |--------------------------------------------------------------------------
    | Save Admin Rejection to Review History
    |--------------------------------------------------------------------------
    */

    $history = $pdo->prepare("
        INSERT INTO service_review_history
(
    request_id,
    actor_type,
    admin_id,
    action_type,
    decision_type,
    message
)
VALUES
(
    ?,
    'admin',
    ?,
    'admin_rejection',
    'reject',
    ?
)
    ");

    $history->execute([
    $serviceJob['request_id'],
    (int) $_SESSION['user'],
    $comments
]);


    /*
    |--------------------------------------------------------------------------
    | Send Service Back to Agent
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests

        SET
            workflow_stage = 'Service Explanation Required',
            job_status = 'Needs Admin Review',
            review_type = 'service_overdue',
            admin_review_comments = ?

        WHERE
            id = ?

            AND workflow_stage = 'Needs Admin Review'

            AND review_type = 'service_overdue'
    ");

    $update->execute([
        $comments,
        $serviceJob['request_id']
    ]);


    if ($update->rowCount() !== 1) {

        die(
            'The service review could not be returned to the agent.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Audit Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $pdo,
        (int) $serviceJob['request_id'],
        'SERVICE_EXPLANATION_REJECTED',
        RequestEventHelper::TYPE_SERVICE,
        'Service Explanation Rejected',
        'The administrator rejected the agent explanation and requested a new explanation. Administrator reason: ' . $comments,
        true
    );


    /*
    |--------------------------------------------------------------------------
    | Return to Admin Review List
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ?page=needs-admin-review&success=service-explanation-rejected'
    );

    exit;
}

}

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

    <?php

/*
|--------------------------------------------------------------------------
| Load Service Review History
|--------------------------------------------------------------------------
*/

$historyStmt = $pdo->prepare("
    SELECT
        h.*,
        a.name AS agent_name,
        ad.email AS admin_email

    FROM service_review_history h

    LEFT JOIN agents a
        ON a.id = h.agent_id

    LEFT JOIN users ad
        ON ad.id = h.admin_id

    WHERE
        h.request_id = ?

    ORDER BY
        h.created_at ASC,
        h.id ASC
");

$historyStmt->execute([
    $serviceJob['request_id']
]);

$reviewHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

?>


<div class="card shadow-sm mb-4">

    <div class="card-header bg-dark text-white">

        Service Review History

    </div>

    <div class="card-body">

        <?php if (empty($reviewHistory)): ?>

            <div class="text-muted">

                No review history has been recorded yet.

            </div>

        <?php else: ?>

            <div class="timeline">

                <?php foreach ($reviewHistory as $entry): ?>

                    <?php

                        $actorName = 'System';

                        if ($entry['actor_type'] === 'agent') {

                            $actorName =
                                $entry['agent_name']
                                ?: 'Agent';

                            $badgeClass = 'bg-primary';

                            $actorLabel = 'Agent';

                        } elseif ($entry['actor_type'] === 'admin') {

                            $actorName =
                                $entry['admin_email']
                                ?: 'Administrator';

                            $badgeClass = 'bg-danger';

                            $actorLabel = 'Administrator';

                        } else {

                            $badgeClass = 'bg-secondary';

                            $actorLabel = 'System';

                        }


                        switch ($entry['action_type']) {

                            case 'agent_explanation':

                                $actionLabel =
                                    'Agent Explanation';

                                break;

                            case 'admin_rejection':

                                $actionLabel =
                                    'Explanation Rejected';

                                break;

                            case 'admin_decision':

                                $actionLabel =
                                    'Administrator Decision';

                                break;

                            case 'admin_note':

                                $actionLabel =
                                    'Administrator Note';

                                break;

                            default:

                                $actionLabel =
                                    ucfirst(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $entry['action_type']
                                        )
                                    );

                        }

                    ?>

                    <div class="border rounded p-3 mb-3">

                        <div class="d-flex justify-content-between align-items-start mb-2">

                            <div>

                                <span class="badge <?= $badgeClass ?>">

                                    <?= htmlspecialchars(
                                        $actorLabel
                                    ) ?>

                                </span>

                                <strong class="ms-2">

                                    <?= htmlspecialchars(
                                        $actorName
                                    ) ?>

                                </strong>

                            </div>


                            <small class="text-muted">

                                <?= date(
                                    'd-m-Y h:i A',
                                    strtotime(
                                        $entry['created_at']
                                    )
                                ) ?>

                            </small>

                        </div>


                        <div class="fw-bold mb-2">

                            <?= htmlspecialchars(
                                $actionLabel
                            ) ?>

                        </div>


                        <?php if (
                            !empty($entry['decision_type'])
                        ): ?>

                            <div class="mb-2">

                                <span class="badge bg-warning text-dark">

                                    <?= htmlspecialchars(
                                        $entry['decision_type']
                                    ) ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <div class="border rounded bg-light p-3">

                            <?= nl2br(
                                htmlspecialchars(
                                    $entry['message']
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

    <!-- Decision Area -->

    <div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        Administrator Decision

    </div>

    <div class="card-body">

        <p class="text-muted">

            Select the action that should be taken for this
            service-overdue case.

            <strong>
                Administrator comments are required.
            </strong>

        </p>


        <form method="POST">

            <div class="row g-4">

                <!-- Accept Explanation -->

                <div class="col-md-6">

                    <div class="card h-100 border-success">

                        <div class="card-body">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="admin_decision"
                                    id="decisionAccept"
                                    value="accept"
                                    required>

                                <label
                                    class="form-check-label"
                                    for="decisionAccept">

                                    <strong>
                                        ✅ Accept Explanation & Close Service
                                    </strong>

                                </label>

                            </div>

                            <p class="text-muted small mt-2 mb-0">

                                Accept the agent's explanation and
                                close the service, only when the
                                service work has actually been completed.

                            </p>

                        </div>

                    </div>

                </div>


                <!-- Reschedule -->

                <div class="col-md-6">

                    <div class="card h-100 border-warning">

                        <div class="card-body">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="admin_decision"
                                    id="decisionReschedule"
                                    value="reschedule">

                                <label
                                    class="form-check-label"
                                    for="decisionReschedule">

                                    <strong>
                                        📅 Reschedule Service
                                    </strong>

                                </label>

                            </div>

                            <p class="text-muted small mt-2 mb-0">

                                The service was not completed within
                                the allocated session and must be
                                scheduled again.

                            </p>

                        </div>

                    </div>

                </div>


                <!-- Reassign -->

                <div class="col-md-6">

                    <div class="card h-100 border-primary">

                        <div class="card-body">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="admin_decision"
                                    id="decisionReassign"
                                    value="reassign">

                                <label
                                    class="form-check-label"
                                    for="decisionReassign">

                                    <strong>
                                        👤 Reassign Service
                                    </strong>

                                </label>

                            </div>

                            <p class="text-muted small mt-2 mb-0">

                                Assign the unfinished service to
                                another agent.

                                This records an agent-performance
                                issue without treating the customer
                                service itself as a failure.

                            </p>

                        </div>

                    </div>

                </div>


                <!-- Reject -->

                <div class="col-md-6">

                    <div class="card h-100 border-danger">

                        <div class="card-body">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="radio"
                                    name="admin_decision"
                                    id="decisionReject"
                                    value="reject">

                                <label
                                    class="form-check-label"
                                    for="decisionReject">

                                    <strong>
                                        ❌ Reject Explanation
                                    </strong>

                                </label>

                            </div>

                            <p class="text-muted small mt-2 mb-0">

                                Reject the explanation and send the
                                case back to the agent for another
                                explanation.

                                This does not close the review.

                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Admin Comments -->

            <div class="mt-4">

                <label
                    for="admin_review_comments"
                    class="form-label fw-bold">

                    Administrator Comments

                </label>

                <textarea
                    name="admin_review_comments"
                    id="admin_review_comments"
                    class="form-control"
                    rows="6"
                    maxlength="2000"
                    required
                    placeholder="Enter the reason for your decision, investigation findings, and any instructions for the agent or customer..."></textarea>

                <div class="form-text">

                    Required for every decision.

                </div>

            </div>


            <div class="d-flex justify-content-between mt-4">

                <a
                    href="?page=needs-admin-review"
                    class="btn btn-secondary">

                    ← Back

                </a>

                <button
                    type="submit"
                    name="submit_service_review"
                    class="btn btn-primary">

                    Continue →

                </button>

            </div>

        </form>

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

<script>
document.querySelectorAll('.card.border-success, .card.border-warning, .card.border-primary, .card.border-danger')
    .forEach(function (card) {

        card.addEventListener('click', function (event) {

            const radio = this.querySelector(
                'input[type="radio"][name="admin_decision"]'
            );

            if (!radio) {
                return;
            }

            radio.checked = true;

            document.querySelectorAll(
                'input[name="admin_decision"]'
            ).forEach(function (input) {

                input.closest('.card').classList.remove(
                    'border-dark',
                    'shadow'
                );

            });

            this.classList.add(
                'border-dark',
                'shadow'
            );

        });

    });
</script>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>