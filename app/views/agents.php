<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("
    SELECT *
    FROM agents
    ORDER BY name ASC
");

$agents = $stmt->fetchAll();

require __DIR__ . '/layouts/header.php';
?>

<div class="card shadow-sm">

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-2">

    <h2 class="mb-0">Agents</h2>

    <a href="?page=add-agent"
       class="btn btn-success">

        Add Agent

    </a>

</div>

<p class="text-muted">

    Total Agents:
    <strong><?= count($agents) ?></strong>

</p>

        <?php if (empty($agents)): ?>

            <div class="alert alert-info">

                No agents found.

            </div>

        <?php else: ?>

            <table class="table table-bordered table-hover">

                <thead>

                <tr>

                    <th width="50">#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Position</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>

                </tr>

                </thead>

                <tbody>

<?php $i = 1; ?>

<?php foreach ($agents as $agent): ?>

<tr>

    <td><?= $i++ ?></td>

    <td class="text-nowrap">
        <strong><?= htmlspecialchars($agent['name']) ?></strong>
    </td>

    <td><?= htmlspecialchars($agent['email']) ?></td>

    <td>

        <?= !empty($agent['phone'])
            ? htmlspecialchars($agent['phone'])
            : '<span class="text-muted fst-italic">Not Set</span>' ?>

    </td>

    <td>

        <?= !empty($agent['position'])
            ? htmlspecialchars($agent['position'])
            : '<span class="text-muted fst-italic">Not Set</span>' ?>

    </td>

    <td class="text-center">

        <?php if ($agent['status'] === 'Active'): ?>

            <span class="badge rounded-pill bg-success">

                Active

            </span>

        <?php else: ?>

            <span class="badge rounded-pill bg-secondary">

                Inactive

            </span>

        <?php endif; ?>

    </td>

    <td class="text-center">

        <a
            href="?page=edit-agent&id=<?= $agent['id'] ?>"
            class="btn btn-warning btn-sm px-3">

            Edit

        </a>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>