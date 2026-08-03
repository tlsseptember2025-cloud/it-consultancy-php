<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

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
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Load Consultation Booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cb.id AS booking_id,
        cb.agent_id,
        a.name AS agent_name,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link
    FROM consultation_bookings cb
    INNER JOIN agents a
        ON a.id = cb.agent_id
    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id
    WHERE cb.request_id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die('Consultation booking not found.');
}

$consultation = array_merge($consultation, $booking);
/*
|--------------------------------------------------------------------------
| Customer Contact Workflow
|--------------------------------------------------------------------------
*/

$contactAttempts = (int)($consultation['contact_attempts'] ?? 0);

$canRetryContact =
    $contactAttempts < MAX_CONTACT_ATTEMPTS;

$maximumAttemptsReached =
    $contactAttempts >= MAX_CONTACT_ATTEMPTS;

/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

if (isset($_POST['approve_contact'])) {

    $adminInstruction = trim($_POST['admin_instruction'] ?? '');

    if ($adminInstruction === '') {
        die('Please enter instructions for the assigned agent.');
    }

    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            admin_instruction = ?,
            workflow_stage = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $adminInstruction,
        'Customer Contact',
        $consultation['id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Get Logged-in Administrator
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['user']]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        die('Administrator not found.');
    }

    $adminId = $admin['id'];

    /*
    |--------------------------------------------------------------------------
    | Save Approval History
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO consultation_customer_contact_approvals
        (
            request_id,
            booking_id,
            agent_id,
            approved_by,
            admin_instruction
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $consultation['id'],
        $consultation['booking_id'],
        $consultation['agent_id'],
        $adminId,
        $adminInstruction
    ]);

    /*
|--------------------------------------------------------------------------
| Log Request Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::add(

    $pdo,

    $consultation['id'],

    'CONTACT_ATTEMPT_APPROVED',

    RequestEventHelper::TYPE_CONTACT,

    'Administrator Approved Customer Contact',

    'The administrator approved another customer contact attempt.',

    RequestEventHelper::SOURCE_ADMINISTRATOR,

    $adminId

);

/*
|--------------------------------------------------------------------------
| Log Request Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::add(

    $pdo,

    $consultation['id'],

    RequestEventHelper::EVENT_CONTACT_ATTEMPT_APPROVED,

    RequestEventHelper::TYPE_CONTACT,

    'Administrator Approved Customer Contact',

    'The administrator approved another customer contact attempt.',

    RequestEventHelper::SOURCE_ADMINISTRATOR,

    $adminId

);

    header('Location: ?page=requests&success=customer-contact-approved');
    exit;
}

require VIEW_PATH . '/admin/admin-contact-customer.php';