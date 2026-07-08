<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

blockDemoAction(
    'Deleting messages is disabled in the online demo.',
    '?page=messages'
);

require dirname(__DIR__, 2) . '/config/database.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header("Location: ?page=messages");
exit;