<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET workflow_stage = 'Consultation Confirmed'
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=requests');
exit;