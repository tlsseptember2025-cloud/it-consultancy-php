<?php

$id = $_GET['id'];

$stmt = $pdo->prepare("
    UPDATE payment_slips
    SET status = 'Approved'
    WHERE id = ?
");

$stmt->execute([$id]);

$stmt = $pdo->prepare("
    SELECT request_id
    FROM payment_slips
    WHERE id = ?
");

$stmt->execute([$id]);

$requestId = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        r.quoted_price,
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

$stmt->execute([$requestId]);

$request = $stmt->fetch();

header(
    'Location: ?page=add-payment&request_id=' . $requestId
);
exit;