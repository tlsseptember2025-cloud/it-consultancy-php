<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

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

    WHERE requests.workflow_stage = 'Archived'

    ORDER BY requests.archived_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2>Archived Requests</h2>

            <p class="text-muted mb-0">
                Requests retained in the archive.
            </p>
        </div>

        <span class="badge bg-secondary">
            <?= count($requests) ?> Archived
        </span>

    </div>

    <?php if (empty($requests)): ?>

        <div class="alert alert-info">
            No archived requests found.
        </div>

    <?php else: ?>

        <div class="card shadow-sm">

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Agent</th>
                                <th>Completed</th>
                                <th>Archived</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>
                                    #<?= (int) $request['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['customer_name']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['service_title']
                                    ) ?>
                                </td>

                                <td>
                                    <?= $request['agent_name']
                                        ? htmlspecialchars(
                                            $request['agent_name']
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['completed_at'])
                                        ? date(
                                            'd M Y H:i',
                                            strtotime(
                                                $request['completed_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['archived_at'])
                                        ? date(
                                            'd M Y H:i',
                                            strtotime(
                                                $request['archived_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <span class="badge bg-secondary">
                                        Archived
                                    </span>
                                </td>

                                <td>

                                    <a
                                        href="?page=view-archived-request&id=<?= (int) $request['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>