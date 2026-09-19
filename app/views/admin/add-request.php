<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

require CONFIG_PATH . '/database.php';

$requestPdo = $pdo;

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    require CONFIG_PATH . '/demo-database.php';

    $requestPdo = $demoPdo;

    $demoTenantId = (int) $_SESSION['demo_user']['demo_tenant_id'];
}


/*
|--------------------------------------------------------------------------
| Save Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Pending';


    /*
     * Verify the selected customer belongs to the correct system/tenant.
     */
    if ($isDemoAdmin) {

        $customerStmt = $requestPdo->prepare("
            SELECT id
            FROM customers
            WHERE id = ?
              AND demo_tenant_id = ?
              AND is_demo_account = 1
            LIMIT 1
        ");

        $customerStmt->execute([
            $customerId,
            $demoTenantId
        ]);

    } else {

        $customerStmt = $requestPdo->prepare("
            SELECT id
            FROM customers
            WHERE id = ?
            LIMIT 1
        ");

        $customerStmt->execute([
            $customerId
        ]);
    }

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);


    if (!$customer) {

        $error = 'Invalid customer selected.';

    } else {

        /*
         * Get service price.
         */
        $serviceStmt = $requestPdo->prepare("
            SELECT id, price
            FROM services
            WHERE id = ?
            LIMIT 1
        ");

        $serviceStmt->execute([
            $serviceId
        ]);

        $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);


        if (!$service) {

            $error = 'Invalid service selected.';

        } else {

            /*
             * Create request.
             */
            $stmt = $requestPdo->prepare("
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

            $stmt->execute([
                $customerId,
                $serviceId,
                $service['price'],
                $description,
                $status
            ]);

            header("Location: ?page=requests");
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Customers
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $customersStmt = $requestPdo->prepare("
        SELECT *
        FROM customers
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
        ORDER BY name
    ");

    $customersStmt->execute([
        $demoTenantId
    ]);

} else {

    $customersStmt = $requestPdo->query("
        SELECT *
        FROM customers
        ORDER BY name
    ");
}

$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Services
|--------------------------------------------------------------------------
*/

$services = $requestPdo->query("
    SELECT *
    FROM services
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Request
                </h2>


                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <?php if (empty($customers)): ?>

                    <div class="alert alert-info">
                        No customers are available.
                    </div>

                <?php else: ?>

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
                                        value="<?= (int) $customer['id'] ?>"
                                        <?= (
                                            isset($_POST['customer_id'])
                                            && (int) $_POST['customer_id'] === (int) $customer['id']
                                        ) ? 'selected' : '' ?>>

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
                                        value="<?= (int) $service['id'] ?>"
                                        <?= (
                                            isset($_POST['service_id'])
                                            && (int) $_POST['service_id'] === (int) $service['id']
                                        ) ? 'selected' : '' ?>>

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
                                rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-select">

                                <option
                                    value="Pending"
                                    <?= ($_POST['status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>
                                    Pending
                                </option>

                                <option
                                    value="Approved"
                                    <?= ($_POST['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>
                                    Approved
                                </option>

                                <option
                                    value="In Progress"
                                    <?= ($_POST['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>
                                    In Progress
                                </option>

                                <option
                                    value="Completed"
                                    <?= ($_POST['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>
                                    Completed
                                </option>

                                <option
                                    value="Cancelled"
                                    <?= ($_POST['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>
                                    Cancelled
                                </option>

                            </select>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            Save Request

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>