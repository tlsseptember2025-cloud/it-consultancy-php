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
        c.email,
        c.phone,

        s.title AS service_title,

        sb.slot_id,

        ss.service_date,
        ss.service_time

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id

    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id

    WHERE r.id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found.');
}
?>

<div class="container mt-4">

    <h2>Review Service</h2>

    <hr>

    <h4>Customer Information</h4>

    <p><strong>Name:</strong> <?= htmlspecialchars($request['name']) ?></p>

    <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>

    <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>

    <hr>

    <h4>Service Information</h4>

    <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

    <p><strong>Date:</strong> <?= date('M d, Y', strtotime($request['service_date'])) ?></p>

    <p><strong>Time:</strong> <?= date('h:i A', strtotime($request['service_time'])) ?></p>

    <p><strong>Status:</strong> <?= htmlspecialchars($request['workflow_stage']) ?></p>

</div>

<hr>

<div class="mt-4">

    <a href="?page=approve-service&id=<?= $request['id'] ?>"
       class="btn btn-success">
        <i class="bi bi-check-circle"></i> Approve Service
    </a>

    <a href="?page=reject-service&id=<?= $request['id'] ?>"
       class="btn btn-danger">
        <i class="bi bi-x-circle"></i> Reject Service
    </a>

    <a href="?page=requests"
       class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>

</div>