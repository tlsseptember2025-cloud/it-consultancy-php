<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

$id = $_GET['id'] ?? 0;

/*
|--------------------------------------------------------------------------
| Approve Service Schedule
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Service Active',
        status = 'In Progress'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Load Customer & Service Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title,
        ss.service_date,
        ss.service_time

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE r.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

sendEmail(
    $request['email'],
    'Service Booking Confirmed',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your service booking has been confirmed and approved.
    </p>

    <p>
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        <strong>Date:</strong>
        " . date('M d, Y', strtotime($request['service_date'])) . "
    </p>

    <p>
        <strong>Time:</strong>
        " . date('h:i A', strtotime($request['service_time'])) . "
    </p>

    <p>
        We look forward to assisting you at the scheduled time.
    </p>

    <p>
        Kind regards,<br>
        IT Consultancy Team
    </p>
    "
);

/*
|--------------------------------------------------------------------------
| Create Customer Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $pdo,
    'customer',
    $request['customer_id'],
    'Service Scheduled',
    'Your service has been scheduled and confirmed.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;