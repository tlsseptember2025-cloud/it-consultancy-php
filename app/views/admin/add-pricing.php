<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Load Services
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, title
    FROM services
    ORDER BY title
");

$services = $stmt->fetchAll();

$errors = [];

/*
|--------------------------------------------------------------------------
| Save Pricing
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $service_id = (int) $_POST['service_id'];
    $description = trim($_POST['description']);
    $starting_price = trim($_POST['starting_price']);
    $maximum_price = trim($_POST['maximum_price']);
    $status = $_POST['status'];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($service_id <= 0) {
        $errors[] = 'Please select a service.';
    }

    if ($starting_price === '' || $starting_price <= 0) {
        $errors[] = 'Starting price must be greater than zero.';
    }

    if ($maximum_price !== '' && $maximum_price < $starting_price) {
        $errors[] = 'Estimated maximum price must be greater than or equal to the starting price.';
    }

    /*
    |--------------------------------------------------------------------------
    | Insert
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            INSERT INTO price_list
            (
                service_id,
                description,
                starting_price,
                maximum_price,
                status
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $service_id,
            $description,
            $starting_price,
            $maximum_price ?: null,
            $status
        ]);

        header('Location: ?page=pricing');
        exit;
    }
}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Create Pricing Estimate
                </h2>

                <?php if (!empty($errors)): ?>

                    <div class="alert alert-danger">

                        <ul class="mb-0">

                            <?php foreach ($errors as $error): ?>

                                <li><?= htmlspecialchars($error) ?></li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Service
                        </label>

                        <select
                            name="service_id"
                            class="form-select"
                            required>

                            <option value="">
                                -- Select Service --
                            </option>

                            <?php foreach ($services as $service): ?>

                                <option
                                    value="<?= $service['id'] ?>"
                                    <?= ($service_id ?? '') == $service['id'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($service['title']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Pricing Description
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"
                            placeholder="Describe what is included in this pricing option..."><?= htmlspecialchars($description ?? '') ?></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Starting From
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                AED
                            </span>

                            <input
                                type="number"
                                name="starting_price"
                                class="form-control"
                                step="0.01"
                                min="0"
                                value="<?= htmlspecialchars($starting_price ?? '') ?>"
                                required>

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Estimated Maximum
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                AED
                            </span>

                            <input
                                type="number"
                                name="maximum_price"
                                class="form-control"
                                step="0.01"
                                min="0"
                                value="<?= htmlspecialchars($maximum_price ?? '') ?>">

                        </div>

                    </div>

                    <div class="mb-4">

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option
                                value="Active"
                                <?= ($status ?? 'Active') == 'Active' ? 'selected' : '' ?>>
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?= ($status ?? '') == 'Inactive' ? 'selected' : '' ?>>
                                Inactive
                            </option>

                        </select>

                    </div>

                    <div class="alert alert-info">

                        <strong>
                            Pricing Notice
                        </strong>

                        <hr>

                        The prices entered here are estimates for customer guidance and internal planning.

                        Final quotations are prepared after the customer consultation and project assessment.

                        Depending on the project scope, complexity, urgency and customer requirements,
                        the final quotation may be lower or higher than the estimated range.

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary">
                        Save Price
                    </button>

                    <a
                        href="?page=pricing"
                        class="btn btn-secondary ms-2">
                        Cancel
                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>