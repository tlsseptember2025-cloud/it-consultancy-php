<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$refundId = (int) ($_GET['id'] ?? 0);

// Get refund details
$stmt = $pdo->prepare("
    SELECT *
    FROM refunds
    WHERE id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    die('Refund not found.');
}

// Mark refund as completed
$stmt = $pdo->prepare("
    UPDATE refunds
    SET status = 'completed'
    WHERE id = ?
");

$stmt->execute([$refundId]);

header("Location: ?page=refunds");
exit;