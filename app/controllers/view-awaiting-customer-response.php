<?php

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/contact_history_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Admin Context
|--------------------------------------------------------------------------
|
| Normal Admin:
|   $_SESSION['user']
|   Main database ($pdo)
|
| Demo Admin:
|   $_SESSION['demo_user']
|   Demo database ($demoPdo)
|   Restricted to its own demo_tenant_id
|
|--------------------------------------------------------------------------
*/


$isDemoAdmin = isset($_SESSION['demo_user']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    requireDemoAdmin();

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

} else {

    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

$requestPdo = $pdo;

$demoTenantId = null;


/*
|--------------------------------------------------------------------------
| Demo Admin Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $requestPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Demo Tenant
    |--------------------------------------------------------------------------
    */

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($requestId <= 0) {

    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Request / Customer / Consultation
|--------------------------------------------------------------------------
|
| Normal Admin:
|   Loads from the main database.
|
| Demo Admin:
|   Loads only a request belonging to the current Demo tenant.
|
|--------------------------------------------------------------------------
*/

$requestSql = "
    SELECT

        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

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

    WHERE r.id = ?
";


$requestParams = [
    $requestId
];


/*
|--------------------------------------------------------------------------
| Demo Tenant Restriction
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $requestSql .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
    ";

    $requestParams[] = $demoTenantId;
}


$requestSql .= "
    LIMIT 1
";


$stmt = $requestPdo->prepare($requestSql);

$stmt->execute($requestParams);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Request Not Found
|--------------------------------------------------------------------------
*/

if (!$consultation) {

    die('Request not found.');
}


/*
|--------------------------------------------------------------------------
| Save Customer Response
|--------------------------------------------------------------------------
*/

if (isset($_POST['save_customer_response'])) {


    /*
    |--------------------------------------------------------------------------
    | Form Values
    |--------------------------------------------------------------------------
    */

    $responseMethod = trim(
        $_POST['response_method'] ?? ''
    );

    $customerDecision = trim(
        $_POST['customer_decision'] ?? ''
    );

    $responseNotes = trim(
        $_POST['response_notes'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $responseMethod === ''
        || $customerDecision === ''
        || $responseNotes === ''
    ) {

        die('All fields are required.');
    }


    /*
    |--------------------------------------------------------------------------
    | Decision Text
    |--------------------------------------------------------------------------
    */

    $decisionText = match ($customerDecision) {

        'continue'   => 'Continue Consultation',

        'reschedule' => 'Reschedule Consultation',

        'cancel'     => 'Cancel Consultation',

        default      => 'Unknown'
    };


    /*
    |--------------------------------------------------------------------------
    | Determine Workflow
    |--------------------------------------------------------------------------
    */

    switch ($customerDecision) {


        /*
        |--------------------------------------------------------------------------
        | Continue Consultation
        |--------------------------------------------------------------------------
        */

        case 'continue':

            $workflowStage = 'Consultation Confirmed';

            $jobStatus = 'Pending';

            $eventTitle = 'Customer Responded - Continue Consultation';


            RequestEventHelper::addCurrentUser(

                $requestPdo,

                (int) $consultation['id'],

                RequestEventHelper::EVENT_CUSTOMER_CONTINUED_CONSULTATION,

                RequestEventHelper::TYPE_CONTACT,

                'Customer Continued Consultation',

                'The customer responded and confirmed that they want to continue with the consultation.',

                true

            );

            break;


        /*
        |--------------------------------------------------------------------------
        | Reschedule Consultation
        |--------------------------------------------------------------------------
        */

        case 'reschedule':

            $workflowStage = 'Needs Admin Review';

            $jobStatus = 'Pending';

            $eventTitle = 'Customer Requested Reschedule';


            RequestEventHelper::addCurrentUser(

                $requestPdo,

                (int) $consultation['id'],

                RequestEventHelper::EVENT_CUSTOMER_REQUESTED_RESCHEDULE,

                RequestEventHelper::TYPE_CONTACT,

                'Customer Requested Reschedule',

                'The customer responded and requested that the consultation be rescheduled.',

                true

            );

            break;


        /*
        |--------------------------------------------------------------------------
        | Cancel Consultation
        |--------------------------------------------------------------------------
        */

        case 'cancel':

            $workflowStage = 'Needs Admin Review';

            $jobStatus = 'Pending';

            $eventTitle = 'Customer Requested Cancellation';


            RequestEventHelper::addCurrentUser(

                $requestPdo,

                (int) $consultation['id'],

                RequestEventHelper::EVENT_CUSTOMER_REQUESTED_CANCELLATION,

                RequestEventHelper::TYPE_CONTACT,

                'Customer Requested Cancellation',

                'The customer responded and requested cancellation of the consultation.',

                true

            );

            break;


        /*
        |--------------------------------------------------------------------------
        | Invalid Decision
        |--------------------------------------------------------------------------
        */

        default:

            die('Invalid customer decision.');
    }


    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    |
    | Demo Admin:
    |   Update is restricted to the current Demo tenant.
    |
    | Normal Admin:
    |   Existing request update behavior remains unchanged.
    |
    |--------------------------------------------------------------------------
    */

    $updateSql = "
        UPDATE requests r

        INNER JOIN customers c
            ON c.id = r.customer_id

        SET
            r.workflow_stage = ?,
            r.job_status = ?

        WHERE r.id = ?
    ";


    $updateParams = [
        $workflowStage,
        $jobStatus,
        $consultation['id']
    ];


    if ($isDemoAdmin) {

        $updateSql .= "
            AND c.demo_tenant_id = ?
            AND c.is_demo_account = 1
        ";

        $updateParams[] = $demoTenantId;
    }


    $stmt = $requestPdo->prepare($updateSql);

    $stmt->execute($updateParams);


    /*
    |--------------------------------------------------------------------------
    | Determine Administrator ID
    |--------------------------------------------------------------------------
    */

    $adminId = null;


    if ($isDemoAdmin) {

        /*
        | Demo Admin ID is already stored in the Demo session.
        */

        $adminId = (int) (
            $_SESSION['demo_user']['id']
            ?? 0
        );

    } else {

        /*
        | Main Admin continues using the existing
        | email-based session lookup.
        */

        $stmt = $requestPdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $_SESSION['user']
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $adminId = $admin['id'] ?? null;
    }


    /*
    |--------------------------------------------------------------------------
    | Contact History
    |--------------------------------------------------------------------------
    */

    addContactHistory(

        $requestPdo,

        $consultation['id'],

        null,

        $adminId,

        'admin',

        RequestEventHelper::EVENT_CUSTOMER_RESPONSE_RECORDED,

        'Response Method: ' . ucfirst($responseMethod)
        . '. Customer Decision: ' . $decisionText
        . '. Administrator Notes: ' . $responseNotes

    );


    /*
    |--------------------------------------------------------------------------
    | Request Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(

        $requestPdo,

        $consultation['id'],

        'CUSTOMER_RESPONSE_RECORDED',

        RequestEventHelper::TYPE_CONTACT,

        $eventTitle,

        'Response Method: ' . ucfirst($responseMethod) . PHP_EOL .
        'Customer Decision: ' . $decisionText . PHP_EOL .
        'Administrator Notes: ' . $responseNotes,

        RequestEventHelper::SOURCE_ADMINISTRATOR,

        $adminId,

        true

    );


    /*
    |--------------------------------------------------------------------------
    | Return To Awaiting Customer Response
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ?page=awaiting-customer-response&success=response-recorded'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/admin/view-awaiting-customer-response.php';