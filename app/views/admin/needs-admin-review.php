<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->prepare("
    SELECT

        r.id,

        r.job_status,
        r.incomplete_reason,
        r.completed_at,

        c.name AS customer_name,

        a.name AS agent_name,

        s.title AS service_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE r.job_status = 'Needs Admin Review'

    ORDER BY r.completed_at DESC
");

$stmt->execute();

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);


require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <h2 class="mb-1">

    Needs Admin Review

</h2>

<p class="text-muted mb-4">

    Consultations that require an administrator's decision.

</p>

   <?php if (empty($consultations)): ?>

    <div class="alert alert-success">

        There are currently no consultations waiting for administrator review.

    </div>

<?php else: ?>

<div class="card shadow-sm">

    <div class="card-body p-0">

        <table class="table table-hover mb-0">

            <thead class="table-dark">

                <tr>

                    <th>Request</th>
                    <th>Customer</th>
                    <th>Agent</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($consultations as $consultation): ?>

                <tr>

                    <td>#<?= $consultation['id'] ?></td>

                    <td><?= htmlspecialchars($consultation['customer_name']) ?></td>

                    <td><?= htmlspecialchars($consultation['agent_name']) ?></td>

                    <td><?= htmlspecialchars($consultation['service_name']) ?></td>

<td>

<?php

$statusClass = 'bg-warning text-dark';

if ($consultation['job_status'] == 'Completed') {
    $statusClass = 'bg-success';
}

?>

<span class="badge <?= $statusClass ?>">

    <?= htmlspecialchars($consultation['job_status']) ?>

</span>

</td>

<td>

    <?= htmlspecialchars($consultation['incomplete_reason']) ?>

</td>

<td>

    <?= date('d M Y', strtotime($consultation['completed_at'])) ?>

    <br>

    <small class="text-muted">

        <?= date('h:i A', strtotime($consultation['completed_at'])) ?>

    </small>

</td>

                    <td>

                       <a
    href="?page=admin-review-consultation&id=<?= $consultation['id'] ?>"
    class="btn btn-primary btn-sm">

    Review →

</a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>