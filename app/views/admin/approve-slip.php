<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'];

/*
|--------------------------------------------------------------------------
| Approve Payment Slip
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE payment_slips
    SET status = 'Approved'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Get Request ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT request_id
    FROM payment_slips
    WHERE id = ?
");

$stmt->execute([$id]);

$requestId = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Load Customer & Request Details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.quoted_price,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    WHERE r.id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Record Payment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO payments
    (
        request_id,
        amount,
        status,
        payment_date,
        notes
    )
    VALUES (?, ?, 'Paid', NOW(), ?)
");

$stmt->execute([
    $requestId,
    $request['quoted_price'],
    'Payment approved from deposit slip review'
]);

/*
|--------------------------------------------------------------------------
| Update Request Workflow
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Awaiting Service Scheduling',
        status = 'Approved'
    WHERE id = ?
");

$stmt->execute([
    $requestId
]);

/*
|--------------------------------------------------------------------------
| Record Payment Approved Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $requestId,
    'PAYMENT_APPROVED',
    RequestEventHelper::TYPE_PAYMENT,
    'Payment Approved',
    'The administrator approved the customer payment receipt.',
    true
);

/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

sendEmail(
    $request['email'],
    'Payment Approved - Schedule Your Service',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        We are pleased to inform you that your payment has been approved.
    </p>

    <p>
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        You can now log in to your account and schedule your service at a convenient date and time.
    </p>

    <p>
        <a
            href='localhost/?page=public-login'
            style='
                background:#198754;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            Schedule Service
        </a>
    </p>

    <p>
        Thank you for choosing our IT Consultancy services.
    </p>

    <p>
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
    'Payment Approved',
    'Your payment has been approved. You may now schedule your service.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;