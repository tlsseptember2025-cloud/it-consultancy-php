<?php
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

require_once APP_PATH . '/helpers/auth.php';

requireAdminLogin();

$isMainAdmin      = isset($_SESSION['user']);
$isDemoAdmin      = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $slipPdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $slipPdo->prepare("
        SELECT id, status, expires_at
        FROM demo_tenants
        WHERE id = ?
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);
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

    $accountStmt = $slipPdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
          AND demo_tenant_id = ?
        LIMIT 1
    ");
    $accountStmt->execute([
        (int) ($_SESSION['demo_user']['id'] ?? 0),
        $demoTenantId
    ]);

    if (!$accountStmt->fetchColumn()) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }
} else {
    require_once CONFIG_PATH . '/database.php';
    $slipPdo = $pdo;
}

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'Invalid payment slip.';
    header('Location: ?page=deposit-slips');
    exit;
}

$stmt = $slipPdo->prepare("
    SELECT
        ps.*,
        c.name AS customer_name,
        c.email AS customer_email,
        s.title AS service_title,
        r.quoted_price
    FROM payment_slips ps
    JOIN customers c
        ON ps.customer_id = c.id
    JOIN requests r
        ON ps.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    WHERE ps.id = ?
      AND (
          ? = 0
          OR (
              c.demo_tenant_id = ?
              AND c.is_demo_account = 1
          )
      )
    LIMIT 1
");

$stmt->execute([
    $id,
    $isDemoAdmin ? $demoTenantId : 0,
    $isDemoAdmin ? $demoTenantId : 0
]);

$slip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slip) {
    $_SESSION['error'] = 'Payment slip not found or you do not have access to it.';
    header('Location: ?page=deposit-slips');
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2>Deposit Slip Review</h2>

        <div class="row mb-3">

            <div class="col-md-6">

                <p class="mb-2">
                    <strong>Customer:</strong><br>
                    <?= htmlspecialchars($slip['customer_name']) ?>
                </p>

            </div>

            <div class="col-md-6">

                <p class="mb-2">
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($slip['customer_email']) ?>
                </p>

            </div>

        </div>

        <p>
            <strong>Service:</strong><br>
            <?= htmlspecialchars($slip['service_title']) ?>
        </p>

        <div class="alert alert-warning">

            <strong>Amount Required:</strong>

            AED <?= number_format((float) $slip['quoted_price'], 2) ?>

            <br>

            <small class="text-muted">
                Verify that the payment receipt shows the full required amount
                before approving the payment.
            </small>

        </div>

        <p>
            <strong>Status:</strong>

            <?php if ($slip['status'] === 'Pending'): ?>

                <span class="badge bg-warning text-dark">
                    Pending Review
                </span>

            <?php elseif ($slip['status'] === 'Approved'): ?>

                <span class="badge bg-success">
                    Approved
                </span>

            <?php elseif ($slip['status'] === 'Rejected'): ?>

                <span class="badge bg-danger">
                    Rejected
                </span>

            <?php else: ?>

                <span class="badge bg-secondary">
                    <?= htmlspecialchars($slip['status']) ?>
                </span>

            <?php endif; ?>

        </p>

        <hr>

        <?php

        $fileExtension = strtolower(
            pathinfo($slip['file_name'], PATHINFO_EXTENSION)
        );

        ?>

        <?php if ($fileExtension === 'pdf'): ?>

            <iframe
                src="uploads/slips/<?= htmlspecialchars($slip['file_name'], ENT_QUOTES, 'UTF-8') ?>"
                width="100%"
                height="600"
                class="border">
            </iframe>

        <?php else: ?>

            <img
                src="uploads/slips/<?= htmlspecialchars($slip['file_name'], ENT_QUOTES, 'UTF-8') ?>"
                class="img-fluid border"
                style="max-width: 100%;">

        <?php endif; ?>

        <hr>

        <a
            href="uploads/slips/<?= htmlspecialchars($slip['file_name'], ENT_QUOTES, 'UTF-8') ?>"
            target="_blank"
            class="btn btn-primary me-2">

            Download Receipt

        </a>

        <?php if ($slip['status'] === 'Pending'): ?>

            <a
                href="?page=approve-slip&id=<?= $slip['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                class="btn btn-success"
                onclick="return confirm('Confirm that the receipt has been checked and shows the full required amount before approving this payment.');">

                Approve Payment

            </a>

            <a
                href="?page=reject-slip&id=<?= $slip['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                class="btn btn-danger"
                onclick="return confirm('Reject this payment receipt?');">

                Reject Payment

            </a>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>