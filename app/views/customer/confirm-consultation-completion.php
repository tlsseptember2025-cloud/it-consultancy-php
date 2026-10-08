<?php

/* --------------------------------------------------------------------------
 | CSRF protection for state-changing POST requests
 |-------------------------------------------------------------------------- */
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['customer']) &&
    !isset($_SESSION['demo_customer'])
) {
    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determine Customer Environment
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
    | Verify Demo Customer Tenant Ownership
    |--------------------------------------------------------------------------
    */

    $customerCheck = $db->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $customerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

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


require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Validate Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Consultation Request
|--------------------------------------------------------------------------
|
| Demo requests do not have Demo tenant columns on requests.
| Demo ownership is therefore verified through the linked
| Demo customer and Demo service.
|
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.*,
            s.title AS service_name
        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?
           

        WHERE
            r.id = ?
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
        $customerId
    ]);
}

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

        $db->beginTransaction();

        try {

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
                       

                    SET
                        r.workflow_stage = 'Needs Admin Final Approval',
                        r.job_status = 'Pending',
                        r.review_type = NULL

                    WHERE
                        r.id = ?
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
                    $customerId,
                    'Awaiting Customer Confirmation'
                ]);
            }

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
                $db,
                (int) $requestId,
                'CONSULTATION_COMPLETION_CONFIRMED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Completion Confirmed by Customer',
                'The customer confirmed that the consultation was completed successfully. Final administrator approval is now required before a proposal can be created.',
                RequestEventHelper::SOURCE_CUSTOMER,
                $customerId,
                true
            );


            $db->commit();


            $_SESSION['success'] =
                'Thank you. Your consultation completion confirmation has been sent to the administrator for final approval.';

            header(
                'Location: ?page=customer-requests'
            );

            exit;

        } catch (Exception $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
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

        $db->beginTransaction();

        try {

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
                       

                    SET
                        r.workflow_stage = 'Needs Admin Review',
                        r.job_status = 'Needs Admin Review',
                        r.review_type = 'consultation_not_completed',
                        r.admin_instruction = NULL

                    WHERE
                        r.id = ?
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
                        review_type = 'consultation_not_completed',
                        admin_instruction = NULL
                    WHERE
                        id = ?
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

                throw new Exception(
                    'The consultation response could not be processed because its status changed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Record Customer Response Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::add(
                $db,
                (int) $requestId,
                'CONSULTATION_NOT_COMPLETED_CONFIRMED',
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Not Completed',
                'The customer reported that the consultation was not completed after the administrator accepted the agent explanation.',
                RequestEventHelper::SOURCE_CUSTOMER,
                $customerId,
                true
            );


            $db->commit();


            $_SESSION['success'] =
                'Thank you. Your response has been sent to the administrator for review.';

            header(
                'Location: ?page=customer-requests'
            );

            exit;

        } catch (Exception $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
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

$pageTitle =
    'Consultation Completion Confirmation';

require dirname(__DIR__) .
    '/layouts/header-customer.php';

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
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

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
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

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

<?php
require dirname(__DIR__) .
    '/layouts/footer.php';
?>