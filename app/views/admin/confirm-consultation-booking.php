<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/meeting.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

/*
|--------------------------------------------------------------------------
| Confirm Consultation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Consultation Confirmed'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::add(
    $pdo,
    $id,
    'CONSULTATION_CONFIRMED',
    RequestEventHelper::TYPE_SYSTEM,
    'Consultation Confirmed',
    'The consultation appointment was confirmed by the administrator.',
    RequestEventHelper::SOURCE_ADMINISTRATOR,
    null
);

/*
|--------------------------------------------------------------------------
| Load Customer & Consultation Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.id = ?
");

$stmt->execute([$id]);
$request = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Generate Meeting Link
|--------------------------------------------------------------------------
*/

$meetingLink = getMeetingLink(
    $request['consultation_method'],
    $request['slot_time']
);

/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

sendEmail(
    $request['email'],
    'Consultation Confirmed',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your consultation has been confirmed.
    </p>

    <p>
        <strong>Service:</strong><br>
        {$request['service_title']}
    </p>

    <p>
        <strong>Date:</strong><br>
        " . formatDate($request['slot_date']) . "
    </p>

    <p>
        <strong>Time:</strong><br>
        " . formatTime($request['slot_time']) . "
    </p>

    <p>
        <strong>Method:</strong><br>
        {$request['consultation_method']}
    </p>

    <hr style='border:none;border-top:1px solid #dddddd;margin:20px 0;'>

<h3 style='margin:0 0 12px 0;font-size:20px;'>

    🔒 Secure Meeting Access

</h3>

<p>

    Your secure
    <strong>{$request['consultation_method']}</strong>
    meeting link will become available
    <strong>10 minutes before your scheduled consultation.</strong>

</p>

<p>

Please log in to your customer portal
and select
<strong>Join Meeting</strong>
when it becomes available.

</p>

<hr style='border:none;border-top:1px solid #dddddd;margin:30px 0;'>


    <p>
    You can manage your consultation, view updates, and access your meeting from your customer portal.
    </p>

    <p>
        <a
            href='" . APP_URL . "/?page=public-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '>

            Customer Portal

        </a>
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);

/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $pdo,
    'customer',
    $request['customer_id'],
    'Consultation Confirmed',
    'Your consultation has been confirmed.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;