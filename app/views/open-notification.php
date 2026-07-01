<?php

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? 0;

// Get the notification
$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE id = ?
");

$stmt->execute([$id]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    header("Location: ?page=dashboard");
    exit;
}

// Mark as read
$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
");

$stmt->execute([$id]);

// Redirect to the stored link
header("Location: " . $notification['link']);
exit;