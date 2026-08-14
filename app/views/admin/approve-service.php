<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Service Active',
        status = 'In Progress'
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=requests');
exit;