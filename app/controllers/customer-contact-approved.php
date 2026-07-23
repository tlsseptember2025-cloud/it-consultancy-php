<?php

if (!isset($_SESSION['agent'])) {
    header('Location: ?page=login');
    exit;
}

$agent = $_SESSION['agent'];

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name

    FROM requests r

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id
    WHERE
        cb.agent_id = ?
        AND r.workflow_stage = ?
");

$stmt->execute([
    $agent['id'],
    'Customer Contact Approved'
]);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/agent/customer-contact-approved.php';