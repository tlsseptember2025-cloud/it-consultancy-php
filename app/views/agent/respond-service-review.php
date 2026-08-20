<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

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
| Load Service Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        sb.id AS service_booking_id,

        r.id AS request_id,
        r.workflow_stage,
        r.job_status,
        r.review_type,
        r.incomplete_reason,
        r.admin_review_comments,

        c.name AS customer_name,

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
| Verify Agent Response State
|--------------------------------------------------------------------------
*/

if (
    $job['workflow_stage'] !== 'Service Explanation Required'
) {

    die(
        'This service job is not currently awaiting an agent response.'
    );

}


$error = null;


/*
|--------------------------------------------------------------------------
| Submit New Explanation
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_service_explanation'])
) {

    $explanation = trim(
        $_POST['explanation'] ?? ''
    );


    if ($explanation === '') {

        $error =
            'Please provide a new explanation before submitting.';

    } else {

        /*
|--------------------------------------------------------------------------
| Save Agent Explanation to Review History
|--------------------------------------------------------------------------
*/

$history = $pdo->prepare("
    INSERT INTO service_review_history
    (
        request_id,
        actor_type,
        agent_id,
        action_type,
        message
    )
    VALUES
    (
        ?,
        'agent',
        ?,
        'agent_explanation',
        ?
    )
");

$history->execute([
    $job['request_id'],
    $agentId,
    $explanation
]);


/*
|--------------------------------------------------------------------------
| Update Current Workflow State
|--------------------------------------------------------------------------
*/

$update = $pdo->prepare("
    UPDATE requests

    SET
        incomplete_reason = ?,
        workflow_stage = 'Needs Admin Review',
        job_status = 'Needs Admin Review',
        review_type = 'service_overdue'

    WHERE
        id = ?

        AND workflow_stage = 'Service Explanation Required'
");

$update->execute([
    $explanation,
    $job['request_id']
]);


if ($update->rowCount() !== 1) {

    die(
        'This service review could not be updated.'
    );

}

        if ($update->rowCount() !== 1) {

            die(
                'This service review could not be updated.'
            );

        }


        RequestEventHelper::addCurrentUser(
            $pdo,
            (int) $job['request_id'],
            'SERVICE_EXPLANATION_RESUBMITTED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Explanation Resubmitted',
            'The assigned agent submitted a new explanation after the administrator rejected the previous explanation.',
            true
        );


        header(
            'Location: ?page=agent-jobs&success=service-explanation-resubmitted'
        );

        exit;
    }
}


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">
                Respond to Service Review
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $job['request_id'] ?>
            </p>

        </div>

        <span class="badge bg-warning text-dark fs-6 px-4 py-2">
            Explanation Required
        </span>

    </div>


    <!-- Administrator Feedback -->

    <div class="card shadow-sm border-danger mb-4">

        <div class="card-header bg-danger text-white">

            Administrator Requested Further Explanation

        </div>

        <div class="card-body">

            <p>
                Your previous explanation was not accepted by
                the administrator.
            </p>

            <strong>
                Administrator Comments
            </strong>

            <div class="border rounded bg-light p-3 mt-2">

                <?php if (
                    !empty($job['admin_review_comments'])
                ): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $job['admin_review_comments']
                        )
                    ) ?>

                <?php else: ?>

                    <span class="text-muted">
                        No administrator comments were provided.
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <?php

$historyStmt = $pdo->prepare("
    SELECT
        h.*,
        a.name AS agent_name,
        u.email AS admin_email

    FROM service_review_history h

    LEFT JOIN agents a
        ON a.id = h.agent_id

    LEFT JOIN users u
        ON u.id = h.admin_id

    WHERE
        h.request_id = ?

    ORDER BY
        h.created_at ASC,
        h.id ASC
");

$historyStmt->execute([
    $job['request_id']
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
                No previous review history.
            </div>

        <?php else: ?>

            <?php foreach ($reviewHistory as $entry): ?>

                <?php

                if ($entry['actor_type'] === 'agent') {

                    $actorLabel = 'Agent';
                    $actorName = $entry['agent_name'] ?: 'Agent';
                    $badgeClass = 'bg-primary';

                } elseif ($entry['actor_type'] === 'admin') {

                    $actorLabel = 'Administrator';
                    $actorName = $entry['admin_email'] ?: 'Administrator';
                    $badgeClass = 'bg-danger';

                } else {

                    $actorLabel = 'System';
                    $actorName = 'System';
                    $badgeClass = 'bg-secondary';

                }

                if ($entry['action_type'] === 'agent_explanation') {

                    $actionLabel = 'Agent Explanation';

                } elseif ($entry['action_type'] === 'admin_rejection') {

                    $actionLabel = 'Explanation Rejected';

                } elseif ($entry['action_type'] === 'admin_decision') {

                    $actionLabel = 'Administrator Decision';

                } else {

                    $actionLabel = ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $entry['action_type']
                        )
                    );

                }

                ?>

                <div class="border rounded p-3 mb-3">

                    <div class="d-flex justify-content-between">

                        <div>

                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($actorLabel) ?>
                            </span>

                            <strong class="ms-2">
                                <?= htmlspecialchars($actorName) ?>
                            </strong>

                        </div>

                        <small class="text-muted">

                            <?= date(
                                'd-m-Y h:i A',
                                strtotime($entry['created_at'])
                            ) ?>

                        </small>

                    </div>

                    <div class="fw-bold mt-2 mb-2">

                        <?= htmlspecialchars($actionLabel) ?>

                    </div>

                    <div class="border rounded bg-light p-3">

                        <?= nl2br(
                            htmlspecialchars(
                                $entry['message']
                            )
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


    <!-- Service Information -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Service Information
        </div>

        <div class="card-body">

            <p>
                <strong>Customer:</strong>
                <?= htmlspecialchars(
                    $job['customer_name']
                ) ?>
            </p>

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars(
                    $job['service_name']
                ) ?>
            </p>

            <div class="row">

                <div class="col-md-6">

                    <strong>Scheduled Date</strong><br>

                    <?= formatDate(
                        $job['service_date']
                    ) ?>

                </div>

                <div class="col-md-6">

                    <strong>Scheduled Time</strong><br>

                    <?= formatTime(
                        $job['service_time']
                    ) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- New Explanation -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            New Agent Explanation

        </div>

        <div class="card-body">

            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="mb-3">

                    <label
                        for="explanation"
                        class="form-label fw-bold">

                        Please provide your response

                    </label>

                    <textarea
                        name="explanation"
                        id="explanation"
                        class="form-control"
                        rows="8"
                        maxlength="2000"
                        required
                        placeholder="Explain what happened and address the administrator's concerns..."></textarea>

                    <div class="form-text">
                        Your response will be sent back to the administrator for review.
                    </div>

                </div>


                <button
                    type="submit"
                    name="submit_service_explanation"
                    class="btn btn-primary">

                    Submit New Explanation

                </button>


                <a
                    href="?page=agent-jobs"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </form>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>