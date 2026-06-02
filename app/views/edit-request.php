<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM requests
    WHERE id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            customer_id = ?,
            service_id = ?,
            description = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['customer_id'],
        $_POST['service_id'],
        $_POST['description'],
        $_POST['status'],
        $id
    ]);

    header("Location: ?page=requests");
    exit;
}

$customers = $pdo->query("
    SELECT *
    FROM customers
    ORDER BY name
")->fetchAll();

$services = $pdo->query("
    SELECT *
    FROM services
    ORDER BY title
")->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Edit Request
                </h2>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Customer
                        </label>

                        <select
                            name="customer_id"
                            class="form-select"
                            required>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= $customer['id'] ?>"
                                    <?= $customer['id'] == $request['customer_id'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($customer['name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Service
                        </label>

                        <select
                            name="service_id"
                            class="form-select"
                            required>

                            <?php foreach ($services as $service): ?>

                                <option
                                    value="<?= $service['id'] ?>"
                                    <?= $service['id'] == $request['service_id'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($service['title']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="5"><?= htmlspecialchars($request['description']) ?></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <?php
                            $statuses = [
                                'Pending',
                                'Approved',
                                'In Progress',
                                'Completed',
                                'Cancelled'
                            ];

                            foreach ($statuses as $status):
                            ?>

                                <option
                                    <?= $status == $request['status'] ? 'selected' : '' ?>>

                                    <?= $status ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <button
                        class="btn btn-primary">

                        Update Request

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>