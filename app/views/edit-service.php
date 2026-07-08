<?php

require_once __DIR__ . '/../helpers/auth.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT * FROM services WHERE id = ?
");

$stmt->execute([$id]);

$service = $stmt->fetch();

if (!$service) {

    die('Service not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);

    $image = $service['image'];

    if (!empty($_FILES['image']['name'])) {

        $image = time() . '_' . basename($_FILES['image']['name']);

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            dirname(__DIR__, 2) . '/public/uploads/' . $image
        );
    }

    $stmt = $pdo->prepare("
        UPDATE services
        SET title = ?, description = ?, image = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $description,
        $image,
        $id
    ]);

    header('Location: ?page=services-admin');
    exit;
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Edit Service
                </h2>

                <form method="POST" enctype="multipart/form-data">

                    <div class="mb-3">

                        <label class="form-label">
                            Service Title
                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="<?= htmlspecialchars($service['title']) ?>"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="5"
                            required><?= htmlspecialchars($service['description']) ?></textarea>

                    </div>

                    <div class="mb-3">

                            <div class="mb-3">

                                <label class="form-label">
                                    Current Image
                                </label>

                                <br>

                                <?php if (!empty($service['image'])): ?>

                                    <img
                                        src="../public/uploads/<?= htmlspecialchars($service['image']) ?>"
                                        width="120"
                                        class="img-thumbnail mb-3">

                                <?php else: ?>

                                    <p>No image uploaded.</p>

                                <?php endif; ?>

                            </div>

<div class="mb-3">

    <label class="form-label">
        New Image
    </label>

    <input
        type="file"
        name="image"
        class="form-control"
        accept="image/*">

</div>

                    </div>

                    <button class="btn btn-primary">

                        Update Service

                    </button>

                    <a
                        href="?page=services-admin"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>