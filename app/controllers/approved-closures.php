<?php

$pageTitle = 'Approved Closures';

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE r.workflow_stage = ?
    ORDER BY r.id DESC
");

$stmt->execute([
    'Closure Approved'
]);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/admin/approved-closures.php';