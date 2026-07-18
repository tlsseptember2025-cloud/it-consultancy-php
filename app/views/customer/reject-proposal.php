<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

$requestId = $_GET['request_id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Proposal Rejected'
    WHERE id = ?
");

$stmt->execute([$requestId]);

header('Location: ?page=customer-requests');
exit;