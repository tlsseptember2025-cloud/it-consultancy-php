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
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

requireAdminLogin();


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

    $tenantStmt = $requestPdo->prepare(
        "SELECT id
         FROM demo_tenants
         WHERE id = ?
           AND status = 'Active'
           AND (expires_at IS NULL OR expires_at >= CURDATE())
         LIMIT 1"
    );
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $requestPdo->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
           AND is_demo_account = 1
           AND is_super_admin = 0
           AND demo_tenant_id = ?
         LIMIT 1"
    );
    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}


$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));


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

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_customer_response'])
) {

    if (
        !isset($_POST['csrf_token'])
        || !hash_equals($csrfToken, (string) $_POST['csrf_token'])
    ) {
        die('Invalid security token.');
    }

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

    $allowedResponseMethods = [
        'Email',
        'Phone',
        'WhatsApp',
        'Other'
    ];

    $allowedCustomerDecisions = [
        'continue',
        'reschedule',
        'cancel'
    ];

    if (
        !in_array($responseMethod, $allowedResponseMethods, true)
        || !in_array($customerDecision, $allowedCustomerDecisions, true)
        || $responseNotes === ''
    ) {
        die('Invalid customer response data.');
    }

    if (mb_strlen($responseNotes) > 5000) {
        die('Administrator notes are too long.');
    }

    if (
        ($consultation['workflow_stage'] ?? '') !== 'Awaiting Customer Response'
    ) {
        die('This customer response is no longer awaiting review.');
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
          AND r.workflow_stage = 'Awaiting Customer Response'
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

    if ($stmt->rowCount() !== 1) {
        die('The customer response could not be recorded because the request status has changed.');
    }


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

    if (!$adminId) {
        die('Administrator account could not be verified.');
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