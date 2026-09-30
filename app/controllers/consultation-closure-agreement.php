<?php

$pageTitle = 'Consultation Closure Agreement';

require_once APP_PATH . '/helpers/RequestEventHelper.php';


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

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

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

    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
|
| The request must belong to the authenticated customer.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.*,
            c.name AS customer_name,
            s.title AS service_name

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?


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
            r.*,
            c.name AS customer_name,
            s.title AS service_name

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id

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
    !== 'Closure Agreement Sent'
) {

    die(
        'This request does not currently have a Closure Agreement awaiting customer response.'
    );
}


$errors = [];
$typedName = '';


/*
|--------------------------------------------------------------------------
| Process Agreement Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | The customer view uses:
    |
    | name="typed_name"
    |
    | Therefore read typed_name here.
    */

    $typedName = trim(
        $_POST['typed_name'] ?? ''
    );

    $agreementAccepted =
        isset($_POST['agreement_accepted']);

    $errors = [];


    /*
    |--------------------------------------------------------------------------
    | Validate Confirmation Name
    |--------------------------------------------------------------------------
    */

    if ($typedName === '') {

        $errors[] =
            'Please enter the confirmation name.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Agreement Checkbox
    |--------------------------------------------------------------------------
    */

    if (!$agreementAccepted) {

        $errors[] =
            'Please confirm the agreement before submitting.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Customer Name
    |--------------------------------------------------------------------------
    */

    if (
        $typedName !== ''
        &&
        strtoupper($typedName)
        !== strtoupper(
            trim($request['customer_name'])
        )
    ) {

        $errors[] =
            'Please type the customer name exactly as shown.';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing Agreement
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        if ($isDemoCustomer) {

            $stmt = $db->prepare("
                SELECT cca.id

                FROM consultation_closure_agreements cca

                INNER JOIN customers c
                    ON c.id = cca.customer_id
                   AND c.demo_tenant_id = ?
                   AND c.is_demo_account = 1

                INNER JOIN requests r
                    ON r.id = cca.request_id
                   AND r.customer_id = c.id

                WHERE cca.request_id = ?
                  AND cca.customer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $demoTenantId,
                $requestId,
                $customerId
            ]);

        } else {

            $stmt = $db->prepare("
                SELECT id
                FROM consultation_closure_agreements
                WHERE request_id = ?
                  AND customer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $requestId,
                $customerId
            ]);
        }

        $existingAgreement =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if ($existingAgreement) {

            $errors[] =
                'A closure agreement has already been submitted for this request.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Agreement
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Closure Agreement
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                INSERT INTO consultation_closure_agreements
                (
                    request_id,
                    customer_id,
                    typed_name,
                    agreement_accepted,
                    signed_at,
                    ip_address
                )
                VALUES
                (
                    ?, ?, ?, ?, NOW(), ?
                )
            ");

            $stmt->execute([
                $request['id'],
                $customerId,
                $typedName,
                1,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Request Workflow
            |--------------------------------------------------------------------------
            */

            if ($isDemoCustomer) {

                $stmt = $db->prepare("
                    UPDATE requests r

                    INNER JOIN customers c
                        ON c.id = r.customer_id
                       AND c.demo_tenant_id = ?
                       AND c.is_demo_account = 1

                    INNER JOIN services s
                        ON s.id = r.service_id
                       AND s.demo_tenant_id = ?


                    SET
                        r.workflow_stage = 'Closure Agreement Submitted'

                    WHERE r.id = ?
                      AND r.customer_id = ?
                      AND r.workflow_stage = ?
                ");

                $stmt->execute([
                    $demoTenantId,
                    $demoTenantId,
                    $requestId,
                    $customerId,
                    'Closure Agreement Sent'
                ]);

            } else {

                $stmt = $db->prepare("
                    UPDATE requests
                    SET
                        workflow_stage = 'Closure Agreement Submitted'

                    WHERE id = ?
                      AND customer_id = ?
                      AND workflow_stage = ?
                ");

                $stmt->execute([
                    $requestId,
                    $customerId,
                    'Closure Agreement Sent'
                ]);
            }


            if ($stmt->rowCount() !== 1) {

                throw new Exception(
                    'The request status changed before the closure agreement could be submitted.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Record Customer Closure Agreement Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::addCurrentUser(
                $db,
                $requestId,
                RequestEventHelper::EVENT_CUSTOMER_CLOSURE_AGREEMENT_SUBMITTED,
                RequestEventHelper::TYPE_CONTACT,
                'Customer Closure Agreement Submitted',
                'The customer submitted and accepted the consultation closure agreement.',
                true
            );


            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | PRG Redirect
            |--------------------------------------------------------------------------
            */

            $_SESSION['success'] =
                'Closure Agreement submitted successfully.';

            header(
                'Location: ?page=customer-requests'
            );

            exit;

        } catch (Exception $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }

            $errors[] =
                'The closure agreement could not be submitted. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Customer View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/customer/consultation-closure-agreement.php';