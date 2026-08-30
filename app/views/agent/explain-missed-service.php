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
| Load service job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        sb.id AS service_booking_id,

        r.id AS request_id,
        r.workflow_stage,
        r.job_status,
        r.incomplete_reason,

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
| Only missed service jobs can use this page
|--------------------------------------------------------------------------
*/

$isInitialMissedService = (
    $job['workflow_stage'] === 'Missed Service'
    && $job['job_status'] === 'Missed Service'
);

$isExplanationRequired = (
    $job['workflow_stage'] === 'Service Explanation Required'
    && $job['job_status'] === 'Needs Admin Review'
);

if (!$isInitialMissedService && !$isExplanationRequired) {

    die(
        'This service job is not currently awaiting an agent explanation.'
    );

}


$error = null;

/*
|--------------------------------------------------------------------------
| Load Latest Administrator Rejection
|--------------------------------------------------------------------------
*/

$rejectionStmt = $pdo->prepare("
    SELECT
        h.message,
        h.created_at,
        u.email AS admin_email

    FROM service_review_history h

    LEFT JOIN users u
        ON u.id = h.admin_id

    WHERE
        h.request_id = ?
        AND h.actor_type = 'admin'
        AND h.action_type = 'admin_rejection'
        AND h.decision_type = 'reject'

    ORDER BY
        h.created_at DESC,
        h.id DESC

    LIMIT 1
");

$rejectionStmt->execute([
    $job['request_id']
]);

$latestRejection = $rejectionStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Submit explanation
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_explanation'])
) {

    $explanation = trim(
        $_POST['explanation'] ?? ''
    );


    if ($explanation === '') {

        $error = 'Please provide an explanation before submitting.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Start transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Move to Admin Review
            |--------------------------------------------------------------------------
            |
            | The service booking remains assigned to the authenticated agent.
            | The booking was loaded using sb.agent_id = $agentId, and the UPDATE
            | below also verifies that the same booking is still assigned to that
            | agent. This prevents a different agent from submitting the explanation
            | if the assignment changed between page load and POST.
            |
            */

            $update = $pdo->prepare("
                UPDATE requests r
                SET
                    r.job_status = 'Needs Admin Review',
                    r.workflow_stage = 'Needs Admin Review',
                    r.review_type = 'service_missed',
                    r.incomplete_reason = ?
                WHERE
                    r.id = ?
                    AND (
                        (
                            r.job_status = 'Missed Service'
                            AND r.workflow_stage = 'Missed Service'
                        )
                        OR
                        (
                            r.job_status = 'Needs Admin Review'
                            AND r.workflow_stage = 'Service Explanation Required'
                            AND r.review_type = 'service_missed'
                        )
                    )
                    AND EXISTS (
                        SELECT 1
                        FROM service_bookings sb_check
                        WHERE
                            sb_check.id = ?
                            AND sb_check.request_id = r.id
                            AND sb_check.agent_id = ?
                    )
            ");

            $update->execute([
                $explanation,
                $job['request_id'],
                $bookingId,
                $agentId
            ]);

            if ($update->rowCount() !== 1) {
                throw new Exception(
                    'The service could not be submitted because it is no longer assigned to you or its workflow status has changed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Record Agent Explanation in Service Review History
            |--------------------------------------------------------------------------
            |
            | This preserves every explanation submitted by the agent. If the
            | administrator rejects it later, the next explanation will be added
            | as a new history entry instead of overwriting the old one.
            |
            */

            $history = $pdo->prepare("
                INSERT INTO service_review_history
                (
                    request_id,
                    actor_type,
                    agent_id,
                    action_type,
                    decision_type,
                    message
                )
                VALUES
                (
                    ?,
                    'agent',
                    ?,
                    'agent_explanation',
                    NULL,
                    ?
                )
            ");

            $history->execute([
                (int) $job['request_id'],
                $agentId,
                $explanation
            ]);


            /*
            |--------------------------------------------------------------------------
            | Record audit event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::addCurrentUser(
                $pdo,
                (int) $job['request_id'],
                'SERVICE_MISSED_EXPLANATION',
                RequestEventHelper::TYPE_SERVICE,
                'Missed Service Explanation Submitted',
                'The assigned agent submitted an explanation for the missed service job.',
                true
            );


            $pdo->commit();


            header(
                'Location: ?page=agent-jobs&success=missed-service-explanation-submitted'
            );

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            die($e->getMessage());
        }
    }
}


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h2 class="mb-1">
                Missed Service Requires Explanation
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $job['request_id'] ?>
            </p>

        </div>

        <span class="badge bg-danger fs-5 px-4 py-2">
            Missed Service
        </span>

    </div>


    <div class="card shadow-sm border-danger mb-4">

        <div class="card-header bg-danger text-white">
            Service Start Window Expired
        </div>

        <div class="card-body">

            <p>
                The scheduled one-hour window passed without the
                service being started.
            </p>

            <p class="mb-0">

                Please provide an explanation for the administrator
                before this service job can be reviewed.

            </p>

        </div>

    </div>

    <?php if ($latestRejection): ?>

    <div class="alert alert-warning border-warning shadow-sm mb-4">

        <h5 class="alert-heading mb-2">
            ⚠️ Administrator Requested Another Explanation
        </h5>

        <p class="mb-2">
            Your previous explanation was rejected.
            Please review the administrator's reason below
            and submit a new explanation.
        </p>

        <hr>

        <p class="mb-1">
            <strong>Administrator's reason:</strong>
        </p>

        <div class="bg-white border rounded p-3">

            <?= nl2br(
                htmlspecialchars(
                    $latestRejection['message']
                )
            ) ?>

        </div>

    </div>

<?php endif; ?>


    <div class="row">

        <div class="col-lg-6">

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

                    <p>
                        <strong>Assigned Agent:</strong>
                        <?= htmlspecialchars(
                            $_SESSION['agent']['name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?= formatDate(
                            $job['service_date']
                        ) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Time:</strong>
                        <?= formatTime(
                            $job['service_time']
                        ) ?>
                    </p>

                </div>

            </div>

        </div>


        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">
                    Agent Explanation
                </div>

                <div class="card-body">

                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <form method="POST">

                        <div class="mb-3">

                            <label
                                for="explanation"
                                class="form-label">

                                Please explain what happened

                            </label>

                            <textarea
                                name="explanation"
                                id="explanation"
                                class="form-control"
                                rows="7"
                                required
                                placeholder="Explain why the service was not started within the scheduled time window..."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            name="submit_explanation"
                            class="btn btn-primary">

                            Submit Explanation for Review

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

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>