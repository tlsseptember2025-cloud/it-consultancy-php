<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/security.php';


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if (
        $customerId <= 0 ||
        $demoTenantId <= 0
    ) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            id,
            demo_tenant_id,
            is_demo_account
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

    $customer = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$customer) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;

    $customerId = (int) (
        $_SESSION['customer']['id'] ?? 0
    );

    if ($customerId <= 0) {

        unset($_SESSION['customer']);

        header('Location: ?page=public-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

if ($requestId <= 0) {

    $_SESSION['error'] =
        'Invalid request.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
|
| Demo:
| - Customer must belong to the active Demo tenant.
| - Service must belong to the active Demo tenant.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.id,
            r.workflow_stage,
            r.customer_id,
            s.title AS service_name

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?
           AND s.is_demo_account = 1

        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $demoTenantId,
        $demoTenantId,
        $requestId,
        $customerId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            r.id,
            r.workflow_stage,
            r.customer_id,
            s.title AS service_name

        FROM requests r

        INNER JOIN services s
            ON s.id = r.service_id

        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}

$request = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$request) {

    $_SESSION['error'] =
        'Request not found.';

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
    | Customer Confirms Service Was Completed
    |--------------------------------------------------------------------------
    */

    if ($confirmation === 'completed') {

        if ($isDemoCustomer) {

            $update = $db->prepare("
                UPDATE requests r

                INNER JOIN customers c
                    ON c.id = r.customer_id
                   AND c.demo_tenant_id = ?
                   AND c.is_demo_account = 1

                INNER JOIN services s
                    ON s.id = r.service_id
                   AND s.demo_tenant_id = ?
                   AND s.is_demo_account = 1

                SET
                    r.workflow_stage = 'Service Completed',
                    r.job_status = 'Completed',
                    r.completed_at = NOW(),
                    r.review_type = NULL

                WHERE r.id = ?
                  AND r.customer_id = ?
                  AND r.workflow_stage = ?
            ");

            $update->execute([
                $demoTenantId,
                $demoTenantId,
                $requestId,
                $customerId,
                'Awaiting Customer Confirmation'
            ]);

        } else {

            $update = $db->prepare("
                UPDATE requests
                SET
                    workflow_stage = 'Service Completed',
                    job_status = 'Completed',
                    completed_at = NOW(),
                    review_type = NULL

                WHERE id = ?
                  AND customer_id = ?
                  AND workflow_stage = ?
            ");

            $update->execute([
                $requestId,
                $customerId,
                'Awaiting Customer Confirmation'
            ]);
        }


        if ($update->rowCount() !== 1) {

            die(
                'The service confirmation could not be processed because the request status changed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Record Audit Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $db,
            $requestId,
            'SERVICE_COMPLETION_CONFIRMED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Completion Confirmed',
            'The customer confirmed that the service was completed successfully.',
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

        if ($isDemoCustomer) {

            $update = $db->prepare("
                UPDATE requests r

                INNER JOIN customers c
                    ON c.id = r.customer_id
                   AND c.demo_tenant_id = ?
                   AND c.is_demo_account = 1

                INNER JOIN services s
                    ON s.id = r.service_id
                   AND s.demo_tenant_id = ?
                   AND s.is_demo_account = 1

                SET
                    r.workflow_stage = 'Needs Admin Review',
                    r.job_status = 'Needs Admin Review',
                    r.review_type = 'service_not_completed'

                WHERE r.id = ?
                  AND r.customer_id = ?
                  AND r.workflow_stage = ?
            ");

            $update->execute([
                $demoTenantId,
                $demoTenantId,
                $requestId,
                $customerId,
                'Awaiting Customer Confirmation'
            ]);

        } else {

            $update = $db->prepare("
                UPDATE requests
                SET
                    workflow_stage = 'Needs Admin Review',
                    job_status = 'Needs Admin Review',
                    review_type = 'service_not_completed'

                WHERE id = ?
                  AND customer_id = ?
                  AND workflow_stage = ?
            ");

            $update->execute([
                $requestId,
                $customerId,
                'Awaiting Customer Confirmation'
            ]);
        }


        if ($update->rowCount() !== 1) {

            die(
                'The service response could not be processed because the request status changed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Record Audit Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $db,
            $requestId,
            'SERVICE_NOT_COMPLETED_CONFIRMED',
            RequestEventHelper::TYPE_SERVICE,
            'Service Not Completed',
            'The customer reported that the service was not completed after the administrator accepted the agent explanation.',
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


/*
|--------------------------------------------------------------------------
| Customer View
|--------------------------------------------------------------------------
*/

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
                    <?= htmlspecialchars(
                        $request['service_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
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