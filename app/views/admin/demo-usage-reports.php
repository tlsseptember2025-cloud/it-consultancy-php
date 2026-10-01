<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

$tenantFilter = (int) ($_GET['tenant_id'] ?? 0);

$tenantsStmt = $demoPdo->query("
    SELECT id, company_name
    FROM demo_tenants
    ORDER BY company_name ASC, id ASC
");
$tenants = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC);

$count = static function (PDO $pdo, string $sql, array $params = []): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Demo usage report count failed: ' . $e->getMessage());
        return 0;
    }
};

$scope = '';
$scopeParams = [];

if ($tenantFilter > 0) {
    $scope = ' AND c.demo_tenant_id = ? AND c.is_demo_account = 1 ';
    $scopeParams[] = $tenantFilter;
}

$metrics = [
    'Requests' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
    'Consultations' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM consultation_bookings cb INNER JOIN requests r ON r.id = cb.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
    'Service Jobs' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
    'Completed Jobs' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE (r.job_status = 'Completed' OR r.status = 'Completed') {$scope}",
        $scopeParams
    ),
    'Cancelled Jobs' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE (r.job_status = 'Cancelled' OR r.status = 'Cancelled') {$scope}",
        $scopeParams
    ),
    'Payments' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM payments p INNER JOIN requests r ON r.id = p.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
    'Refund Requests' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM refund_requests rr INNER JOIN requests r ON r.id = rr.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
    'Live Chats' => $count(
        $demoPdo,
        $tenantFilter > 0
            ? "SELECT COUNT(*) FROM guest_chat_conversations gc INNER JOIN customers c ON c.email = gc.guest_email WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1"
            : "SELECT COUNT(*) FROM guest_chat_conversations gc INNER JOIN customers c ON c.email = gc.guest_email WHERE c.is_demo_account = 1",
        $tenantFilter > 0 ? [$tenantFilter] : []
    ),
    'Notifications' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM notifications n INNER JOIN customers c ON n.recipient_type = 'customer' AND n.recipient_id = c.id WHERE c.is_demo_account = 1" . ($tenantFilter > 0 ? ' AND c.demo_tenant_id = ?' : ''),
        $tenantFilter > 0 ? [$tenantFilter] : []
    ),
    'Request Events' => $count(
        $demoPdo,
        "SELECT COUNT(*) FROM request_events re INNER JOIN requests r ON r.id = re.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE 1=1 {$scope}",
        $scopeParams
    ),
];

/*
|--------------------------------------------------------------------------
| Per-company usage table
|--------------------------------------------------------------------------
*/

$companyRows = [];

foreach ($tenants as $tenant) {
    $id = (int) $tenant['id'];

    $companyCustomerStmt = $demoPdo->prepare(
        "SELECT email FROM customers WHERE demo_tenant_id = ? AND is_demo_account = 1 ORDER BY id ASC LIMIT 1"
    );
    $companyCustomerStmt->execute([$id]);
    $companyCustomerEmail = (string) ($companyCustomerStmt->fetchColumn() ?: '');

    $companyLiveChats = 0;
    if ($companyCustomerEmail !== '') {
        $companyLiveChats = $count(
            $demoPdo,
            "SELECT COUNT(*) FROM guest_chat_conversations WHERE guest_email = ?",
            [$companyCustomerEmail]
        );
    }

    $companyRows[] = [
        'id' => $id,
        'name' => $tenant['company_name'],
        'requests' => $count($demoPdo, "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
        'consultations' => $count($demoPdo, "SELECT COUNT(*) FROM consultation_bookings cb INNER JOIN requests r ON r.id = cb.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
        'jobs' => $count($demoPdo, "SELECT COUNT(*) FROM requests r INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
        'payments' => $count($demoPdo, "SELECT COUNT(*) FROM payments p INNER JOIN requests r ON r.id = p.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
        'refunds' => $count($demoPdo, "SELECT COUNT(*) FROM refund_requests rr INNER JOIN requests r ON r.id = rr.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
        'live_chats' => $companyLiveChats,
        'events' => $count($demoPdo, "SELECT COUNT(*) FROM request_events re INNER JOIN requests r ON r.id = re.request_id INNER JOIN customers c ON c.id = r.customer_id WHERE c.demo_tenant_id = ? AND c.is_demo_account = 1", [$id]),
    ];
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Overall Demo Usage Reports</h1>
            <p class="text-muted mb-0">
                Usage across Demo companies, based on actual customer, agent and workflow activity.
            </p>
        </div>
        <a href="?page=demo-super-admin" class="btn btn-outline-secondary">Demo Companies</a>
    </div>

    <form method="GET" class="card card-body shadow-sm mb-4">
        <input type="hidden" name="page" value="demo-usage-reports">
        <div class="row align-items-end g-3">
            <div class="col-md-5">
                <label class="form-label">Company</label>
                <select name="tenant_id" class="form-select">
                    <option value="0">All Demo Companies</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= (int) $tenant['id'] ?>" <?= $tenantFilter === (int) $tenant['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['company_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Apply Filter</button>
                <?php if ($tenantFilter > 0): ?>
                    <a href="?page=demo-usage-reports" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <?php foreach ($metrics as $label => $value): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-2"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="fs-3 fw-semibold"><?= number_format($value) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Usage by Demo Company</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Requests</th>
                            <th>Consultations</th>
                            <th>Service Jobs</th>
                            <th>Payments</th>
                            <th>Refunds</th>
                            <th>Live Chats</th>
                            <th>Events</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($companyRows)): ?>
                        <tr><td colspan="9" class="p-3 text-muted">No Demo companies available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($companyRows as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format($row['requests']) ?></td>
                                <td><?= number_format($row['consultations']) ?></td>
                                <td><?= number_format($row['jobs']) ?></td>
                                <td><?= number_format($row['payments']) ?></td>
                                <td><?= number_format($row['refunds']) ?></td>
                                <td><?= number_format($row['live_chats']) ?></td>
                                <td><?= number_format($row['events']) ?></td>
                                <td>
                                    <a href="?page=demo-company-report&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Report
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
