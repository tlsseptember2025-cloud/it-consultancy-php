<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';

$isDemoAgent = isset($_SESSION['demo_agent']);

if ($isDemoAgent) {

    requireDemoAgent();

    require_once CONFIG_PATH . '/demo-database.php';

    $agentId = (int) ($_SESSION['demo_agent']['id'] ?? 0);
    $demoTenantId = (int) ($_SESSION['demo_agent']['demo_tenant_id'] ?? 0);

    if ($agentId <= 0 || $demoTenantId <= 0) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $agentPdo = $demoPdo;

    $agentStmt = $agentPdo->prepare("
        SELECT id
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND status = 'Active'
        LIMIT 1
    ");

    $agentStmt->execute([
        $agentId,
        $demoTenantId
    ]);

    if (!$agentStmt->fetchColumn()) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

} else {

    /*
     * This page is an Agent page. The normal Agent session is required.
     */
    if (!isset($_SESSION['agent'])) {
        header('Location: ?page=public-login');
        exit;
    }

    if (!isset($_SESSION['agent'])) {
        header('Location: ?page=public-login');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $agentId = (int) $_SESSION['agent']['id'];
    $agentPdo = $pdo;
}

if ($isDemoAgent) {

    $stmt = $agentPdo->prepare("
        SELECT

            sb.id,

            r.id AS request_id,

            c.name AS customer_name,

            s.title AS service_name,

            ss.service_date,

            ss.service_time,

            r.job_status,
            r.workflow_stage

        FROM service_bookings sb

        INNER JOIN service_slots ss
            ON ss.id = sb.slot_id

        INNER JOIN requests r
            ON r.id = sb.request_id

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        WHERE
            sb.agent_id = ?

            AND c.demo_tenant_id = ?
            AND c.is_demo_account = 1

            AND r.workflow_stage IN (
                'Service Scheduled',
                'Service Active',
                'Missed Service',
                'Service Explanation Required',
                'Needs Admin Review'
            )

        ORDER BY
            ss.service_date,
            ss.service_time
    ");

    $stmt->execute([
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $agentPdo->prepare("
        SELECT

            sb.id,

            r.id AS request_id,

            c.name AS customer_name,

            s.title AS service_name,

            ss.service_date,

            ss.service_time,

            r.job_status,
            r.workflow_stage

        FROM service_bookings sb

        INNER JOIN service_slots ss
            ON ss.id = sb.slot_id

        INNER JOIN requests r
            ON r.id = sb.request_id

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        WHERE
            sb.agent_id = ?

            AND r.workflow_stage IN (
                'Service Scheduled',
                'Service Active',
                'Missed Service',
                'Service Explanation Required',
                'Needs Admin Review'
            )

        ORDER BY
            ss.service_date,
            ss.service_time
    ");

    $stmt->execute([
        $agentId
    ]);
}


$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>
            My Service Jobs
        </h2>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th class="text-center" style="width:100px;">
                                Request #
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Stage
                            </th>

                            <th>
                                Job Status
                            </th>

                            <th style="width:180px;">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

<?php if (empty($jobs)): ?>

<tr>

    <td colspan="8" class="text-center text-muted py-4">

        No service jobs assigned.

    </td>

</tr>

<?php else: ?>

<?php foreach ($jobs as $job): ?>

<tr>

    <td class="text-center">

        <strong>
            #<?= (int) $job['request_id'] ?>
        </strong>

    </td>


    <td>

        <?= formatDate($job['service_date']) ?>

    </td>


    <td>

        <?= formatTime($job['service_time']) ?>

    </td>


    <td>

        <?= htmlspecialchars($job['customer_name']) ?>

    </td>


    <td>

        <?= htmlspecialchars($job['service_name']) ?>

    </td>


    <td>

        <?= htmlspecialchars($job['workflow_stage']) ?>

    </td>


    <td>

        <?= htmlspecialchars($job['job_status']) ?>

    </td>


    <td>

<?php if (
    $job['workflow_stage'] === 'Service Explanation Required'
): ?>

    <a
        href="?page=respond-service-review&id=<?= (int) $job['id'] ?>"
        class="btn btn-warning btn-sm text-nowrap">

        Respond to Admin Review

    </a>


<?php elseif (
    $job['workflow_stage'] === 'Missed Service'
    || $job['job_status'] === 'Missed Service'
): ?>

    <a
        href="?page=explain-missed-service&id=<?= (int) $job['id'] ?>"
        class="btn btn-danger btn-sm text-nowrap">

        Explain Missed Service

    </a>


<?php elseif (
    $job['workflow_stage'] === 'Needs Admin Review'
    && $job['job_status'] === 'Needs Admin Review'
): ?>

    <span class="badge bg-warning text-dark">

        Submitted for Admin Review

    </span>


<?php else: ?>

    <a
        href="?page=view-service-job&id=<?= (int) $job['id'] ?>"
        class="btn btn-success btn-sm">

        View Job

    </a>

<?php endif; ?>

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

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
