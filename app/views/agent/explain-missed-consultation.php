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

    die('Invalid consultation.');
}


/*
|--------------------------------------------------------------------------
| Load Consultation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

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
");

$stmt->execute([
    $requestId,
    $agentId
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Verify Consultation
|--------------------------------------------------------------------------
*/

if (!$consultation) {

    die('Consultation not found or not assigned to you.');
}


/*
|--------------------------------------------------------------------------
| Verify Consultation Requires Agent Explanation
|--------------------------------------------------------------------------
*/

if ($consultation['workflow_stage'] !== 'Consultation Decision Required') {

    header(
        'Location: ?page=agent-consultations'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Handle Explanation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim(
        $_POST['missed_consultation_reason'] ?? ''
    );

    if ($reason === '') {

        $error = 'Please provide an explanation.';
    }

    else {

        /*
        |--------------------------------------------------------------------------
        | Save Agent Explanation
        |--------------------------------------------------------------------------
        */

       $update = $pdo->prepare("
    UPDATE requests

    SET
        missed_consultation_reason = ?,
        workflow_stage = 'Needs Admin Review',
        job_status = 'Needs Admin Review'

    WHERE
        id = ?
        AND agent_id = ?
        AND workflow_stage = 'Consultation Decision Required'
");

$update->execute([
    $reason,
    $requestId,
    $agentId
]);

if ($update->rowCount() === 0) {

    die('Unable to update the consultation. Its workflow state may have changed.');
}

        /*
        |--------------------------------------------------------------------------
        | Record Audit Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            $requestId,
            RequestEventHelper::EVENT_MISSED_CONSULTATION_EXPLAINED,
            RequestEventHelper::TYPE_CONSULTATION,
            'Missed Consultation Explained',
            'The assigned agent submitted an explanation for the missed consultation: ' . $reason,
            false
        );


        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        header(
            'Location: ?page=agent-consultations'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Agent Header
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="mb-4">

        <h2>

            Explain Missed Consultation

        </h2>

        <p class="text-muted">

            Please explain why this consultation was missed.

        </p>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">


            <?php if (!empty($error)): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!--
            |--------------------------------------------------------------------------
            | Consultation Information
            |--------------------------------------------------------------------------
            -->

            <h5 class="mb-3">

                Consultation Information

            </h5>


            <div class="row mb-4">

                <div class="col-md-6">

                    <p>

                        <strong>Request:</strong><br>

                        #<?= (int) $consultation['id'] ?>

                    </p>

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Customer:</strong><br>

                        <?= htmlspecialchars(
                            $consultation['customer_name']
                        ) ?>

                    </p>

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Service:</strong><br>

                        <?= htmlspecialchars(
                            $consultation['service_name']
                        ) ?>

                    </p>

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Consultation Method:</strong><br>

                        <?= htmlspecialchars(
                            $consultation['consultation_method']
                        ) ?>

                    </p>

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Scheduled Date:</strong><br>

                        <?= htmlspecialchars(
                            $consultation['slot_date']
                        ) ?>

                    </p>

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Scheduled Time:</strong><br>

                        <?= htmlspecialchars(
                            $consultation['slot_time']
                        ) ?>

                    </p>

                </div>

            </div>


            <div class="alert alert-warning">

                <strong>Missed Consultation</strong>

                <p class="mb-0 mt-2">

                    The scheduled consultation window has expired.
                    Please provide an explanation for why the consultation
                    was not started.

                </p>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Explanation Form
            |--------------------------------------------------------------------------
            -->

            <form method="POST">

                <div class="mb-4">

                    <label class="form-label">

                        Why was the consultation missed?

                    </label>

                    <textarea
                        name="missed_consultation_reason"
                        class="form-control"
                        rows="6"
                        required
                        placeholder="Please provide a clear explanation..."
                    ></textarea>

                </div>


                <div class="d-flex justify-content-between">

                    <a
                        href="?page=agent-consultations"
                        class="btn btn-secondary">

                        ← Back

                    </a>


                    <button
                        type="submit"
                        class="btn btn-danger">

                        Submit Explanation

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>