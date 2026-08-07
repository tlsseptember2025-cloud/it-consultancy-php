<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$stmt = $pdo->query("
SELECT
    requests.*,
    customers.name AS customer_name,
    services.title AS service_title,
    agents.name AS agent_name

FROM requests

JOIN customers
    ON customers.id = requests.customer_id

JOIN services
    ON services.id = requests.service_id

LEFT JOIN agents
    ON agents.id = requests.agent_id

WHERE requests.workflow_stage = 'Closed'

ORDER BY requests.completed_at DESC
");

$requests = $stmt->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2 class="mb-4">Closed Requests</h2>

<table class="table table-bordered">

    <thead>

        <tr>

            <th>Request #</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Assigned Agent</th>
            <th>Quoted Price</th>
            <th>Closed On</th>
            <th>Action</th>

        </tr>

    </thead>

    <?php foreach ($requests as $request): ?>

    <tr>

        <td><?= $request['id'] ?></td>

        <td><?= htmlspecialchars($request['customer_name']) ?></td>

        <td><?= htmlspecialchars($request['service_title']) ?></td>

        <td>
            <?= !empty($request['agent_name'])
                ? htmlspecialchars($request['agent_name'])
                : '-' ?>
        </td>

        <td>AED <?= number_format($request['quoted_price'], 2) ?></td>

        <td>
            <?= !empty($request['completed_at'])
                ? date('M d, Y', strtotime($request['completed_at']))
                : '-' ?>
        </td>

        <td>

            <a
                href="index.php?page=review-closed-request&request_id=<?= $request['id'] ?>"
                class="btn btn-info btn-sm">

                Review

            </a>

        </td>

    </tr>

<?php endforeach; ?>

</tbody>

</table>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>