<?php

require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once CONFIG_PATH . '/database.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

$reviewPdo = $pdo;
$adminTenantId = 0;

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $reviewPdo = $demoPdo;

    $adminTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($adminTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $reviewPdo->prepare("
        SELECT id, status, expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");
    $tenantStmt->execute([$adminTenantId]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$tenant ||
        ($tenant['status'] ?? '') !== 'Active' ||
        (!empty($tenant['expires_at']) && strtotime($tenant['expires_at']) < time())
    ) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $demoAdminId = (int) ($_SESSION['demo_user']['id'] ?? 0);

    if ($demoAdminId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $adminStmt = $reviewPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $adminStmt->execute([$demoAdminId, $adminTenantId]);

    if (!$adminStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
}

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Invalid request.');
}

$stmt = $reviewPdo->prepare("
    SELECT
        r.*,
        c.name,
        c.email,
        c.phone,
        s.title AS service_title,
        sb.slot_id,
        ss.service_date,
        ss.service_time
    FROM requests r
    JOIN customers c
        ON c.id = r.customer_id
    JOIN services s
        ON s.id = r.service_id
    LEFT JOIN service_bookings sb
        ON sb.request_id = r.id
    LEFT JOIN service_slots ss
        ON ss.id = sb.slot_id
    WHERE r.id = ?
      AND (
          ? = 0
          OR (
              c.demo_tenant_id = ?
              AND c.is_demo_account = 1
              AND s.demo_tenant_id = ?
          )
      )
    LIMIT 1
");

$stmt->execute([$id, $adminTenantId, $adminTenantId, $adminTenantId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $notes = trim($_POST['service_review_notes'] ?? '');
        $decision = $_POST['decision'] ?? '';

        if (!in_array($decision, ['approve', 'return'], true)) {
            $error = 'Invalid decision.';
        } elseif (mb_strlen($notes) > 5000) {
            $error = 'Review notes cannot exceed 5,000 characters.';
        } else {

            try {
                $reviewPdo->beginTransaction();

                // Re-read and lock the request so the decision cannot be applied
                // after another administrator has already changed the workflow.
                $lockStmt = $reviewPdo->prepare("
                    SELECT
                        r.id,
                        r.workflow_stage,
                        r.customer_id
                    FROM requests r
                    INNER JOIN customers c
                        ON c.id = r.customer_id
                    WHERE r.id = ?
                      AND (
                          ? = 0
                          OR (
                              c.demo_tenant_id = ?
                              AND c.is_demo_account = 1
                          )
                      )
                    FOR UPDATE
                ");
                $lockStmt->execute([$id, $adminTenantId, $adminTenantId]);
                $lockedRequest = $lockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$lockedRequest) {
                    throw new RuntimeException('Request not found.');
                }

                if (
                    !in_array(
                        $lockedRequest['workflow_stage'],
                        [
                            'Service Scheduled',
                            'Service Review Required',
                            'Service Pending Review',
                            'Service Active'
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'This service request is no longer awaiting administrator review.'
                    );
                }

                if ($decision === 'approve') {

                    $updateStmt = $reviewPdo->prepare("
                        UPDATE requests
                        SET
                            service_review_notes = ?,
                            workflow_stage = 'Service Active'
                        WHERE id = ?
                    ");

                    $updateStmt->execute([
                        $notes,
                        $id
                    ]);

                    RequestEventHelper::addCurrentUser(
                        $reviewPdo,
                        (int) $id,
                        RequestEventHelper::EVENT_SERVICE_STARTED,
                        RequestEventHelper::TYPE_SERVICE,
                        'Service Started',
                        'The administrator approved the service appointment and the service is now active.',
                        true
                    );

                    $reviewPdo->commit();

                    $_SESSION['success'] = 'The service was approved and is now active.';

                    header('Location: ?page=requests');
                    exit;

                }

                // Return for rescheduling: keep the current booking unchanged
                // and send the customer back to the reschedule workflow.
                $updateStmt = $reviewPdo->prepare("
                    UPDATE requests
                    SET
                        service_review_notes = ?,
                        workflow_stage = 'Awaiting Customer Reschedule',
                        job_status = 'Pending',
                        status = 'Pending',
                        admin_instruction = 'Please choose another available service appointment.'
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $notes,
                    $id
                ]);

                RequestEventHelper::addCurrentUser(
                    $reviewPdo,
                    (int) $id,
                    'SERVICE_RETURNED_FOR_RESCHEDULE',
                    RequestEventHelper::TYPE_SERVICE,
                    'Service Returned for Rescheduling',
                    $notes !== ''
                        ? 'The administrator returned the service appointment for rescheduling. Notes: ' . $notes
                        : 'The administrator returned the service appointment for rescheduling.',
                    true
                );

                $reviewPdo->commit();

                $_SESSION['success'] =
                    'The service was returned for rescheduling. The customer can choose another appointment.';

                header('Location: ?page=requests');
                exit;

            } catch (Throwable $e) {

                if ($reviewPdo->inTransaction()) {
                    $reviewPdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

require dirname(__DIR__) . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2 class="mb-4">Review Service</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Customer Information</strong>
            </div>

            <div class="card-body">
                <p><strong>Name:</strong> <?= htmlspecialchars($request['name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($request['phone']) ?></p>
            </div>
        </div>

        <!-- Service Information -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Service Information</strong>
            </div>

            <div class="card-body">
                <p><strong>Service:</strong> <?= htmlspecialchars($request['service_title']) ?></p>

                <p><strong>Date:</strong>
                    <?= !empty($request['service_date'])
                        ? date('M d, Y', strtotime($request['service_date']))
                        : 'Pending' ?>
                </p>

                <p><strong>Time:</strong>
                    <?= !empty($request['service_time'])
                        ? date('h:i A', strtotime($request['service_time']))
                        : 'Pending' ?>
                </p>

                <p><strong>Status:</strong>
                    <?= htmlspecialchars($request['workflow_stage']) ?>
                </p>
            </div>
        </div>

        <!-- Service Review -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Service Review</strong>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label for="service_review_notes" class="form-label">
                        Review Notes
                    </label>

                    <textarea
                        id="service_review_notes"
                        name="service_review_notes"
                        class="form-control"
                        rows="5"
                        maxlength="5000"
                        placeholder="Enter your review notes here..."><?= htmlspecialchars($request['service_review_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Decision -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Decision</strong>
            </div>

            <div class="card-body">

                <div class="form-check mb-3">
                    <input
                        class="form-check-input"
                        type="radio"
                        name="decision"
                        id="approve"
                        value="approve"
                        checked>

                    <label class="form-check-label" for="approve">
                        Approve Service
                    </label>
                </div>

                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="radio"
                        name="decision"
                        id="return"
                        value="return">

                    <label class="form-check-label" for="return">
                        Return for Rescheduling
                    </label>
                </div>

            </div>
        </div>

        <hr>

        <div class="d-flex justify-content-end gap-2">

            <a
                href="?page=requests"
                class="btn btn-secondary">
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-success">
                Continue
            </button>

        </div>

    </form>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
