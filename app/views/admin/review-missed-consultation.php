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
        AND r.workflow_stage = 'Missed Consultation Review'
");

$stmt->execute([$requestId]);

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
| Admin Decision
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['missed_consultation_decision'])
) {

    $decision = $_POST['missed_consultation_decision'];

    /*
    |--------------------------------------------------------------------------
    | Keep Same Agent
    |--------------------------------------------------------------------------
    */

    if ($decision === 'keep_agent') {

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Consultation Confirmed',
                job_status = 'Pending',
                status = 'Pending',
                admin_instruction = '__RESCHEDULE_ALLOWED__'
            WHERE
                id = ?
                AND workflow_stage = 'Missed Consultation Review'
        ");

        $update->execute([
            $requestId
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            $requestId,
            'MISSED_CONSULTATION_APPROVED',
            RequestEventHelper::TYPE_CONSULTATION,
            'Missed Consultation Approved',
            'The administrator reviewed the missed consultation and approved rescheduling with the same agent.',
            true
        );

        header(
            'Location: ?page=customer-requests'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Reassign Agent
    |--------------------------------------------------------------------------
    */

    if ($decision === 'reassign_agent') {

        header(
            'Location: ?page=admin-assign-agent&id=' . $requestId
        );

        exit;
    }
}

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
    | Missed Consultation Explanation
    |--------------------------------------------------------------------------
    -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-warning">

            <strong>
                Agent Explanation
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
                    No explanation has been provided.
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


    <div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <strong>
            Administrator Decision
        </strong>

    </div>

    <div class="card-body">

        <p class="text-muted">

            Choose how this missed consultation should proceed.

            The customer will be allowed to reschedule in either case.

        </p>


        <div class="row g-3">


            <!-- Keep Same Agent -->

            <div class="col-md-6">

                <form method="POST">

                    <input
                        type="hidden"
                        name="missed_consultation_decision"
                        value="keep_agent">

                    <button
                        type="submit"
                        class="btn btn-success w-100">

                        ✓ Keep Same Agent & Allow Reschedule

                    </button>

                </form>

            </div>


            <!-- Reassign Agent -->

            <div class="col-md-6">

                <form method="POST">

                    <input
                        type="hidden"
                        name="missed_consultation_decision"
                        value="reassign_agent">

                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Reassign Agent

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>


    <div class="d-flex justify-content-between">

        <a
            href="?page=dashboard"
            class="btn btn-secondary">

            ← Back to Dashboard

        </a>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>