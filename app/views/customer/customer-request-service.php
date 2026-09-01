<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$customerId = (int) $_SESSION['customer']['id'];

$services = $pdo->query("
    SELECT *
    FROM services
    ORDER BY title
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
    INSERT INTO requests
    (
        customer_id,
        service_id,
        description,
        status,
        workflow_stage
    )
    VALUES (?, ?, ?, 'Pending', 'Submitted')
");

$stmt->execute([
    $_SESSION['customer']['id'],
    $_POST['service_id'],
    $_POST['description']
]);

$requestId = (int) $pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| Record Request Created Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $requestId,
    'REQUEST_CREATED',
    RequestEventHelper::TYPE_REQUEST,
    'Request Created',
    'The customer submitted a new service request.',
    true
);

$_SESSION['success'] = 'Service request submitted successfully.';

/*
|--------------------------------------------------------------------------
| Notify Administrator
|--------------------------------------------------------------------------
*/

$serviceStmt = $pdo->prepare("
    SELECT title
    FROM services
    WHERE id = ?
    LIMIT 1
");

$serviceStmt->execute([
    (int) $_POST['service_id']
]);

$service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

$serviceName = $service['title'] ?? 'Unknown Service';

$customerName =
    $_SESSION['customer']['name']
    ?? 'Customer';

$customerEmail =
    $_SESSION['customer']['email']
    ?? '';

/*
|--------------------------------------------------------------------------
| Admin In-App Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $pdo,
    'admin',
    null,
    'New Service Request',
    'New Service Request #' . $requestId .
        ' has been submitted for ' . $serviceName . '.',
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
    $_POST['description']
);

header('Location: ?page=customer-requests');
exit;


}

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Service
        </h2>

        <?php if (!empty($success)): ?>

            <div class="alert alert-success">

                <?= $success ?>

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