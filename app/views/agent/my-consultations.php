<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=agent-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$agentId = $_SESSION['agent']['id'];

$stmt = $pdo->prepare("
    SELECT

        cb.id,

        r.id AS request_id,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,

        cs.slot_time,

        r.job_status

    FROM consultation_bookings cb

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    INNER JOIN requests r
        ON r.id = cb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE cb.agent_id = ?

    ORDER BY
        cs.slot_date,
        cs.slot_time
");

$stmt->execute([$agentId]);

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>

            My Consultations

        </h2>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>Date</th>

                            <th>Time</th>

                            <th>Customer</th>

                            <th>Service</th>

                            <th>Status</th>

                            <th width="120">Action</th>

                        </tr>

                    </thead>

                    <tbody>

<?php if (empty($consultations)): ?>

<tr>

    <td colspan="6" class="text-center text-muted py-4">

        No consultations assigned.

    </td>

</tr>

<?php else: ?>

<?php foreach ($consultations as $consultation): ?>

<tr>

    <td>

        <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

    </td>

    <td>

        <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

    </td>

    <td>

        <?= htmlspecialchars($consultation['customer_name']) ?>

    </td>

    <td>

        <?= htmlspecialchars($consultation['service_name']) ?>

    </td>

    <td>

        <?= htmlspecialchars($consultation['job_status']) ?>

    </td>

    <td>

        <a
            href="?page=view-consultation&id=<?= $consultation['request_id'] ?>"
            class="btn btn-primary btn-sm">

            Open

        </a>

    </td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>