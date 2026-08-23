<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;

}

require_once HELPER_PATH . '/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Validate Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;

}


/*
|--------------------------------------------------------------------------
| Get Consultation Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
        s.title AS service_name
    FROM requests r
    LEFT JOIN services s
        ON s.id = r.service_id
    WHERE
        r.id = ?
        AND r.customer_id = ?
    LIMIT 1
");

$stmt->execute([
    $requestId,
    $_SESSION['customer']['id']
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {

    header('Location: ?page=customer-requests');
    exit;

}


/*
|--------------------------------------------------------------------------
| Verify Workflow Stage
|--------------------------------------------------------------------------
*/

if (
    $request['workflow_stage']
    !== 'Awaiting Customer Confirmation'
) {

    header('Location: ?page=customer-requests');
    exit;

}


/*
|--------------------------------------------------------------------------
| Process Customer Confirmation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $confirmation =
        $_POST['customer_confirmation'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Customer Confirms Consultation Was Completed
    |--------------------------------------------------------------------------
    */

    if ($confirmation === 'completed') {

        $pdo->beginTransaction();

        try {

           $update = $pdo->prepare("
                UPDATE requests
                SET
                    workflow_stage = 'Needs Admin Final Approval',
                    job_status = 'Pending',
                    review_type = NULL
                WHERE
                    id = ?
                    AND customer_id = ?
                    AND workflow_stage = ?
            ");

            $update->execute([
                $requestId,
                $_SESSION['customer']['id'],
                'Awaiting Customer Confirmation'
            ]);

            if ($update->rowCount() !== 1) {

                throw new Exception(
                    'The consultation could not be confirmed because its status changed.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Record Customer Confirmation Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::add(
                $pdo,
                (int) $requestId,
                'CONSULTATION_COMPLETION_CONFIRMED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Completion Confirmed by Customer',
                'The customer confirmed that the consultation was completed successfully. Final administrator approval is now required before a proposal can be created.',
                RequestEventHelper::SOURCE_CUSTOMER,
                (int) $_SESSION['customer']['id'],
                true
            );


            $pdo->commit();


            $_SESSION['success'] =
                'Thank you. Your consultation completion confirmation has been sent to the administrator for final approval.';

            header(
                'Location: ?page=customer-requests'
            );

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            die($e->getMessage());

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Customer Confirms Consultation Was Not Completed
    |--------------------------------------------------------------------------
    */

    if ($confirmation === 'not_completed') {

        $pdo->beginTransaction();

        try {

            $update = $pdo->prepare("
                UPDATE requests
                SET
                    workflow_stage = 'Needs Admin Review',
                    job_status = 'Needs Admin Review',
                    review_type = 'consultation_not_completed',
                    admin_instruction = NULL
                WHERE
                    id = ?
                    AND customer_id = ?
                    AND workflow_stage = ?
            ");

            $update->execute([
                $requestId,
                $_SESSION['customer']['id'],
                'Awaiting Customer Confirmation'
            ]);

            if ($update->rowCount() !== 1) {

                throw new Exception(
                    'The consultation response could not be processed because its status changed.'
                );

            }


            RequestEventHelper::add(
                $pdo,
                (int) $requestId,
                'CONSULTATION_NOT_COMPLETED_CONFIRMED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Not Completed',
                'The customer reported that the consultation was not completed after the administrator accepted the agent explanation.',
                RequestEventHelper::SOURCE_CUSTOMER,
                (int) $_SESSION['customer']['id'],
                true
            );


            $pdo->commit();


            $_SESSION['success'] =
                'Thank you. Your response has been sent to the administrator for review.';

            header(
                'Location: ?page=customer-requests'
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


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'Consultation Completion Confirmation';

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header bg-info text-white">

            <strong>
                Consultation Completion Confirmation
            </strong>

        </div>

        <div class="card-body">

            <p>
                The agent has reported that the following consultation
                has been completed:
            </p>

            <div class="border rounded p-3 mb-3">

                <strong>
                    <?= htmlspecialchars(
                        $request['service_name'] ?? 'Consultation'
                    ) ?>
                </strong>

            </div>

            <p>
                Please confirm whether your consultation was completed
                successfully.
            </p>


            <form method="POST">

                <input
                    type="hidden"
                    name="customer_confirmation"
                    value="completed"
                >

                <button
                    type="submit"
                    class="btn btn-success w-100 mb-2"
                >

                    Yes, Consultation Was Completed

                </button>

            </form>


            <form method="POST">

                <input
                    type="hidden"
                    name="customer_confirmation"
                    value="not_completed"
                >

                <button
                    type="submit"
                    class="btn btn-danger w-100"
                >

                    No, Consultation Was Not Completed

                </button>

            </form>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>