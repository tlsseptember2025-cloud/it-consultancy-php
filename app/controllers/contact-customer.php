<?php

require_once APP_PATH . '/helpers/contact_history_helper.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/consultation_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

if (!isset($_SESSION['agent'])) {
    header('Location: ?page=login');
    exit;
}


$agent = $_SESSION['agent'];


$requestId = (int) ($_GET['request_id'] ?? 0);


$stmt = $pdo->prepare("
    SELECT
        r.*,
        cb.agent_id AS consultation_agent_id,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link,
        c.name AS customer_name,
        c.email,
        c.phone,
        s.title AS service_name
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
    $agent['id']
]);


$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {
    die('Request not found.');
}


/*
|--------------------------------------------------------------------------
| Contact Attempt Information
|--------------------------------------------------------------------------
*/

$currentAttempt = min(
    (int) $request['contact_attempts'],
    MAX_CONTACT_ATTEMPTS
);


$remainingAttempts = max(
    0,
    MAX_CONTACT_ATTEMPTS - $currentAttempt
);


/*
|--------------------------------------------------------------------------
| Validate Workflow
|--------------------------------------------------------------------------
*/

if ($request['workflow_stage'] !== 'Customer Contact') {
    die('Invalid workflow.');
}


/*
|--------------------------------------------------------------------------
| Save Customer Contact Result
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contactResult    = trim($_POST['contact_result'] ?? '');
    $agentNotes       = trim($_POST['agent_notes'] ?? '');
    $customerDecision = trim($_POST['customer_decision'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validate Required Fields
    |--------------------------------------------------------------------------
    */

    if ($contactResult === '' || $agentNotes === '') {
        die('Please complete all required fields.');
    }


    if (
        $contactResult === 'Customer Answered'
        && $customerDecision === ''
    ) {
        die('Please select the customer decision.');
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Next Workflow
    |--------------------------------------------------------------------------
    */

    $workflowStage = $request['workflow_stage'];

    $jobStatus  = 'In Progress';

    $reviewType = null;


    switch ($contactResult) {

        case 'No Answer':

            // Customer could not be reached.
            // Administrator must review the next contact action.

            $workflowStage = 'Needs Admin Review';

            $jobStatus = 'In Progress';

            $reviewType = 'customer_contact';

            /*
            |--------------------------------------------------------------------------
            | Record No Answer Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::addCurrentUser(
                $pdo,
                (int) $request['id'],
                RequestEventHelper::EVENT_NO_ANSWER,
                RequestEventHelper::TYPE_CONTACT,
                'No Answer',
                'The assigned agent attempted to contact the customer, but there was no answer.',
                false
            );

            break;    

        case 'Wrong Number':

    // Administrator must review the customer's contact information.

    $workflowStage = 'Needs Admin Review';

    $jobStatus = 'In Progress';

    $reviewType = 'customer_contact';

    /*
    |--------------------------------------------------------------------------
    | Record Wrong Number Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $pdo,
        (int) $request['id'],
        RequestEventHelper::EVENT_WRONG_NUMBER,
        RequestEventHelper::TYPE_CONTACT,
        'Wrong Number',
        'The assigned agent attempted to contact the customer, but the telephone number was incorrect.',
        false
    );

    break;

        /*
        |--------------------------------------------------------------------------
        | Customer Answered
        |--------------------------------------------------------------------------
        */

        case 'Customer Answered':

            /*
            |--------------------------------------------------------------------------
            | Continue Current Appointment
            |--------------------------------------------------------------------------
            */

            if ($customerDecision === 'Continue Current Appointment') {

                $workflowStage = 'Consultation Confirmed';

                $jobStatus = 'In Progress';

                $reviewType = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Continue With New Appointment
            |--------------------------------------------------------------------------
            */

            elseif ($customerDecision === 'Continue New Appointment') {

                // Administrator must arrange the new consultation.

                $workflowStage = 'Needs Admin Review';

                $jobStatus = 'In Progress';

                $reviewType = 'customer_contact';
            }


            /*
            |--------------------------------------------------------------------------
            | Customer Requested Closure
            |--------------------------------------------------------------------------
            */

            elseif ($customerDecision === 'Close Request') {

                // Customer requested closure.
                // Administrator must review and confirm closure.

                $workflowStage = 'Needs Admin Review';

                $jobStatus = 'In Progress';

                $reviewType = 'customer_contact';
            }

            break;
    }

   /*
|--------------------------------------------------------------------------
| Record Customer Answered Event
|--------------------------------------------------------------------------
*/

if ($contactResult === 'Customer Answered') {

    RequestEventHelper::addCurrentUser(
        $pdo,
        (int) $request['id'],
        RequestEventHelper::EVENT_CUSTOMER_ANSWERED,
        RequestEventHelper::TYPE_CONTACT,
        'Customer Answered',
        'The customer answered the contact attempt.',
        false
    );
}


    /*
    |--------------------------------------------------------------------------
    | Record Contact Attempt
    |--------------------------------------------------------------------------
    |
    | Every submitted contact result represents one actual contact attempt.
    |
    */

    $contactAttempts = min(
        (int) $request['contact_attempts'] + 1,
        MAX_CONTACT_ATTEMPTS
    );


    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            job_status = ?,
            workflow_stage = ?,
            review_type = ?,
            contact_notes = ?,
            contact_result = ?,
            contact_attempts = ?
        WHERE id = ?
    ");


    $stmt->execute([
        $jobStatus,
        $workflowStage,
        $reviewType,
        $agentNotes,
        $contactResult,
        $contactAttempts,
        $request['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Save Contact History
    |--------------------------------------------------------------------------
    */

    addContactHistory(
        $pdo,
        $request['id'],
        $request['agent_id'],
        null,
        'phone',
        $contactResult,
        $agentNotes
    );

    /*
|--------------------------------------------------------------------------
| Record Contact Attempt Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $request['id'],
    RequestEventHelper::EVENT_CONTACT_ATTEMPT,
    RequestEventHelper::TYPE_CONTACT,
    'Customer Contact Attempt',
    'The assigned agent attempted to contact the customer.',
    false
);


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header('Location: ?page=agent-consultations&success=contact-saved');

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Agent Customer Contact View
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/agent/contact-customer.php';