<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
|
| Main Admin:
|   Uses the Main database.
|
| Demo Admin:
|   Uses the Demo database and is restricted to its own tenant.
|
| Demo Super Admin:
|   Uses the Demo database and can review all Demo records.
|
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $adminPdo = $demoPdo;

} else {

    require_once CONFIG_PATH . '/database.php';

    $adminPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Demo Tenant Scope
|--------------------------------------------------------------------------
*/

$tenantCondition = '';
$tenantParams = [];

if ($isDemoAdmin) {

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }

    /*
     * Demo Admin may only see requests belonging to its own
     * Demo customers and Demo services.
     */
    $tenantCondition = "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
        AND s.demo_tenant_id = ?
    ";

    $tenantParams = [
        $demoTenantId,
        $demoTenantId
    ];
}


/*
|--------------------------------------------------------------------------
| Load Missed Consultation Reviews
|--------------------------------------------------------------------------
*/

$stmt = $adminPdo->prepare("
    SELECT
        r.id,
        r.workflow_stage,
        r.job_status,
        r.incomplete_reason,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.workflow_stage = 'Missed Consultation Review'

        AND r.job_status = 'Pending'

        AND TIMESTAMP(cs.slot_date, cs.slot_time)
            < DATE_SUB(NOW(), INTERVAL 1 HOUR)

        $tenantCondition

    ORDER BY cs.slot_date DESC, cs.slot_time DESC
");

$stmt->execute($tenantParams);

$missedConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">
                Missed Consultation Approvals
            </h2>

            <p class="text-muted mb-0">
                Consultations awaiting administrator review.
            </p>
        </div>

        <span class="badge bg-warning text-dark fs-6">
            <?= count($missedConsultations) ?> Awaiting Review
        </span>

    </div>


    <div class="card shadow-sm">

        <div class="card-body p-0">

            <?php if (empty($missedConsultations)): ?>

                <div class="p-5 text-center text-muted">

                    <h5>
                        No missed consultations are awaiting approval.
                    </h5>

                    <p class="mb-0">
                        All missed consultations have been reviewed.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-dark">

                            <tr>

                                <th>Request #</th>

                                <th>Customer</th>

                                <th>Service</th>

                                <th>Scheduled Date</th>

                                <th>Scheduled Time</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($missedConsultations as $item): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            #<?= (int)$item['id'] ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['customer_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['service_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= !empty($item['slot_date'])
                                            ? date(
                                                'd-m-Y',
                                                strtotime($item['slot_date'])
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                    <td>
                                        <?= !empty($item['slot_time'])
                                            ? date(
                                                'h:i A',
                                                strtotime($item['slot_time'])
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                    <td>

                                        <span class="badge bg-warning text-dark">
                                            Missed Consultation Review
                                        </span>

                                    </td>

                                    <td>

                                        <a
                                            href="?page=review-missed-consultation&id=<?= (int)$item['id'] ?>"
                                            class="btn btn-danger btn-sm">

                                            Review

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>