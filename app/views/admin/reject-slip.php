<?php

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';

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

sendEmail(
    $data['email'],
    'Payment Rejected',
    "
    <h2>Hello {$data['name']},</h2>

    <p>We reviewed the payment slip you submitted for:</p>

    <p><strong>Service:</strong> {$data['service_title']}</p>

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

/*
|--------------------------------------------------------------------------
| Return request to Proposal Accepted
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Proposal Accepted',
        status = 'Pending'
    WHERE id = ?
");

$stmt->execute([
    $data['request_id']
]);


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
