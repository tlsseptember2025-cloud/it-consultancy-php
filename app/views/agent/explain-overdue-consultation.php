<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$agentId = (int) $_SESSION['agent']['id'];
$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid consultation request.');

}


/*
|--------------------------------------------------------------------------
| Load the exact overdue consultation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.workflow_stage,
        r.job_status,
        r.incomplete_reason,
        r.completed_at,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.id = ?
        AND cb.agent_id = ?

    LIMIT 1
");

$stmt->execute([
    $requestId,
    $agentId
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$consultation) {

    die('Consultation not found.');

}


/*
|--------------------------------------------------------------------------
| Only overdue admin-review consultations may use this page
|--------------------------------------------------------------------------
*/

if (
    $consultation['job_status'] !== 'Needs Admin Review'
    || $consultation['workflow_stage'] !== 'Needs Admin Review'
) {

    die(
        'This consultation is not currently awaiting an overdue-session explanation.'
    );

}


$error = null;


/*
|--------------------------------------------------------------------------
| Save explanation
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

        $update = $pdo->prepare("
            UPDATE requests

            SET
                incomplete_reason = ?

            WHERE
                id = ?
                AND job_status = 'Needs Admin Review'
                AND workflow_stage = 'Needs Admin Review'
        ");

        $update->execute([
            $explanation,
            $requestId
        ]);


        RequestEventHelper::addCurrentUser(
            $pdo,
            $requestId,
            'CONSULTATION_OVERDUE_EXPLANATION',
            RequestEventHelper::TYPE_CONSULTATION,
            'Overdue Consultation Explanation Submitted',
            'The assigned agent submitted an explanation for the overdue consultation session.',
            false
        );


        header(
            'Location: ?page=agent-consultations&success=overdue-explanation-submitted'
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
                Consultation Requires Explanation
            </h2>

            <p class="text-muted mb-0">
                Request #<?= (int) $consultation['id'] ?>
            </p>

        </div>

        <span class="badge bg-danger fs-5 px-4 py-2">
            Needs Admin Review
        </span>

    </div>


    <div class="card shadow-sm border-danger mb-4">

        <div class="card-header bg-danger text-white">

            Consultation Session Expired

        </div>

        <div class="card-body">

            <p>
                The consultation was started, but the session remained
                <strong>In Progress</strong> after the scheduled one-hour
                consultation period ended.
            </p>

            <p class="mb-0">
                Please provide an explanation for the administrator before
                this case can be reviewed.
            </p>

        </div>

    </div>


    <div class="row">

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">
                    Consultation Information
                </div>

                <div class="card-body">

                    <p>
                        <strong>Customer:</strong>
                        <?= htmlspecialchars(
                            $consultation['customer_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Service:</strong>
                        <?= htmlspecialchars(
                            $consultation['service_name']
                        ) ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?= htmlspecialchars(
                            $consultation['slot_date']
                        ) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Scheduled Time:</strong>
                        <?= htmlspecialchars(
                            $consultation['slot_time']
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
                                placeholder="Explain why the consultation remained open after the scheduled session ended..."
                            ><?= htmlspecialchars(
                                $consultation['incomplete_reason'] ?? ''
                            ) ?></textarea>

                        </div>


                        <button
                            type="submit"
                            name="submit_explanation"
                            class="btn btn-primary">

                            Submit Explanation for Review

                        </button>

                        <a
                            href="?page=agent-consultations"
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