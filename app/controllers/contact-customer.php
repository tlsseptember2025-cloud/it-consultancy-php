<?php

if (!isset($_SESSION['agent'])) {
    header('Location: ?page=login');
    exit;
}

$agent = $_SESSION['agent'];

$requestId = (int) ($_GET['request_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,
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

$currentAttempt = min(
    $request['contact_attempts'] + 1,
    MAX_CONTACT_ATTEMPTS
);

$remainingAttempts = max(
    MAX_CONTACT_ATTEMPTS - $request['contact_attempts'],
    0
);

if ($request['workflow_stage'] !== 'Customer Contact') {
    die('Invalid workflow.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contactResult    = trim($_POST['contact_result'] ?? '');
    $agentNotes       = trim($_POST['agent_notes'] ?? '');
    $customerDecision = trim($_POST['customer_decision'] ?? '');

    if ($contactResult === '' || $agentNotes === '') {
        die('Please complete all required fields.');
    }

    if (
        $contactResult === 'Customer Answered'
        && $customerDecision === ''
    ) {
        die('Please select the customer decision.');
    }

    $workflowStage = $request['workflow_stage'];
    $jobStatus     = '';
    $reviewType    = null;

    switch ($contactResult) {

        case 'No Answer':
            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Could Not Complete';
            $reviewType = 'customer_contact';
            break;

        case 'Wrong Number':
            $workflowStage = 'Needs Admin Review';
            $jobStatus = 'Could Not Complete';
            $reviewType = 'customer_contact';
            break;

        case 'Customer Answered':

            if ($customerDecision === 'Continue Current Appointment') {

                $workflowStage = 'Consultation Confirmed';
                $jobStatus = 'In Progress';

            } elseif (
                $customerDecision === 'Continue New Appointment'
                || $customerDecision === 'Close Request'
            ) {

                $workflowStage = 'Needs Admin Review';
                $jobStatus = 'Completed';
                $reviewType = 'customer_contact';

            }

            break;
    }

    $contactAttempts = $request['contact_attempts'];

    if ($contactResult === 'No Answer') {
        $contactAttempts = min(
            $contactAttempts + 1,
            MAX_CONTACT_ATTEMPTS
        );
    }

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

    header('Location: ?page=agent-consultations&success=contact-saved');
    exit;
}

require VIEW_PATH . '/agent/contact-customer.php';