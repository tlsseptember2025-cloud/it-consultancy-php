<?php

require_once APP_PATH . '/helpers/auth.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $archivePdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $archivePdo->prepare(
        "SELECT id
         FROM demo_tenants
         WHERE id = ?
           AND status = 'Active'
           AND (expires_at IS NULL OR expires_at >= CURDATE())
         LIMIT 1"
    );
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $archivePdo->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
           AND is_demo_account = 1
           AND is_super_admin = 0
           AND demo_tenant_id = ?
         LIMIT 1"
    );
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
    $archivePdo = $pdo;
    $demoTenantId = null;
}

require_once CONFIG_PATH . '/request-events.php';
require_once CONFIG_PATH . '/request-event-display.php';

$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Archived Request
|--------------------------------------------------------------------------
*/

$stmt = $archivePdo->prepare("
    SELECT
        requests.*,

        customers.name AS customer_name,
        customers.email,
        customers.phone,
        customers.company,

        services.title AS service_title,

        agents.name AS agent_name

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    LEFT JOIN agents
        ON agents.id = requests.agent_id

    WHERE requests.id = ?
      AND requests.workflow_stage = 'Archived'
      AND (
          ? = 0
          OR (
              customers.demo_tenant_id = ?
              AND customers.is_demo_account = 1
          )
      )

    LIMIT 1
");

$stmt->execute([
    $requestId,
    $demoTenantId ?? 0,
    $demoTenantId ?? 0
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {
    die('Archived request not found.');
}


/*
|--------------------------------------------------------------------------
| Load Request Events
|--------------------------------------------------------------------------
*/

$stmt = $archivePdo->prepare("
    SELECT
        *
    FROM request_events
    WHERE request_id = ?
    ORDER BY created_at ASC, id ASC
");

$stmt->execute([$requestId]);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Archived Request #<?= (int) $request['id'] ?>
            </h2>

            <p class="text-muted mb-0">
                Read-only archived record.
            </p>

        </div>

        <span class="badge bg-secondary">
            Archived
        </span>

    </div>


    <!-- Request Information -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Request Information
        </div>

        <div class="card-body">

            <p>
                <strong>Request #:</strong>
                <?= (int) $request['id'] ?>
            </p>

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars($request['service_title']) ?>
            </p>

            <p>
                <strong>Quoted Price:</strong>
                <?= !empty($request['quoted_price'])
                    ? '$' . number_format($request['quoted_price'], 2)
                    : '-' ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= htmlspecialchars($request['status'] ?? '-') ?>
            </p>

            <p>
                <strong>Workflow Stage:</strong>
                <span class="badge bg-secondary">
                    Archived
                </span>
            </p>

            <p>
                <strong>Request Date:</strong>
                <?= !empty($request['created_at'])
                    ? formatDateTime($request['created_at'])
                    : '-' ?>
            </p>

            <p class="mb-0">
                <strong>Description:</strong>
            </p>

            <div class="border rounded p-3 mt-2">

                <?= !empty($request['description'])
                    ? nl2br(htmlspecialchars($request['description']))
                    : '<span class="text-muted">No description recorded.</span>' ?>

            </div>

        </div>

    </div>


    <!-- Customer Information -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Customer Information
        </div>

        <div class="card-body">

            <p>
                <strong>Customer:</strong>
                <?= htmlspecialchars($request['customer_name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars($request['email']) ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= htmlspecialchars($request['phone']) ?>
            </p>

            <p class="mb-0">
                <strong>Company:</strong>
                <?= !empty($request['company'])
                    ? htmlspecialchars($request['company'])
                    : '-' ?>
            </p>

        </div>

    </div>


    <!-- Completion & Archive Information -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Completion & Archive Information
        </div>

        <div class="card-body">

            <p>
                <strong>Completed At:</strong>
                <?= !empty($request['completed_at'])
                    ? formatDateTime($request['completed_at'])
                    : '-' ?>
            </p>

            <p>
                <strong>Archived At:</strong>
                <?= !empty($request['archived_at'])
                    ? formatDateTime($request['archived_at'])
                    : '-' ?>
            </p>

            <p class="mb-0">
                <strong>Completion Notes:</strong>
            </p>

            <div class="border rounded p-3 mt-2">

                <?= !empty($request['completion_notes'])
                    ? nl2br(htmlspecialchars($request['completion_notes']))
                    : '<span class="text-muted">No completion notes recorded.</span>' ?>

            </div>

        </div>

    </div>


    <!-- Assigned Agent -->

    <div class="card mb-4">

        <div class="card-header bg-primary text-white">
            Assigned Agent
        </div>

        <div class="card-body">

            <?= !empty($request['agent_name'])
                ? htmlspecialchars($request['agent_name'])
                : '<span class="text-muted">No agent recorded.</span>' ?>

        </div>

    </div>


    <!-- Request Timeline -->

    <div class="card mb-4">

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

                    <?php

                    $display = $requestEventDisplay[$event['event_code']] ?? [
                        'title' => 'Unknown Event',
                        'icon'  => '📝',
                        'badge' => 'secondary'
                    ];

                    ?>

                    <div class="border-bottom pb-3 mb-3">

                        <div class="d-flex justify-content-between">

                            <strong>

                                <?= $display['icon'] ?>

                                <?= htmlspecialchars(
                                    $event['event_title'] ?: $display['title']
                                ) ?>

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

                                <?= nl2br(
                                    htmlspecialchars(
                                        $event['event_description']
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>


    <a
        href="?page=archived-requests"
        class="btn btn-secondary"
    >
        Back to Archived Requests
    </a>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>