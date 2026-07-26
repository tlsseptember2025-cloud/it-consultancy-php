<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/notifications.php';

$refundId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM refund_requests
    WHERE id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

echo '<pre>';
var_dump($refundId);
var_dump($refund);
echo '</pre>';
exit;

$stmt = $pdo->prepare("
    SELECT
        rr.*,

        r.id AS request_id,
        r.workflow_stage,

        c.name,
        c.email,
        c.phone,

        s.title AS service_title,

        sb.slot_id,

        ss.service_date,
        ss.service_time

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE rr.id = ?
");

$stmt->execute([$refundId]);

$refund = $stmt->fetch();

if (!$refund) {
    die('Refund request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $decision = $_POST['decision'] ?? '';
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if ($decision === 'reject' && $reviewNotes === '') {

        $error = 'Please provide a reason for rejecting this refund request.';

    }

}