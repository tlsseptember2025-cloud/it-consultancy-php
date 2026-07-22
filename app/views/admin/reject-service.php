<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name,
        s.title AS service_title
    FROM requests r
    JOIN customers c ON c.id = r.customer_id
    JOIN services s ON s.id = r.service_id
    WHERE r.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim($_POST['reason']);

    if ($reason === '') {
        die('Rejection reason is required.');
    }

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Rejected',
            service_rejection_reason = ?,
            service_rejected_at = NOW(),
            service_rejected_by = ?,
            service_reschedules = 0
        WHERE id = ?
    ");

    $stmt->execute([
        $reason,
        $_SESSION['user'],
        $id
    ]);

    header('Location: ?page=requests');
    exit;
}

?>

<div class="container mt-4">

    <h2>Reject Service</h2>

    <hr>

    <p><strong>Customer:</strong> <?= htmlspecialchars($request['name']) ?></p>

    <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

    <form method="post">

        <div class="mb-3">
            <label class="form-label">
                Rejection Reason
            </label>

            <textarea
                name="reason"
                class="form-control"
                rows="5"
                required
            ></textarea>
        </div>

        <button type="submit" class="btn btn-danger">
            Reject Service
        </button>

        <a href="?page=review-service&id=<?= $id ?>"
           class="btn btn-secondary">
            Cancel
        </a>

    </form>

</div>