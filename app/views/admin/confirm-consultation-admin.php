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
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        <strong>Date:</strong>
        {$request['slot_date']}<br>

        <strong>Time:</strong>
        {$request['slot_time']}<br>

        <strong>Method:</strong>
        {$request['consultation_method']}
    </p>

    <p>
        Please log in to your customer portal on the scheduled date and join your consultation.
    </p>

    <p>
        <a
            href='https://ramiphp.com/?page=public-login'
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