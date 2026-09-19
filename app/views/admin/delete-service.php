<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: ?page=services-admin");
    exit;
}

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $servicesPdo = $demoPdo;
} else {
    require CONFIG_PATH . '/database.php';
    $servicesPdo = $pdo;
}

$id = (int) $_GET['id'];

$stmt = $servicesPdo->prepare("
    DELETE FROM services
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=services-admin');
exit;
