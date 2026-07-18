<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$stmt = $pdo->query("
    SELECT
        requests.*,
        customers.name AS customer_name,
        services.title AS service_title

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

   WHERE requests.workflow_stage = 'Completed'
    ORDER BY requests.completed_at DESC
");

$requests = $stmt->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2 class="mb-4">Archived Requests</h2>

<table class="table table-bordered">

    <thead>

        <tr>

            <th>Customer</th>
            <th>Service</th>
            <th>Quoted Price</th>
            <th>Status</th>
            <th>Completed On</th>

        </tr>

    </thead>

    <tbody>

        <?php foreach ($requests as $request): ?>

            <tr>

                <td><?= htmlspecialchars($request['customer_name']) ?></td>

                <td><?= htmlspecialchars($request['service_title']) ?></td>

                <td>AED <?= number_format($request['quoted_price'], 2) ?></td>

                <td><?= htmlspecialchars($request['status']) ?></td>

                <td>
                    <?= !empty($request['completed_at'])
                        ? date('M d, Y', strtotime($request['completed_at']))
                        : '-' ?>
                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>