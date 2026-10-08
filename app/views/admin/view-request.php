<?php

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

requireAdminLogin();

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $requestPdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $requestPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $requestPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    require_once CONFIG_PATH . '/database.php';
    $requestPdo = $pdo;
    $demoTenantId = null;
}

require_once CONFIG_PATH . '/request-events.php';
require_once CONFIG_PATH . '/request-event-display.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    $_SESSION['error'] = 'Invalid request ID.';
    header('Location: ?page=requests');
    exit;
}

if ($isDemoAdmin) {
    $stmt = $requestPdo->prepare("
        SELECT
            requests.*,
            customers.name AS customer_name,
            customers.email,
            customers.phone,
            customers.company,
            services.title AS service_title
        FROM requests
        JOIN customers
            ON customers.id = requests.customer_id
        JOIN services
            ON services.id = requests.service_id
        WHERE requests.id = ?
          AND customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
          AND services.demo_tenant_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id, $demoTenantId, $demoTenantId]);
} else {
    $stmt = $requestPdo->prepare("
        SELECT
            requests.*,
            customers.name AS customer_name,
            customers.email,
            customers.phone,
            customers.company,
            services.title AS service_title
        FROM requests
        JOIN customers
            ON customers.id = requests.customer_id
        JOIN services
            ON services.id = requests.service_id
        WHERE requests.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['error'] = 'Request not found or you do not have access to this request.';
    header('Location: ?page=requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Request Timeline
|--------------------------------------------------------------------------
*/

$events = RequestEventHelper::get(
    $requestPdo,
    (int)$request['id']
);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Request Details
        </h2>

        <p><strong>Customer:</strong> <?= htmlspecialchars($request['customer_name']) ?></p>

        <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>

        <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>

        <p><strong>Company:</strong> <?= htmlspecialchars($request['company']) ?></p>

        <hr>

        <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

        <p><strong>Quoted Price:</strong> $<?= number_format($request['quoted_price'], 2) ?></p>

        <p><strong>Status:</strong> <?= htmlspecialchars($request['status']) ?></p>

        <p><strong>Description:</strong></p>

        <div class="border rounded p-3 mb-3">

            <?= nl2br(htmlspecialchars($request['description'])) ?>

        </div>

        <p><strong>Date:</strong> <?= formatDateTime($request['created_at']) ?>

        <hr>

<div class="card shadow-sm mt-4">

    <div class="card-header bg-dark text-white">

        Request Timeline

    </div>

    <div class="card-body">

        <?php if (empty($events)): ?>

            <p class="text-muted mb-0">

                No events have been recorded for this request.

            </p>

        <?php else: ?>

            <?php foreach ($events as $event): ?>

                <div class="border-bottom pb-3 mb-3">

                    <div class="d-flex justify-content-between">

                        <strong>

                            <?php

$display = array_merge(
    [
        'title' => 'Unknown Event',
        'icon'  => '📝',
        'badge' => 'secondary'
    ],
    $requestEventDisplay[$event['event_code']] ?? []
);

?>

<span class="badge bg-<?= $display['badge'] ?> me-2">

    <?= $display['icon'] ?>

</span>

<strong>

    <?= htmlspecialchars($display['title']) ?>

</strong>

                        </strong>

                        <small class="text-muted">

                            <?= formatDateTime($event['created_at']) ?>

                        </small>

                    </div>

                    <div class="text-muted small mt-2">

                        <?= htmlspecialchars($event['event_source']) ?>

                    </div>
                    
                    <?php if (!empty($event['event_description'])): ?>

                        <div class="mt-2">

                            <?= nl2br(htmlspecialchars($event['event_description'])) ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

        <a
            href="?page=requests"
            class="btn btn-secondary">

            Back

        </a>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>