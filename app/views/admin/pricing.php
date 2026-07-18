<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
    SELECT
        price_list.*,
        services.title AS service_title
    FROM price_list
    INNER JOIN services
        ON price_list.service_id = services.id
    ORDER BY services.title ASC
");

$prices = $stmt->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Pricing</h2>

        <a href="?page=add-pricing" class="btn btn-primary">
            Add Price
        </a>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <p class="mb-0">
                <?php if (count($prices) > 0): ?>

<table class="table table-hover align-middle">

    <thead>

        <tr>

            <th>Service</th>
            <th>Starting Price</th>
            <th>Maximum Price</th>
            <th>Status</th>
            <th width="170">Actions</th>

        </tr>

    </thead>

    <tbody>

        <?php foreach ($prices as $price): ?>

        <tr>

            <td><?= htmlspecialchars($price['service_title']) ?></td>

            <td>
                AED <?= number_format($price['starting_price'], 2) ?>
            </td>

            <td>

                <?php if ($price['maximum_price']) : ?>

                    AED <?= number_format($price['maximum_price'], 2) ?>

                <?php else: ?>

                    -

                <?php endif; ?>

            </td>

            <td><?= htmlspecialchars($price['status']) ?></td>

            <td>

                <a href="?page=edit-pricing&id=<?= $price['id'] ?>"
                   class="btn btn-sm btn-warning">
                    Edit
                </a>

                <a href="?page=delete-pricing&id=<?= $price['id'] ?>"
   class="btn btn-sm btn-danger"
   onclick="return confirm('Are you sure you want to delete this pricing record?');">
    <i class="bi bi-trash"></i> Delete
</a>

            </td>

        </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<?php else: ?>

<div class="alert alert-info mb-0">

    No pricing records found.

</div>

<?php endif; ?>
            </p>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>