<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = $stmt->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h1>
        Manage Services
    </h1>

    <a
        href="?page=add-service"
        class="btn btn-primary">

        Add Service

    </a>

</div>

<div class="table-responsive">

    <table class="table table-bordered table-hover align-middle">

        <tr>

            <th>Image</th>
            <th>Title</th>
            <th>Description</th>
            <th>Action</th>

        </tr>

        <?php foreach ($services as $service): ?>

            <tr>

                <td>

                    <?php if (!empty($service['image'])): ?>

                        <img
                            src="../public/uploads/<?= htmlspecialchars($service['image']) ?>"
                            width="80"
                            class="img-thumbnail">

                    <?php endif; ?>

                </td>

                <td>
                    <?= htmlspecialchars($service['title']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($service['description']) ?>
                </td>

                <td>

                    <a
                        href="?page=edit-service&id=<?= $service['id'] ?>"
                        class="btn btn-sm btn-warning">

                        Edit

                    </a>

                    <a
                        href="?page=delete-service&id=<?= $service['id'] ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this service?')">

                        Delete

                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

    </table>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>