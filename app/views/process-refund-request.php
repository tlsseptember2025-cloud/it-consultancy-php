<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!in_array($action, ['approve', 'reject'], true)) {
    die('Invalid action.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM refund_requests
    WHERE id = ?
");

$stmt->execute([$id]);

$refundRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refundRequest) {
    die('Refund request not found.');
}

if ($refundRequest['status'] !== 'Pending') {
    header("Location: ?page=refund-requests");
    exit;
}

if ($action === 'reject') {

    $stmt = $pdo->prepare("
        UPDATE refund_requests
        SET status = 'Rejected'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: ?page=refund-requests");
    exit;
}

/*
|--------------------------------------------------------------------------
| APPROVE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO refunds
    (
        request_id,
        amount,
        refund_date,
        reason
    )
    VALUES
    (
        ?,
        0,
        NOW(),
        ?
    )
");

$stmt->execute([
    $refundRequest['request_id'],
    $refundRequest['reason_type']
]);

$stmt = $pdo->prepare("
    UPDATE refund_requests
    SET status = 'Approved'
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: ?page=refund-requests");
exit;