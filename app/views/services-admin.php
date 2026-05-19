<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = $stmt->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

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

            <th>Title</th>
            <th>Description</th>
            <th>Action</th>

        </tr>

        <?php foreach ($services as $service): ?>

            <tr>

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

<?php require __DIR__ . '/layouts/footer.php'; ?>