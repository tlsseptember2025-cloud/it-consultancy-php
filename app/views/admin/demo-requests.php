<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/demo-database.php';

$stmt = $pdo->query("
    SELECT
        id,
        full_name,
        email,
        phone,
        company_name,
        company_domain,
        explore_options,
        status,
        email_confirmed_at,
        created_at
    FROM demo_requests
    ORDER BY created_at DESC
");

$demoRequests = $stmt->fetchAll();

/*
 * Determine the actual Demo provisioning stage for requests that have
 * reached Customer Confirmed. The request remains Customer Confirmed
 * while Step 1.9 / Step 1.10 / Demo Setup are being completed.
 */
foreach ($demoRequests as &$request) {

    $request['provisioning_stage'] = null;

    if ($request['status'] !== 'Customer Confirmed') {
        continue;
    }

    $tenantStmt = $demoPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE demo_request_id = ?
        LIMIT 1
    ");

    $tenantStmt->execute([(int)$request['id']]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        $request['provisioning_stage'] = 'tenant';
        continue;
    }

    $tenantId = (int)$tenant['id'];

    $adminStmt = $demoPdo->prepare("
        SELECT id
        FROM users
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
          AND is_super_admin = 0
        LIMIT 1
    ");

    $adminStmt->execute([$tenantId]);
    $demoAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$demoAdmin) {
        $request['provisioning_stage'] = 'admin';
        continue;
    }

    $customerStmt = $demoPdo->prepare("
        SELECT COUNT(*)
        FROM customers
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
    ");

    $customerStmt->execute([$tenantId]);
    $customerCount = (int)$customerStmt->fetchColumn();

    $agentStmt = $demoPdo->prepare("
        SELECT COUNT(*)
        FROM agents
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
    ");

    $agentStmt->execute([$tenantId]);
    $agentCount = (int)$agentStmt->fetchColumn();

    if ($customerCount >= 1 && $agentCount >= 2) {
        $request['provisioning_stage'] = 'complete';
    } else {
        $request['provisioning_stage'] = 'setup';
    }
}
unset($request);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th class="text-center" style="width:100px;">
                    Demo #
                </th>

                <th>
                    Requester
                </th>

                <th>
                    Company
                </th>

                <th>
                    Email
                </th>

                <th>
                    Domain
                </th>

                <th>
                    Status
                </th>

                <th style="width:150px; white-space:nowrap;">
                    Request Date
                </th>

                <th style="width:120px;">
                    Action
                </th>

            </tr>

        </thead>

        <tbody>

            <?php if (empty($demoRequests)): ?>

                <tr>

                    <td colspan="8" class="text-center text-muted">
                        No Demo requests found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($demoRequests as $request): ?>

                    <tr>

                        <td class="text-center">
                            #<strong><?= (int)$request['id']; ?></strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['company_name'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['company_domain']) ?>
                        </td>

                        <td>

                            <?php if ($request['status'] === 'Confirmed'): ?>

                                <span class="badge bg-success">
                                    Confirmed
                                </span>

                            <?php elseif ($request['status'] === 'Approved'): ?>

                                <span class="badge bg-primary">
                                    Awaiting Customer Confirmation
                                </span>

                            <?php elseif ($request['status'] === 'Rejected'): ?>

                                <span class="badge bg-danger">
                                    Rejected
                                </span>

                            <?php elseif ($request['status'] === 'Customer Confirmed'): ?>

                                <?php if ($request['provisioning_stage'] === 'tenant'): ?>

                                    <span class="badge bg-success">
                                        Ready to Create Demo Tenant
                                    </span>

                                <?php elseif ($request['provisioning_stage'] === 'admin'): ?>

                                    <span class="badge bg-primary">
                                        Ready to Create Demo Admin
                                    </span>

                                <?php elseif ($request['provisioning_stage'] === 'setup'): ?>

                                    <span class="badge bg-warning text-dark">
                                        Awaiting Demo Setup
                                    </span>

                                <?php elseif ($request['provisioning_stage'] === 'complete'): ?>

                                    <span class="badge bg-success">
                                        Demo Setup Complete
                                    </span>

                                <?php endif; ?>

                            <?php elseif ($request['status'] === 'Demo Created'): ?>

                                <span class="badge bg-success">
                                    Demo Created
                                </span>

                            <?php elseif ($request['status'] === 'Expired'): ?>

                                <span class="badge bg-secondary">
                                    Expired
                                </span>

                            <?php else: ?>

                                <span class="badge bg-warning text-dark">
                                    <?= htmlspecialchars($request['status']) ?>
                                </span>

                            <?php endif; ?>

                        </td>

                        <td style="white-space:nowrap;">
                            <?= htmlspecialchars($request['created_at']) ?>
                        </td>

                        <td>

                            <?php if ($request['status'] === 'Confirmed'): ?>

                                <a
                                    href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                    class="btn btn-warning btn-sm">
                                    Review &amp; Approve
                                </a>

                            <?php elseif ($request['status'] === 'Approved'): ?>

                                <a
                                    href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                    class="btn btn-primary btn-sm">
                                    Awaiting Customer
                                </a>

                            <?php elseif ($request['status'] === 'Customer Confirmed'): ?>

                                <?php if ($request['provisioning_stage'] === 'tenant'): ?>

                                    <a
                                        href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                        class="btn btn-success btn-sm">
                                        Create Demo Tenant
                                    </a>

                                <?php elseif ($request['provisioning_stage'] === 'admin'): ?>

                                    <a
                                        href="?page=create-demo-admin&id=<?= (int)$request['id'] ?>"
                                        class="btn btn-success btn-sm">
                                        Create Demo Admin
                                    </a>

                                <?php elseif ($request['provisioning_stage'] === 'setup'): ?>

                                    <a
                                        href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                        class="btn btn-warning btn-sm">
                                        Awaiting Demo Setup
                                    </a>

                                <?php elseif ($request['provisioning_stage'] === 'complete'): ?>

                                    <a
                                        href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                        class="btn btn-success btn-sm">
                                        Demo Setup Complete
                                    </a>

                                <?php else: ?>

                                    <a
                                        href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                        class="btn btn-info btn-sm">
                                        View
                                    </a>

                                <?php endif; ?>

                            <?php elseif ($request['status'] === 'Demo Created'): ?>

                                <a
                                    href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                    class="btn btn-success btn-sm">
                                    View Demo
                                </a>

                            <?php else: ?>

                                <a
                                    href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                                    class="btn btn-info btn-sm">
                                    View
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>