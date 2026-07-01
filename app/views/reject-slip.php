<?php

require_once __DIR__ . '/../helpers/email.php';
require_once __DIR__ . '/../helpers/notifications.php';

$id = $_GET['id'] ?? 0;

/*
|--------------------------------------------------------------------------
| Reject the payment slip
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE payment_slips
    SET status = 'Rejected'
    WHERE id = ?
");

$stmt->execute([$id]);

/*
|--------------------------------------------------------------------------
| Get request and customer details
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ps.request_id,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title
    FROM payment_slips ps
    JOIN customers c
        ON c.id = ps.customer_id
    JOIN requests r
        ON r.id = ps.request_id
    JOIN services s
        ON s.id = r.service_id
    WHERE ps.id = ?
");

$stmt->execute([$id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

<<<<<<< HEAD
if (!$data) {
    die('Payment slip not found.');
}
=======
sendEmail(
    $slip['email'],
    'Payment Rejected',
    "
    <h2>Hello {$slip['name']},</h2>

    <p>We reviewed the payment slip you submitted for:</p>

    <p><strong>Service:</strong> {$slip['service_title']}</p>

    <p>Unfortunately, we could not verify the payment.</p>

    <p>Please log in to your account and upload a new deposit slip.</p>

    <p>
        <a
            href='http://ramiphp.com/it-consultancy-php/public/?page=customer-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            Login Now
        </a>
    </p>

    <p>If you believe this is an error, please contact us.</p>

    <p>IT Consultancy Team</p>
    "
);
>>>>>>> fd63020bd82f2bea8d519b8c432465f188af48b4

/*
|--------------------------------------------------------------------------
| Return request to Proposal Accepted
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
<<<<<<< HEAD
    SET workflow_stage = WF_PROPOSAL_ACCEPTED
=======
    SET workflow_stage = 'Proposal Accepted'
>>>>>>> fd63020bd82f2bea8d519b8c432465f188af48b4
    WHERE id = ?
");

$stmt->execute([
    $data['request_id']
]);

/*
|--------------------------------------------------------------------------
| Email customer
|--------------------------------------------------------------------------
*/

sendEmail(
    $data['email'],
    'Payment Slip Rejected',
    "
    <h2>Hello {$data['name']},</h2>

    <p>
        Unfortunately, the payment slip you submitted could not be approved.
    </p>

    <p>
        Please log in to your customer portal and upload a new payment slip so we can continue processing your request.
    </p>

    <p>
        <a href='https://ramiphp.com/?page=customer-login'>
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
| Create customer notification
|--------------------------------------------------------------------------
*/

createNotification(
    $pdo,
    'customer',
    (int) $data['customer_id'],
    'Payment Rejected',
    'Your payment slip was rejected. Please upload a new deposit slip.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;
