<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=public-login");
    exit;
}

// Block deleting in Demo Mode
blockDemoAction(
    'Deleting services is disabled in the online demo.',
    '?page=services-admin'
);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: ?page=services-admin");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("
    DELETE FROM services
    WHERE id = ?
");

$stmt->execute([$id]);

header('Location: ?page=services-admin');
exit;