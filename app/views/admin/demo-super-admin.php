<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

/*
|--------------------------------------------------------------------------
| Demo Companies
|--------------------------------------------------------------------------
|
| This is the permanent Demo Super Admin company-control page.
| Each Demo tenant has one Admin, one Customer and two Agents.
| The page shows the current/latest state; the full history is available
| from the company report.
|--------------------------------------------------------------------------
*/

$companiesStmt = $demoPdo->query("
    SELECT
        t.id,
        t.company_name,
        t.company_domain,
        t.registered_email,
        CASE
            WHEN t.status <> 'Active' THEN t.status
            WHEN t.expires_at IS NULL THEN 'No Expiry'
            WHEN t.expires_at <= NOW() THEN 'Expired'
            ELSE 'Active'
        END AS effective_status,
        t.started_at,
        t.expires_at,

        (
            SELECT u.id
            FROM users u
            WHERE u.demo_tenant_id = t.id
              AND u.is_demo_account = 1
              AND u.is_super_admin = 0
            ORDER BY u.id ASC
            LIMIT 1
        ) AS admin_id,

        (
            SELECT u.username
            FROM users u
            WHERE u.demo_tenant_id = t.id
              AND u.is_demo_account = 1
              AND u.is_super_admin = 0
            ORDER BY u.id ASC
            LIMIT 1
        ) AS admin_username,

        (
            SELECT c.id
            FROM customers c
            WHERE c.demo_tenant_id = t.id
              AND c.is_demo_account = 1
            ORDER BY c.id ASC
            LIMIT 1
        ) AS customer_id,

        (
            SELECT c.name
            FROM customers c
            WHERE c.demo_tenant_id = t.id
              AND c.is_demo_account = 1
            ORDER BY c.id ASC
            LIMIT 1
        ) AS customer_name,

        (
            SELECT a.id
            FROM agents a
            WHERE a.demo_tenant_id = t.id
              AND a.is_demo_account = 1
            ORDER BY a.id ASC
            LIMIT 1
        ) AS agent1_id,

        (
            SELECT a.name
            FROM agents a
            WHERE a.demo_tenant_id = t.id
              AND a.is_demo_account = 1
            ORDER BY a.id ASC
            LIMIT 1
        ) AS agent1_name,

        (
            SELECT a.id
            FROM agents a
            WHERE a.demo_tenant_id = t.id
              AND a.is_demo_account = 1
            ORDER BY a.id ASC
            LIMIT 1 OFFSET 1
        ) AS agent2_id,

        (
            SELECT a.name
            FROM agents a
            WHERE a.demo_tenant_id = t.id
              AND a.is_demo_account = 1
            ORDER BY a.id ASC
            LIMIT 1 OFFSET 1
        ) AS agent2_name



    FROM demo_tenants t
    WHERE t.status = 'Active'
      AND t.expires_at IS NOT NULL
      AND t.expires_at > NOW()
    ORDER BY t.company_name ASC, t.id ASC
");

$companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Optional Admin Presence
|--------------------------------------------------------------------------
|
| Demo Admin presence is read only when the Demo database contains the
| existing admin_presence table. This keeps the company page compatible
| with Demo databases that pre-date presence tracking.
|--------------------------------------------------------------------------
*/

$adminPresenceById = [];

try {
    $tableStmt = $demoPdo->query("SHOW TABLES LIKE 'admin_presence'");

    if ($tableStmt && $tableStmt->fetchColumn()) {
        $presenceStmt = $demoPdo->query("
            SELECT admin_id, is_online, last_seen
            FROM admin_presence
        ");

        foreach ($presenceStmt->fetchAll(PDO::FETCH_ASSOC) as $presence) {
            $adminPresenceById[(int) $presence['admin_id']] = $presence;
        }
    }
} catch (Throwable $e) {
    error_log('Demo Super Admin presence lookup failed: ' . $e->getMessage());
}

foreach ($companies as &$company) {
    $presence = $adminPresenceById[(int) ($company['admin_id'] ?? 0)] ?? null;
    $company['admin_is_online'] = $presence['is_online'] ?? 0;
    $company['admin_last_seen'] = $presence['last_seen'] ?? null;
}
unset($company);

function demoCompanyStatusBadge(?string $status): string
{
    $status = $status ?: 'Unknown';

    $class = match (strtolower($status)) {
        'active' => 'bg-success',
        'expired', 'suspended', 'inactive' => 'bg-danger',
        default => 'bg-secondary',
    };

    return '<span class="badge ' . $class . '">' .
        htmlspecialchars($status, ENT_QUOTES, 'UTF-8') .
        '</span>';
}

function demoPresenceBadge($isOnline, ?string $lastSeen = null): string
{
    if ((int) $isOnline === 1 && $lastSeen !== null) {
        $age = time() - strtotime($lastSeen);
        if ($age <= 180) {
            return '<span class="badge bg-success">Online</span>';
        }
    }

    return '<span class="badge bg-secondary">Offline</span>';
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Demo Companies</h1>
            <p class="text-muted mb-0">
                Super Admin view of each Demo company and its actual usage.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="?page=demo-archived-companies" class="btn btn-outline-secondary">
                <i class="bi bi-archive me-1"></i>
                Archived Demo Companies
            </a>
            <a href="?page=demo-extension-requests" class="btn btn-outline-warning">
                <i class="bi bi-calendar-plus me-1"></i>
                Extension Requests
            </a>
            <a href="?page=demo-usage-reports" class="btn btn-primary">
                <i class="bi bi-bar-chart-line me-1"></i>
                Overall Usage Reports
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Demo model:</strong>
        each company is provisioned with one Demo Admin, one Demo Customer,
        Agent 1 and Agent 2. The reports measure what those users actually do.
    </div>

    <?php if (empty($companies)): ?>
        <div class="alert alert-secondary">
            No active Demo companies are currently available for live support.<br>Use <strong>Archived Demo Companies</strong> to view expired or inactive Demo companies.
        </div>
    <?php else: ?>

        <div class="row g-4">

            <?php foreach ($companies as $company): ?>

                <div class="col-12">
                    <div class="card shadow-sm">

                        <div class="card-header bg-dark text-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong><?= htmlspecialchars($company['company_name'] ?? 'Unnamed Company', ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($company['company_domain'])): ?>
                                        <span class="text-white-50 ms-2">
                                            <?= htmlspecialchars($company['company_domain'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?= demoCompanyStatusBadge($company['effective_status'] ?? null) ?>
                            </div>
                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="small text-muted mb-1">Admin</div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($company['admin_username'] ?? 'Not provisioned', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="mt-2">
                                            <?= demoPresenceBadge($company['admin_is_online'] ?? 0, $company['admin_last_seen'] ?? null) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="small text-muted mb-1">Customer</div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($company['customer_name'] ?? 'Not provisioned', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="small text-muted mt-2">
                                            Customer activity is shown in the company report.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="small text-muted mb-1">Agent 1</div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($company['agent1_name'] ?? 'Not provisioned', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="small text-muted mb-1">Agent 2</div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($company['agent2_name'] ?? 'Not provisioned', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                                <div class="small text-muted">
                                    Tenant #<?= (int) $company['id'] ?>
                                    <?php if (!empty($company['expires_at'])): ?>
                                        · Expires <?= htmlspecialchars($company['expires_at'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>

                                <a
                                    href="?page=demo-company-report&id=<?= (int) $company['id'] ?>"
                                    class="btn btn-outline-primary">
                                    <i class="bi bi-clipboard-data me-1"></i>
                                    Open Company Report
                                </a>
                            </div>

                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>
