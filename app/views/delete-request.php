<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

blockDemoAction(
    'Deleting requests is disabled in the online demo.',
    '?page=requests'
);

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? 0;

$slipStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM payment_slips
    WHERE request_id = ?
");

$slipStmt->execute([$id]);

if ($slipStmt->fetchColumn() > 0) {

    $_SESSION['error'] =
        'Cannot delete request because deposit slips exist.';

} else {

    $stmt = $pdo->prepare("
        DELETE FROM requests
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $_SESSION['success'] =
        'Request deleted successfully.';
}

header("Location: ?page=requests");
exit;