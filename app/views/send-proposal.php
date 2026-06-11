<?php

require_once __DIR__ . '/../helpers/email.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        r.*,
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

$stmt->execute([$id]);

$request = $stmt->fetch();

sendEmail(
    $request['email'],
    'Proposal Ready',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your proposal is now ready for review.
    </p>

    <p>
        <strong>Service:</strong>
        {$request['service_title']}
    </p>

    <p>
        <strong>Proposal:</strong>
    </p>

    <p>
        " . nl2br($request['proposal']) . "
    </p>

    <p>
        <strong>Proposed Price:</strong>
        $" . number_format($request['quoted_price'], 2) . "
    </p>

     <p>Please log in and schedule your consultation.</p>

    <p>
        <a
            href='http://localhost/it-consultancy-php/public/?page=customer-login'
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

    <p>
        IT Consultancy Team
    </p>
    "
);

$update = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Proposal Sent'
    WHERE id = ?
");

$update->execute([$id]);

header('Location: ?page=requests');
exit;