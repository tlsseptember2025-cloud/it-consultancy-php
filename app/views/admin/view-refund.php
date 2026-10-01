<?php

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

// Demo Super Admin has a separate portal and must not use normal Admin pages.
if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}

if ($isDemoAdmin) {
    require_once CONFIG_PATH . '/demo-database.php';
    $refundPdo = $demoPdo;

    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $refundPdo->prepare(
        "SELECT id, status, expires_at
         FROM demo_tenants
         WHERE id = ?
         LIMIT 1"
    );
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

    $accountStmt = $refundPdo->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
           AND is_demo_account = 1
           AND is_super_admin = 0
           AND demo_tenant_id = ?
         LIMIT 1"
    );
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
    $refundPdo = $pdo;
    $demoTenantId = 0;
}

$refundId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$refundId || $refundId <= 0) {
    $_SESSION['error'] = 'Invalid refund ID.';
    header('Location: ?page=archived-refunds');
    exit;
}

if ($isDemoAdmin) {
    $stmt = $refundPdo->prepare("\
        SELECT
            rr.*,
            c.name AS customer_name,
            c.email,
            s.title AS service_title
        FROM refund_requests rr
        JOIN requests r
            ON r.id = rr.request_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE rr.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        LIMIT 1
    ");
    $stmt->execute([$refundId, $demoTenantId]);
} else {
    $stmt = $refundPdo->prepare("\
        SELECT
            rr.*,
            c.name AS customer_name,
            c.email,
            s.title AS service_title
        FROM refund_requests rr
        JOIN requests r
            ON r.id = rr.request_id
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        WHERE rr.id = ?
          AND (c.is_demo_account = 0 OR c.is_demo_account IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$refundId]);
}

$refund = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refund) {
    $_SESSION['error'] = 'Refund not found or you do not have access to this refund.';
    header('Location: ?page=archived-refunds');
    exit;
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<h2 class="mb-4">Refund Details</h2>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <strong><i class="bi bi-person-fill"></i> Customer Information</strong>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <tr>
                <th width="220">Customer Name</th>
                <td><?= htmlspecialchars($refund['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th>Email Address</th>
                <td><?= htmlspecialchars($refund['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <strong><i class="bi bi-briefcase-fill"></i> Service Information</strong>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <tr>
                <th width="220">Service</th>
                <td><?= htmlspecialchars($refund['service_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <strong><i class="bi bi-cash-stack"></i> Refund Information</strong>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <tr>
                <th width="220">Reason Type</th>
                <td><?= htmlspecialchars($refund['reason_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th>Reason Details</th>
                <td><?= nl2br(htmlspecialchars($refund['reason_details'] ?? '', ENT_QUOTES, 'UTF-8')) ?></td>
            </tr>
            <tr>
                <th>Refund Amount</th>
                <td>AED <?= number_format((float) ($refund['refund_amount'] ?? 0), 2) ?></td>
            </tr>
            <tr>
                <th>Decision</th>
                <td>
                    <?php if (
                        ($refund['status'] ?? '') === 'Approved' &&
                        ($refund['refund_status'] ?? '') === 'Completed'
                    ): ?>
                        <span class="badge rounded-pill bg-success fs-6 px-3 py-2">Completed</span>
                    <?php elseif (($refund['status'] ?? '') === 'Rejected'): ?>
                        <span class="badge rounded-pill bg-danger fs-6 px-3 py-2">Rejected</span>
                    <?php elseif (($refund['status'] ?? '') === 'Approved'): ?>
                        <span class="badge rounded-pill bg-success fs-6 px-3 py-2">Approved</span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-secondary fs-6 px-3 py-2">
                            <?= htmlspecialchars($refund['status'] ?? 'Pending', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Review Notes</th>
                <td><?= nl2br(htmlspecialchars($refund['review_notes'] ?? '', ENT_QUOTES, 'UTF-8')) ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <strong><i class="bi bi-clock-history"></i> Timeline</strong>
    </div>
    <div class="card-body">
        <table class="table table-bordered mb-0">
            <tr>
                <th width="220">Refund Requested</th>
                <td><?= !empty($refund['created_at']) ? formatDateTime($refund['created_at']) : '—' ?></td>
            </tr>
            <tr>
                <th>Refund Closed</th>
                <td>
                    <?= !empty($refund['reviewed_at'])
                        ? date('l, d M Y - h:i A', strtotime($refund['reviewed_at']))
                        : '—' ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="text-end mt-4">
    <a href="?page=archived-refunds" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i>
        Back to Archived Refunds
    </a>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
