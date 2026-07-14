<?php

require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

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
    SET workflow_stage = 'Consultation Approved'
    WHERE id = ?
");

$stmt->execute([$id]);

if ($customer && !empty($customer['email'])) {

    sendEmail(
    $customer['email'],
    'Consultation Approved',
    "
    <h2>Hello {$customer['name']},</h2>

    <p>Your consultation request has been approved.</p>

    <p>Please log in and schedule your consultation.</p>

    <p>
        <a

            href='http://ramiphp.com/?page=customer-login'
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

    <p>IT Consultancy Team</p>
    "
);
}

header('Location: ?page=requests');
exit;