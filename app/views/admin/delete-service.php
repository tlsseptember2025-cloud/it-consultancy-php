<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: ?page=services-admin");
    exit;
}

require dirname(__DIR__, 3) . '/config/database.php';

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("
    DELETE FROM services
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=services-admin');
exit;
