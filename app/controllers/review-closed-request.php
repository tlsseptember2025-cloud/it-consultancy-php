<?php

$pageTitle = 'Review Closed Request';

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {

    die('Invalid request.');

}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        s.title AS service_name,
        a.name AS agent_name
    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    WHERE r.id = ?

    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM consultation_closure_agreements
    WHERE request_id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {

    die('Request not found.');

}

require VIEW_PATH . '/admin/review-closed-request.php';