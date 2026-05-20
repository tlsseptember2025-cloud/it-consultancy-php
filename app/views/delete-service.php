<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    DELETE FROM services
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=services-admin');
exit;