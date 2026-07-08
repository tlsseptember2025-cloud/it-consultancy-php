<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

blockDemoAction(
    'Deleting a payment is disabled in the online demo.',
    '?page=requests'
);

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    DELETE FROM payments
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=payments");
exit;