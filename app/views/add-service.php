<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);

    $stmt = $pdo->prepare("
        INSERT INTO services (title, description, price)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$title, $description, $price]);

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
                    Add Service
                </h2>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Service Title
                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
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
                            required></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Price
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            name="price"
                            class="form-control"
                            required>

                    </div>

                    <button class="btn btn-primary">

                        Save Service

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>