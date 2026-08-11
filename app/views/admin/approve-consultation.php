<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        customers.name,
        customers.email
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    WHERE requests.id = ?
");

$stmt->execute([$id]);

$customer = $stmt->fetch();

$stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Consultation Confirmed'
    WHERE id = ?
");

$stmt->execute([$id]);

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $id,
    'CONSULTATION_REQUEST_APPROVED',
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Request Approved',
    'The administrator approved the consultation request. The customer may now schedule the consultation.',
    true
);

if ($customer && !empty($customer['email'])) {

    sendEmail(
    $customer['email'],
    'Consultation Confirmed',
    "
    <h2>Hello {$customer['name']},</h2>

    <p>Your consultation request has been approved.</p>

    <p>Please log in and schedule your consultation.</p>

    <p>
        <a

            href='localhost/?page=public-login'
            href='localhost/it-consultancy-php/public/?page=public-login'
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

    <p>IT Consultancy Team</p>
    "
);
}

header('Location: ?page=requests');
exit;