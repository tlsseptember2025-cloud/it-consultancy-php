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

$stmt = $pdo->prepare("
    INSERT INTO payments
    (
        request_id,
        amount,
        status,
        payment_date,
        notes
    )
    VALUES (?, ?, 'Paid', NOW(), ?)
");

$stmt->execute([
    $requestId,
    $request['quoted_price'],
    'Payment approved from deposit slip review'
]);

$stmt = $pdo->prepare("
UPDATE requests
SET
    workflow_stage = 'Awaiting Service Scheduling',
    status = 'Approved'
WHERE id = ?
");

$stmt->execute([
    $requestId
]);

header('Location: ?page=requests');
exit;