<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    DELETE FROM customers
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=customers");
exit;