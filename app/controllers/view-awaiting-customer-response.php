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

    $decisionText = match ($customerDecision) {

    'continue'   => 'Continue Consultation',

    'reschedule' => 'Reschedule Consultation',

    'cancel'     => 'Cancel Consultation',

    default      => 'Unknown'

};

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

    $stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user']]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$adminId = $admin['id'] ?? null;

addContactHistory(

    $pdo,

    $consultation['id'],

    null,

    $adminId,

    'admin',

    RequestEventHelper::EVENT_CUSTOMER_RESPONSE_RECORDED,

    'Response Method: ' . ucfirst($responseMethod) .
'. Customer Decision: ' . $decisionText .
'. Administrator Notes: ' . $responseNotes

);

RequestEventHelper::add(

    $pdo,

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

    header('Location: ?page=awaiting-customer-response&success=response-recorded');
    exit;
}

require VIEW_PATH . '/admin/view-awaiting-customer-response.php';