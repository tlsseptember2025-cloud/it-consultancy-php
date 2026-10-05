<?php
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';

/*
|--------------------------------------------------------------------------
| Admin Context
|--------------------------------------------------------------------------
| Main Admin  -> Main database
| Demo Admin  -> Demo database, restricted to its tenant
| Demo Super Admin -> Uses the Demo Super Admin workflow, not this page
|--------------------------------------------------------------------------
*/

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$reviewPdo = $pdo;
$demoTenantId = 0;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';

    $reviewPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    // Verify tenant is active.
    $tenantStmt = $reviewPdo->prepare("
        SELECT id, status, expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");

    $tenantStmt->execute([$demoTenantId]);

    $demoTenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$demoTenant
        || $demoTenant['status'] !== 'Active'
        || (
            $demoTenant['expires_at'] !== null
            && strtotime($demoTenant['expires_at']) <= time()
        )
    ) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    // Verify the Demo Admin belongs to this tenant and is not a super admin.
    $adminStmt = $reviewPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
        LIMIT 1
    ");

    $adminStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    if (!$adminStmt->fetch(PDO::FETCH_ASSOC)) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}

$requestId = (int) ($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid request.');
}

/*
|--------------------------------------------------------------------------
| Load Consultation Request
|--------------------------------------------------------------------------
*/

$requestSql = "
    SELECT
        r.*,
        c.name AS customer_name,
        s.title AS service_title,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id
    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id
    WHERE r.id = ?
";

$requestParams = [$requestId];

if ($isDemoAdmin) {
    $requestSql .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
    ";

    $requestParams[] = $demoTenantId;
}

$requestSql .= " LIMIT 1";

$stmt = $reviewPdo->prepare($requestSql);
$stmt->execute($requestParams);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-0">
            Review Consultation #<?= (int) $requestId ?>
        </h2>

        <p>
            <strong>Workflow Stage:</strong>
            <?= htmlspecialchars($request['workflow_stage'] ?? '') ?>
        </p>

        <p>
            <strong>Customer:</strong>
            <?= htmlspecialchars($request['customer_name'] ?? '') ?>
        </p>

        <p>
            <strong>Service:</strong>
            <?= htmlspecialchars($request['service_title'] ?? '') ?>
        </p>

        <p>
            <strong>Date:</strong>

            <?= !empty($request['slot_date'])
                ? formatDate($request['slot_date'])
                : 'Not Scheduled' ?>

        </p>

        <p>
            <strong>Time:</strong>

            <?= !empty($request['slot_time'])
                ? formatTime($request['slot_time'])
                : 'Not Scheduled' ?>

        </p>

        <p>
            <strong>Method:</strong>

            <?= htmlspecialchars(
                $request['consultation_method'] ?? 'Not Selected'
            ) ?>

        </p>

        <hr>

        <?php if (!empty($request['agent_notes'])): ?>

            <hr>

            <h4>Agent Consultation Notes</h4>

            <div class="alert alert-light border">
                <?= nl2br(htmlspecialchars($request['agent_notes'])) ?>
            </div>

        <?php endif; ?>

        <div class="mt-4 d-flex gap-2">

            <?php if ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

                <a
                    href="?page=confirm-consultation-booking&id=<?= (int) $request['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                    class="btn btn-success"
                >
                    Approve Schedule
                </a>

                <a
                    href="?page=reject-consultation&id=<?= (int) $request['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                    class="btn btn-danger"
                >
                    Reject Schedule
                </a>

            <?php elseif ($request['workflow_stage'] === 'Needs Admin Review'): ?>

                <a
                    href="?page=approve-consultation&id=<?= (int) $request['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                    class="btn btn-success"
                >
                    Complete Consultation
                </a>

                <a
                    href="?page=reject-consultation&id=<?= (int) $request['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                    class="btn btn-danger"
                >
                    Return to Agent
                </a>

            <?php endif; ?>

            <a
                href="?page=requests"
                class="btn btn-secondary"
            >
                Back
            </a>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
