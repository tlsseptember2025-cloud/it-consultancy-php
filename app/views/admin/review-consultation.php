<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}

$stmt = $pdo->prepare("
    SELECT
    r.*,
    c.name AS customer_name,
    s.title AS service_title,
    cs.slot_date,
    cs.slot_time,
    cs.consultation_method
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id
    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id
    WHERE r.id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

if (!$request) {

    die('Request not found.');

}

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-0">
            Review Consultation #<?= $requestId ?>
        </h2>

        <p>
            <strong>Workflow Stage:</strong>
            <?= htmlspecialchars($request['workflow_stage']) ?>
        </p>

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($request['customer_name']) ?>
        </p>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($request['service_title']) ?>
        </p>

        <p>
            <strong>Date:</strong>

            <?= $request['slot_date']
                ? date('M d, Y', strtotime($request['slot_date']))
                : 'Not Scheduled' ?>

        </p>

        <p>
            <strong>Time:</strong>

            <?= $request['slot_time']
                ? date('h:i A', strtotime($request['slot_time']))
                : 'Not Scheduled' ?>

        </p>

        <p>
            <strong>Method:</strong>

            <?= htmlspecialchars($request['consultation_method'] ?? 'Not Selected') ?>

        </p>

        <hr>

<?php if (!empty($consultation['agent_notes'])): ?>

    <hr>

    <h4>Agent Consultation Notes</h4>

    <div class="alert alert-light border">
        <?= nl2br(htmlspecialchars($consultation['agent_notes'])) ?>
    </div>

<?php endif; ?>

        <div class="mt-4 d-flex gap-2">

    
    <?php if ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

    <a
        href="?page=confirm-consultation-booking&id=<?= $request['id'] ?>"
        class="btn btn-success">

        Approve Schedule

    </a>

    <a
        href="?page=reject-consultation&id=<?= $request['id'] ?>"
        class="btn btn-danger">

        Reject Schedule

    </a>

<?php elseif ($request['workflow_stage'] === 'Needs Admin Review'): ?>

    <a
        href="?page=approve-consultation&id=<?= $request['id'] ?>"
        class="btn btn-success">

        Complete Consultation

    </a>

    <a
        href="?page=reject-consultation&id=<?= $request['id'] ?>"
        class="btn btn-danger">

        Return to Agent

    </a>

<?php endif; ?>

    <a href="?page=requests"
       class="btn btn-secondary">
        Back
    </a>

</div>

    

</div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>