<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/contact_history_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
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
LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user']]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$adminId = $admin['id'] ?? null;

if (isset($_POST['save_customer_response'])) {

    $responseMethod = trim($_POST['response_method']);
    $customerDecision = trim($_POST['customer_decision']);
    $responseNotes = trim($_POST['response_notes']);

    if (
        $responseMethod === '' ||
        $customerDecision === '' ||
        $responseNotes === ''
    ) {

        die('All fields are required.');

    }

    switch ($customerDecision) {

        case 'continue':

            $workflowStage = 'Consultation Confirmed';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Responded - Continue Consultation';

            break;

        case 'reschedule':

            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Requested Reschedule';

            break;

        case 'cancel':

            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Pending';
            $eventTitle = 'Customer Requested Cancellation';

            break;

        default:

            die('Invalid customer decision.');

    }

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = ?,
            job_status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $workflowStage,
        $jobStatus,
        $consultation['id']
    ]);

addContactHistory(

    $pdo,

    $consultation['id'],

    null,

    $adminId,

    'admin',

    RequestEventHelper::EVENT_CUSTOMER_RESPONSE_RECORDED,

    'Customer responded via ' . $responseMethod .
    '. Decision: Customer Requested Cancellation. ' .
    'Administrator Notes: ' . $responseNotes

);

RequestEventHelper::add(

    $pdo,

    $consultation['id'],

    'CUSTOMER_RESPONSE_RECORDED',

    RequestEventHelper::TYPE_CONTACT,

    $eventTitle,

    $responseNotes,

    RequestEventHelper::SOURCE_ADMINISTRATOR,

    $adminId

);

    header('Location: ?page=awaiting-customer-response&success=response-recorded');
    exit;
}

if (isset($_POST['continue_consultation'])) {

    $consultationDateTime = strtotime(
        $consultation['slot_date'] . ' ' . $consultation['slot_time']
    );

    $currentDateTime = time();

    if ($currentDateTime > $consultationDateTime) {

        // Consultation has expired.
        // Next step: redirect the customer to the existing
        // reschedule consultation workflow.

        echo "Consultation has expired.";
        exit;

    } else {

        // Consultation is still valid.

        $stmt = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Consultation Confirmed',
                job_status = 'Pending'
            WHERE id = ?
        ");

        $stmt->execute([$requestId]);

        addContactHistory(

            $pdo,

            $consultation['id'],

            null,

            $adminId,

            'admin',

            RequestEventHelper::EVENT_CONSULTATION_CONFIRMED,

            'Administrator approved continuation of the consultation.'

        );

        RequestEventHelper::add(

            $pdo,

            $consultation['id'],

            RequestEventHelper::EVENT_CONSULTATION_CONFIRMED,

            RequestEventHelper::TYPE_CONSULTATION,

            'Consultation Continued',

            'Administrator approved continuation of the consultation.',

            RequestEventHelper::SOURCE_ADMINISTRATOR,

            $adminId

        );

        header('Location: ?page=requests&success=consultation-continued');
        exit;

    }

}

require VIEW_PATH . '/admin/review-cancellation-request.php';