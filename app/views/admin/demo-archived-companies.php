<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

$companiesStmt = $demoPdo->query("
    SELECT
        t.id,
        t.company_name,
        t.company_domain,
        t.registered_email,
        t.started_at,
        t.expires_at,
        t.status,
        CASE
            WHEN t.expires_at IS NOT NULL AND t.expires_at <= NOW() THEN 'Expired'
            WHEN t.status <> 'Active' THEN t.status
            ELSE 'No Expiry'
        END AS effective_status,
        (
            SELECT er.status
            FROM demo_extension_requests er
            WHERE er.demo_tenant_id = t.id
            ORDER BY er.id DESC
            LIMIT 1
        ) AS extension_status
    FROM demo_tenants t
    WHERE NOT (
        t.status = 'Active'
        AND t.expires_at IS NOT NULL
        AND t.expires_at > NOW()
    )
    ORDER BY
        CASE
            WHEN t.expires_at IS NOT NULL AND t.expires_at <= NOW() THEN 0
            ELSE 1
        END,
        t.expires_at DESC,
        t.company_name ASC,
        t.id ASC
");

$companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="mb-1">Archived Demo Companies</h1>
            <p class="text-muted mb-0">
                Expired or inactive Demo companies kept for history and reporting.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="?page=demo-super-admin" class="btn btn-primary">
                <i class="bi bi-building me-1"></i>
                Active Demo Companies
            </a>
            <a href="?page=demo-usage-reports" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i>
                Overall Usage Reports
            </a>
        </div>
    </div>

    <?php if (empty($companies)): ?>
        <div class="alert alert-secondary">
            No archived or expired Demo companies are currently recorded.
        </div>
    <?php else: ?>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Expired</th>
                            <th>Extension</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($companies as $company): ?>
                        <?php
                            $status = (string) ($company['effective_status'] ?? 'Unknown');
                            $badgeClass = match (strtolower($status)) {
                                'expired', 'suspended', 'inactive' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                            $extensionStatus = (string) ($company['extension_status'] ?? 'None');
                            $extensionBadge = match ($extensionStatus) {
                                'Approved' => 'bg-success',
                                'Rejected' => 'bg-danger',
                                'Pending' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($company['company_name'] ?? 'Unnamed Company', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="small text-muted">
                                    Tenant #<?= (int) $company['id'] ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($company['company_domain'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($company['started_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($company['expires_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= $extensionBadge ?>">
                                    <?= htmlspecialchars($extensionStatus, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a
                                    href="?page=demo-company-report&id=<?= (int) $company['id'] ?>"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clipboard-data me-1"></i>
                                    Report
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>
