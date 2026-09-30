<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Customer Authentication / Database Selection
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheck = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $customerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

/*
|--------------------------------------------------------------------------
| Load Available Services
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT *
        FROM services
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
        ORDER BY title
    ");

    $stmt->execute([
        $demoTenantId
    ]);

    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $services = $customerPdo->query("
        SELECT *
        FROM services
        ORDER BY title
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$error = null;

/*
|--------------------------------------------------------------------------
| Submit Service Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $serviceId = (int) (
        $_POST['service_id'] ?? 0
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    if ($serviceId <= 0) {

        $error = 'Please select a service.';

    } elseif ($description === '') {

        $error = 'Please provide a description.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify Selected Service Belongs to Correct Tenant
        |--------------------------------------------------------------------------
        */

        if ($isDemoCustomer) {

            $serviceStmt = $customerPdo->prepare("
                SELECT
                    id,
                    title
                FROM services
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
                LIMIT 1
            ");

            $serviceStmt->execute([
                $serviceId,
                $demoTenantId
            ]);

        } else {

            $serviceStmt = $customerPdo->prepare("
                SELECT
                    id,
                    title
                FROM services
                WHERE id = ?
                LIMIT 1
            ");

            $serviceStmt->execute([
                $serviceId
            ]);
        }

        $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$service) {

            $error = 'The selected service is not available.';

        } else {

            try {

                $customerPdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Create Request
                |--------------------------------------------------------------------------
                */

                $stmt = $customerPdo->prepare("
                    INSERT INTO requests
                    (
                        customer_id,
                        service_id,
                        description,
                        status,
                        workflow_stage
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'Pending',
                        'Submitted'
                    )
                ");

                $stmt->execute([
                    $customerId,
                    $serviceId,
                    $description
                ]);

                $requestId = (int) $customerPdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | Record Request Created Event
                |--------------------------------------------------------------------------
                */

                RequestEventHelper::addCurrentUser(
                    $customerPdo,
                    $requestId,
                    'REQUEST_CREATED',
                    RequestEventHelper::TYPE_REQUEST,
                    'Request Created',
                    'The customer submitted a new service request.',
                    true
                );

                /*
                |--------------------------------------------------------------------------
                | Customer Details
                |--------------------------------------------------------------------------
                */

                if ($isDemoCustomer) {

                    $customerStmt = $customerPdo->prepare("
                        SELECT
                            name,
                            email
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

                    $customerStmt = $customerPdo->prepare("
                        SELECT
                            name,
                            email
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

                    throw new RuntimeException(
                        'Customer account could not be verified.'
                    );
                }

                $customerName =
                    $customer['name'] ?? 'Customer';

                $customerEmail =
                    $customer['email'] ?? '';

                $serviceName =
                    $service['title'] ?? 'Unknown Service';

                /*
                |--------------------------------------------------------------------------
                | Admin In-App Notification
                |--------------------------------------------------------------------------
                */

                createNotification(
                    $customerPdo,
                    'admin',
                    null,
                    'New Service Request',
                    'New Service Request #' . $requestId .
                        ' has been submitted for ' .
                        $serviceName . '.',
                    '?page=view-request&id=' . $requestId
                );

                /*
                |--------------------------------------------------------------------------
                | Admin Email
                |--------------------------------------------------------------------------
                */

                sendNewServiceRequestAdminEmail(
                    $requestId,
                    $customerName,
                    $customerEmail,
                    $serviceName,
                    $description
                );

                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $customerPdo->commit();

                $_SESSION['success'] =
                    'Service request submitted successfully.';

                header(
                    'Location: ?page=customer-requests'
                );

                exit;

            } catch (Throwable $e) {

                if ($customerPdo->inTransaction()) {

                    $customerPdo->rollBack();
                }

                $error =
                    'Unable to submit the service request. Please try again.';
            }
        }
    }
}

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Service
        </h2>

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars($error) ?>

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
                        Select Service
                    </option>

                    <?php foreach ($services as $service): ?>

                        <option
                            value="<?= (int) $service['id'] ?>">

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
                    rows="5"
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                Submit Request

            </button>

            <a
                href="?page=customer-requests"
                class="btn btn-secondary ms-2">

                Cancel

            </a>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>