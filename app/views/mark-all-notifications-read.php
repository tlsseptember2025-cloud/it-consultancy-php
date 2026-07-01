<?php

require_once __DIR__ . '/../helpers/auth.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE recipient_type = 'admin'
      AND is_read = 0
");

$stmt->execute();

header('Location: ?page=notifications');
exit;