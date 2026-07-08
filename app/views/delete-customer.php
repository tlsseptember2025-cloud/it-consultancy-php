<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

blockDemoAction(
    'Deleting customers is disabled in the online demo.',
    '?page=customers'
);

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    DELETE FROM customers
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=customers");
exit;

