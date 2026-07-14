<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
    INSERT INTO requests
    (
        customer_id,
        service_id,
        quoted_price,
        description,
        status
    )
    VALUES (?, ?, ?, ?, ?)
");

    $serviceStmt = $pdo->prepare("
    SELECT price
    FROM services
    WHERE id = ?
");

$serviceStmt->execute([
    $_POST['service_id']
]);

$service = $serviceStmt->fetch();

$stmt->execute([
    $_POST['customer_id'],
    $_POST['service_id'],
    $service['price'],
    $_POST['description'],
    $_POST['status']
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

require dirname(__DIR__) . '/layouts/header-admin.php';

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Request
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

                            <option value="">
                                Select Customer
                            </option>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= $customer['id'] ?>">

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

                            <option value="">
                                Select Service
                            </option>

                            <?php foreach ($services as $service): ?>

                                <option
                                    value="<?= $service['id'] ?>">

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
                            rows="5"></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option>
                                Pending
                            </option>

                            <option>
                                Approved
                            </option>

                            <option>
                                In Progress
                            </option>

                            <option>
                                Completed
                            </option>

                            <option>
                                Cancelled
                            </option>

                        </select>

                    </div>

                    <button
                        class="btn btn-primary">

                        Save Request

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>

