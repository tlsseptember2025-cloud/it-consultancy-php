<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
SELECT
    r.*,
    c.name AS customer_name,
    c.email,
    c.phone,
    s.title AS service_name,
    cs.slot_date,
    cs.slot_time,
    cs.consultation_method
FROM requests r

INNER JOIN customers c
    ON c.id = r.customer_id

INNER JOIN services s
    ON s.id = r.service_id

INNER JOIN consultation_bookings cb
    ON cb.request_id = r.id

INNER JOIN consultation_slots cs
    ON cs.id = cb.slot_id

WHERE r.id = ?
LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

require VIEW_PATH . '/admin/view-awaiting-customer-response.php';