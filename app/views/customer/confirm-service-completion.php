<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/security.php';

$requestId = $_GET['request_id'] ?? 0;

verifyCustomerRequest($pdo, $requestId);


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.workflow_stage,
        r.customer_id,
        s.title AS service_name

    FROM requests r

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE
        r.id = ?
");

$stmt->execute([
    $requestId
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    die('Request not found.');
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
    | Customer Confirms Service Was Completed
    |--------------------------------------------------------------------------
    */

    if ($confirmation === 'completed') {

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Service Completed',
                job_status = 'Completed',
                completed_at = NOW(),
                review_type = NULL
            WHERE
                id = ?
                AND customer_id = ?
                AND workflow_stage = ?
        ");

        $update->execute([
            $requestId,
            $_SESSION['customer'],
            AWAITING_CUSTOMER_CONFIRMATION
        ]);

        if ($update->rowCount() !== 1) {

            die(
                'The service confirmation could not be processed because the request status changed.'
            );
        }

        RequestEventHelper::add(
            $pdo,
            (int) $requestId,
            'SERVICE_COMPLETION_CONFIRMED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Completion Confirmed',
            'The customer confirmed that the service was completed successfully.',
            RequestEventHelper::SOURCE_CUSTOMER,
            (int) $_SESSION['customer'],
            true
        );

        $_SESSION['success'] =
            'Thank you. The service completion has been confirmed.';

        header(
            'Location: ?page=customer-requests'
        );

        exit;
    }

        /*
    |--------------------------------------------------------------------------
    | Customer Reports Service Was Not Completed
    |--------------------------------------------------------------------------
    */

    if ($confirmation === 'not_completed') {

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Needs Admin Review',
                job_status = 'Needs Admin Review',
                review_type = 'service_not_completed'
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

            die(
                'The service response could not be processed because the request status changed.'
            );
        }

        RequestEventHelper::add(
            $pdo,
            (int) $requestId,
            'SERVICE_NOT_COMPLETED_CONFIRMED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Not Completed',
            'The customer reported that the service was not completed after the administrator accepted the agent explanation.',
            RequestEventHelper::SOURCE_CUSTOMER,
            (int) $_SESSION['customer'],
            true
        );

        $_SESSION['success'] =
            'Thank you. Your response has been sent to the administrator for review.';

        header(
            'Location: ?page=customer-requests'
        );

        exit;
    }
}

require VIEW_PATH . '/layouts/header-customer.php';
?>

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header bg-info text-white">

            <h5 class="mb-0">
                Service Completion Confirmation
            </h5>

        </div>

        <div class="card-body">

            <p>

                The agent has reported that the following service
                has been completed:

            </p>

            <div class="alert alert-light border">

                <strong>
                    <?= htmlspecialchars($request['service_name']) ?>
                </strong>

            </div>

            <p class="mb-4">

                Please confirm whether your service was completed
                successfully.

            </p>


            <form method="POST">

                <div class="d-grid gap-2">

                    <button
                        type="submit"
                        name="customer_confirmation"
                        value="completed"
                        class="btn btn-success">

                        Yes, Service Was Completed

                    </button>


                    <button
                        type="submit"
                        name="customer_confirmation"
                        value="not_completed"
                        class="btn btn-danger">

                        No, Service Was Not Completed

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>