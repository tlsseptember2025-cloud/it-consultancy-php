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

header(
    'Location: ?page=add-payment&request_id=' . $requestId
);
exit;