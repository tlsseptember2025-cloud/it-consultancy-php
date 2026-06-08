<?php

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE payment_slips
    SET status = 'Rejected'
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=deposit-slips');
exit;