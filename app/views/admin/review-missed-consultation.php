<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid consultation.');
}

$adminId = (int) ($_SESSION['user']['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Load Missed Consultation
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

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.id = ?
        AND r.workflow_stage = 'Needs Admin Review'
        AND r.missed_consultation_reason IS NOT NULL
        AND r.missed_consultation_reason <> ''
");

$stmt->execute([
    $requestId
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Verify Consultation
|--------------------------------------------------------------------------
*/

if (!$consultation) {

    die('Missed consultation review not found.');
}


/*
|--------------------------------------------------------------------------
| Load Consultation Review History
|--------------------------------------------------------------------------
*/

$historyStmt = $pdo->prepare("
    SELECT
        h.*,
        a.name AS history_agent_name

    FROM consultation_review_history h

    LEFT JOIN agents a
        ON a.id = h.agent_id

    WHERE
        h.request_id = ?

    ORDER BY
        h.created_at ASC,
        h.id ASC
");

$historyStmt->execute([
    $requestId
]);

$reviewHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Admin Decision
|--------------------------------------------------------------------------
*/

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['missed_consultation_decision'])
) {

    $decision = trim(
        $_POST['missed_consultation_decision']
    );

    $adminComment = trim(
        $_POST['admin_comment'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Administrator Comment Required
    |--------------------------------------------------------------------------
    */

    if ($adminComment === '') {

        $error = 'Please enter an administrator comment before making a decision.';
    }

    else {

        /*
        |--------------------------------------------------------------------------
        | Accept Explanation — Keep Same Agent
        |--------------------------------------------------------------------------
        */

        if ($decision === 'keep_agent') {

            try {

                $pdo->beginTransaction();


                $update = $pdo->prepare("
                    UPDATE requests

                    SET
                        workflow_stage = 'Awaiting Customer Reschedule',
                        job_status = 'Pending',
                        status = 'Pending',
                        admin_instruction = '__RESCHEDULE_ALLOWED__'

                    WHERE
                        id = ?
                        AND workflow_stage = 'Needs Admin Review'
                ");

                $update->execute([
                    $requestId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new Exception(
                        'The consultation workflow state may have changed.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Save Administrator Decision
                |--------------------------------------------------------------------------
                */

                $history = $pdo->prepare("
                    INSERT INTO consultation_review_history
                    (
                        request_id,
                        actor_type,
                        agent_id,
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
                        ?,
                        'admin_decision',
                        'keep_agent',
                        ?
                    )
                ");

                $history->execute([
                    $requestId,
                    $consultation['agent_id'] ?? null,
                    $adminId,
                    $adminComment
                ]);


                RequestEventHelper::addCurrentUser(
                    $pdo,
                    $requestId,
                    'MISSED_CONSULTATION_APPROVED',
                    RequestEventHelper::TYPE_CONSULTATION,
                    'Missed Consultation Explanation Accepted',
                    'The administrator accepted the agent explanation and approved rescheduling with the same agent.',
                    true
                );


                $pdo->commit();


                header(
                    'Location: ?page=view-request&id=' . $requestId
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                die(
                    'Unable to accept the explanation: '
                    . htmlspecialchars($e->getMessage())
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Accept Explanation — Reassign Agent
        |--------------------------------------------------------------------------
        */

        if ($decision === 'reassign_agent') {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Save Administrator Decision
                |--------------------------------------------------------------------------
                */

                $history = $pdo->prepare("
                    INSERT INTO consultation_review_history
                    (
                        request_id,
                        actor_type,
                        agent_id,
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
                        ?,
                        'admin_decision',
                        'reassign_agent',
                        ?
                    )
                ");

                $history->execute([
                    $requestId,
                    $consultation['agent_id'] ?? null,
                    $adminId,
                    $adminComment
                ]);


                RequestEventHelper::addCurrentUser(
                    $pdo,
                    $requestId,
                    'MISSED_CONSULTATION_REASSIGNMENT_REQUIRED',
                    RequestEventHelper::TYPE_CONSULTATION,
                    'Missed Consultation Agent Reassignment Required',
                    'The administrator accepted the explanation and chose to reassign the consultation to another agent.',
                    true
                );


                $pdo->commit();


                header(
                    'Location: ?page=admin-assign-agent&id=' . $requestId
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                die(
                    'Unable to continue with agent reassignment: '
                    . htmlspecialchars($e->getMessage())
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Reject Explanation
        |--------------------------------------------------------------------------
        |
        | The explanation is not logically acceptable.
        |
        | The administrator must provide a reason.
        |
        | The request returns to the assigned agent and the agent
        | must submit a NEW explanation.
        |
        */

        if ($decision === 'reject_explanation') {

            try {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Save Administrator Rejection to Conversation History
                |--------------------------------------------------------------------------
                */

                $history = $pdo->prepare("
                    INSERT INTO consultation_review_history
                    (
                        request_id,
                        actor_type,
                        agent_id,
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
                        ?,
                        'admin_rejection',
                        'reject_explanation',
                        ?
                    )
                ");

                $history->execute([
                    $requestId,
                    $consultation['agent_id'] ?? null,
                    $adminId,
                    $adminComment
                ]);


                /*
                |--------------------------------------------------------------------------
                | Return Consultation to Agent
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare("
                    UPDATE requests

                    SET
                        missed_consultation_reason = NULL,
                        workflow_stage = 'Consultation Decision Required',
                        job_status = 'Pending',
                        status = 'Pending'

                    WHERE
                        id = ?
                        AND workflow_stage = 'Needs Admin Review'
                ");

                $update->execute([
                    $requestId
                ]);


                if ($update->rowCount() !== 1) {

                    throw new Exception(
                        'The consultation workflow state may have changed.'
                    );
                }


                RequestEventHelper::addCurrentUser(
                    $pdo,
                    $requestId,
                    'MISSED_CONSULTATION_EXPLANATION_REJECTED',
                    RequestEventHelper::TYPE_CONSULTATION,
                    'Missed Consultation Explanation Rejected',
                    'The administrator rejected the agent explanation and returned the consultation to the agent for a new explanation.',
                    true
                );


                $pdo->commit();


                header(
                    'Location: ?page=needs-admin-review&success=explanation-rejected'
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                die(
                    'Unable to reject the explanation: '
                    . htmlspecialchars($e->getMessage())
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Invalid Decision
        |--------------------------------------------------------------------------
        */

        $error = 'Invalid administrator decision.';
    }
}


/*
|--------------------------------------------------------------------------
| Admin Header
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Missed Consultation Review
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $consultation['id'] ?>
            </p>

        </div>

        <span class="badge bg-danger fs-6">
            Missed Consultation Review
        </span>

    </div>


    <?php if (!empty($error)): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | Customer Information
    |--------------------------------------------------------------------------
    -->

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

                    <strong>Phone</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['customer_phone']
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Consultation Information
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Consultation Information
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4 mb-3">

                    <strong>Request</strong>

                    <div>
                        #<?= (int) $consultation['id'] ?>
                    </div>

                </div>


                <div class="col-md-4 mb-3">

                    <strong>Service</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['service_name']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4 mb-3">

                    <strong>Agent</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['agent_name'] ?? 'Not assigned'
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Date</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['slot_date']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Time</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['slot_time']
                        ) ?>
                    </div>

                </div>


                <div class="col-md-4">

                    <strong>Method</strong>

                    <div>
                        <?= htmlspecialchars(
                            $consultation['consultation_method']
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Consultation Review History
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>
                Consultation Review History
            </strong>

        </div>

        <div class="card-body">

            <?php if (!empty($reviewHistory)): ?>

                <?php foreach ($reviewHistory as $entry): ?>

                    <?php

                    $actorType = $entry['actor_type'] ?? '';

                    $actionType = $entry['action_type'] ?? '';

                    $isAgent = $actorType === 'agent';

                    $isRejection = $actionType === 'admin_rejection';

                    $isDecision = $actionType === 'admin_decision';

                    ?>

                    <div class="border rounded p-3 mb-3">

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <strong>

                                <?php if ($isAgent): ?>

                                    Agent
                                    <?php if (!empty($entry['history_agent_name'])): ?>
                                        —
                                        <?= htmlspecialchars(
                                            $entry['history_agent_name']
                                        ) ?>
                                    <?php endif; ?>

                                <?php else: ?>

                                    Administrator

                                <?php endif; ?>

                            </strong>


                            <small class="text-muted">

                                <?= htmlspecialchars(
                                    $entry['created_at']
                                ) ?>

                            </small>

                        </div>


                        <?php if ($isRejection): ?>

                            <div class="alert alert-danger mb-0">

                                <strong>
                                    ✕ Explanation Rejected
                                </strong>

                                <div class="mt-2">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $entry['message'] ?? ''
                                        )
                                    ) ?>

                                </div>

                            </div>


                        <?php elseif ($isDecision): ?>

                            <div class="alert alert-info mb-0">

                                <strong>

                                    Administrator Decision:

                                    <?php
                                    $decisionLabel = $entry['decision_type'] ?? '';

                                    if ($decisionLabel === 'keep_agent') {
                                        echo 'Accept & Keep Agent';
                                    }
                                    elseif ($decisionLabel === 'reassign_agent') {
                                        echo 'Accept & Reassign Agent';
                                    }
                                    else {
                                        echo htmlspecialchars($decisionLabel);
                                    }
                                    ?>

                                </strong>

                                <div class="mt-2">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $entry['message'] ?? ''
                                        )
                                    ) ?>

                                </div>

                            </div>


                        <?php else: ?>

                            <div class="border rounded p-3">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $entry['message'] ?? ''
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="text-muted">
                    No consultation review history has been recorded yet.
                </div>

            <?php endif; ?>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Current Agent Explanation
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-warning">

            <strong>
                Current Agent Explanation
            </strong>

        </div>

        <div class="card-body">

            <?php if (!empty($consultation['missed_consultation_reason'])): ?>

                <div class="p-3 border rounded">

                    <?= nl2br(
                        htmlspecialchars(
                            $consultation['missed_consultation_reason']
                        )
                    ) ?>

                </div>

            <?php else: ?>

                <span class="text-muted">
                    No current explanation has been provided.
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            Review Status
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <strong>Workflow Stage</strong>

                    <div>

                        <span class="badge bg-warning text-dark">

                            <?= htmlspecialchars(
                                $consultation['workflow_stage']
                            ) ?>

                        </span>

                    </div>

                </div>


                <div class="col-md-6">

                    <strong>Job Status</strong>

                    <div>

                        <?= htmlspecialchars(
                            $consultation['job_status']
                        ) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Administrator Decision
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <strong>
                Administrator Decision
            </strong>

        </div>

        <div class="card-body">

            <p class="text-muted">

                Review the agent's explanation and provide an administrator
                comment before choosing how this consultation should proceed.

                If the explanation is rejected, the consultation will be
                returned to the assigned agent for a new explanation.

            </p>


            <!--
            |--------------------------------------------------------------------------
            | Administrator Comment
            |--------------------------------------------------------------------------
            -->

            <div class="mb-4">

                <label
                    for="admin_comment"
                    class="form-label">

                    <strong>
                        Administrator Comment
                    </strong>

                </label>

                <textarea
                    id="admin_comment"
                    name="admin_comment"
                    class="form-control"
                    rows="4"
                    form="decision-form"
                    required
                    placeholder="Enter your review comment or reason for the decision..."
                ></textarea>

                <div class="form-text">

                    This comment will be saved in the consultation review history.

                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Decision Buttons
            |--------------------------------------------------------------------------
            -->

            <div class="row g-2">


                <!-- Accept — Keep Same Agent -->

                <div class="col-md-4">

                    <form
                        method="POST"
                        id="decision-form"
                    >

                        <input
                            type="hidden"
                            name="missed_consultation_decision"
                            value="keep_agent"
                        >

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >

                            ✓ Accept & Keep Agent

                        </button>

                    </form>

                </div>


                <!-- Accept — Reassign Agent -->

                <div class="col-md-4">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="missed_consultation_decision"
                            value="reassign_agent"
                        >

                        <input
                            type="hidden"
                            name="admin_comment"
                            id="reassign_admin_comment"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                            onclick="
                                document.getElementById('reassign_admin_comment').value =
                                document.getElementById('admin_comment').value;
                            "
                        >

                            ⇄ Accept & Reassign Agent

                        </button>

                    </form>

                </div>


                <!-- Reject Explanation -->

                <div class="col-md-4">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="missed_consultation_decision"
                            value="reject_explanation"
                        >

                        <input
                            type="hidden"
                            name="admin_comment"
                            id="reject_admin_comment"
                        >

                        <button
                            type="submit"
                            class="btn btn-danger w-100"
                            onclick="
                                document.getElementById('reject_admin_comment').value =
                                document.getElementById('admin_comment').value;
                            "
                        >

                            ✕ Reject Explanation

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>


    <div class="d-flex justify-content-between">

        <a
            href="?page=dashboard"
            class="btn btn-secondary"
        >

            ← Back to Dashboard

        </a>

    </div>

</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>