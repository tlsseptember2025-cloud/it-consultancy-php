<?php

$pageTitle = 'Complete Consultation Closure';

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM consultation_closure_agreements
    WHERE request_id = ?
      AND status = 'Approved'
    LIMIT 1
");

$stmt->execute([
    $requestId
]);

$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['confirm_closure'])) {

        die('Please confirm the consultation closure.');

    }

    try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = ?,
            job_status = ?,
            completed_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        'Closed',
        'Completed',
        $requestId
    ]);

    $pdo->commit();

    header('Location: index.php?page=approved-closures&success=closure_completed');
    exit;

} catch (Exception $e) {

    $pdo->rollBack();

    die($e->getMessage());

}

    exit;

}

require VIEW_PATH . '/admin/complete-consultation-closure.php';