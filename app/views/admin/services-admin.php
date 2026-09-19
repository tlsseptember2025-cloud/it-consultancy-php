<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $servicesPdo = $demoPdo;
} else {
    require_once CONFIG_PATH . '/database.php';
    $servicesPdo = $pdo;
}

$stmt = $servicesPdo->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = $stmt->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Services</h1>
    <a href="?page=add-service" class="btn btn-primary">Add Service</a>
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
                        <img src="../public/uploads/services/<?= htmlspecialchars($service['image']) ?>" width="80" class="img-thumbnail">
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($service['title']) ?></td>

                <td>
                    <?php
                    $description = $service['description'] ?? '';
                    $description = strip_tags(
                        $description,
                        '<p><div><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><span>'
                    );
                    $description = preg_replace(
                        '~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i',
                        '',
                        $description
                    );
                    $description = preg_replace(
                        '~\s(href|src)\s*=\s*(["\'])\s*(javascript:|vbscript:|data:text/html)[^"\']*\2~i',
                        '',
                        $description
                    );
                    $description = preg_replace(
                        '~\b(?:expression|javascript|vbscript)\s*\(~i',
                        '',
                        $description
                    );
                    ?>
                    <div class="service-description-preview">
                        <?= $description ?>
                    </div>
                </td>

                <td>
                    <a href="?page=edit-service&id=<?= $service['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?page=delete-service&id=<?= $service['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this service?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<style>
.service-description-preview {
    max-width: 900px;
    overflow-wrap: anywhere;
}
.service-description-preview p,
.service-description-preview div {
    margin-bottom: .35rem;
}
.service-description-preview img {
    max-width: 100%;
    height: auto;
}
</style>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
