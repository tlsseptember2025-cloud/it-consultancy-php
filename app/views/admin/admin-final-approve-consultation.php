<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;

}

require_once HELPER_PATH . '/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Validate Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=needs-admin-review');
    exit;

}


/*
|--------------------------------------------------------------------------
| Get Consultation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name AS customer_name,
        c.email AS customer_email,

        a.name AS agent_name,

        s.title AS service_name,

        cs.slot_date AS consultation_date,
        cs.slot_time AS consultation_time,
        cs.consultation_method

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.id = ?
        AND r.workflow_stage = 'Needs Admin Final Approval'

    LIMIT 1
");

$stmt->execute([
    $requestId
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {

    header('Location: ?page=needs-admin-review');
    exit;

}

/*
|--------------------------------------------------------------------------
| Process Final Approval
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pdo->beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Current Administrator
        |--------------------------------------------------------------------------
        */

        $adminStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $adminStmt->execute([
            $_SESSION['user']
        ]);

        $currentAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentAdmin) {

            throw new Exception(
                'Unable to identify the current administrator.'
            );

        }

        $currentAdminId = (int) $currentAdmin['id'];


        /*
        |--------------------------------------------------------------------------
        | Complete Consultation
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Consultation Completed',
                job_status = 'Completed',
                completed_at = NOW()
            WHERE
                id = ?
                AND workflow_stage = 'Needs Admin Final Approval'
        ");

        $update->execute([
            $requestId
        ]);

        if ($update->rowCount() !== 1) {

            throw new Exception(
                'The consultation could not be finally approved because its status changed.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Request Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            $requestId,
            'CONSULTATION_FINAL_APPROVED',
            RequestEventHelper::TYPE_CONSULTATION,
            'Consultation Finally Approved',
            'The administrator gave final approval to the customer-confirmed consultation. The consultation is now completed and ready for proposal creation.',
            true
        );


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        $_SESSION['success'] =
            'The consultation has been finally approved and is ready for proposal creation.';

        header(
            'Location: ?page=needs-admin-review'
        );

        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        die($e->getMessage());

    }

}

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header bg-success text-white">

            <strong>
                Final Consultation Approval
            </strong>

        </div>

        <div class="card-body">

            <div class="alert alert-info">

                The customer has confirmed that this consultation
                was completed successfully.

                Please review the consultation details and give
                final approval before a proposal can be created.

            </div>


            <div class="row">

                <div class="col-md-6">

                    <p>

                        <strong>Request ID:</strong>

                        #<?= (int) $consultation['id'] ?>

                    </p>

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

                </div>


                <div class="col-md-6">

                    <p>

                        <strong>Agent:</strong>

                        <?= htmlspecialchars(
                            $consultation['agent_name']
                        ) ?>

                    </p>

                    <p>

                        <strong>Consultation Date:</strong>

                        <?= htmlspecialchars(
                            $consultation['consultation_date']
                        ) ?>

                    </p>

                    <p>

                        <strong>Consultation Time:</strong>

                        <?= htmlspecialchars(
                            $consultation['consultation_time']
                        ) ?>

                    </p>

                    <p>

                        <strong>Method:</strong>

                        <?= htmlspecialchars(
                            $consultation['consultation_method']
                        ) ?>

                    </p>

                </div>

            </div>

            <hr>


            <form method="POST">

                <button
                    type="submit"
                    class="btn btn-success"
                >

                    ✓ Final Approve Consultation

                </button>

                <a
                    href="?page=needs-admin-review"
                    class="btn btn-secondary"
                >

                    Cancel

                </a>

            </form>

        </div>

    </div>

</div>


<?php require VIEW_PATH.'/layouts/footer.php'; ?>