<?php

require dirname(__DIR__, 2) . '/config/database.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("
    UPDATE messages
    SET is_closed = 1
    WHERE reply_token = ?
");

$stmt->execute([$token]);

header(
    "Location: ?page=visitor-message&token=" .
    urlencode($token)
);

exit;