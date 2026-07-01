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

if (!$data) {
    die('Payment slip not found.');
}

/*
|--------------------------------------------------------------------------
| Return request to Proposal Accepted
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = WF_PROPOSAL_ACCEPTED
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
