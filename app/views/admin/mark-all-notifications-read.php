<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE recipient_type = 'admin'
      AND is_read = 0
");

$stmt->execute();

header('Location: ?page=notifications');
exit;