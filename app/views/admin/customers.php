<?php

require_once HELPER_PATH . '/auth.php';

requireAdminLogin();

if (isset($_SESSION['demo_user'])) {
    require_once CONFIG_PATH . '/demo-database.php';
    $customersPdo = $demoPdo;
} else {
    require CONFIG_PATH . '/database.php';
    $customersPdo = $pdo;
}

require dirname(__DIR__) . '/layouts/header-admin.php';


/*
|--------------------------------------------------------------------------
| Pending Customer Registrations
|--------------------------------------------------------------------------
*/

$stmt = $customersPdo->query("
    SELECT *
    FROM customers
    WHERE registration_status = 'Pending Admin Approval'
    ORDER BY created_at DESC
");

$pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Approved / Existing Customers
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['demo_user'])) {

    $demoTenantId = (int) $_SESSION['demo_user']['demo_tenant_id'];

    $stmt = $customersPdo->prepare("
        SELECT *
        FROM customers
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
        ORDER BY created_at DESC
    ");

    $stmt->execute([$demoTenantId]);

} else {

    $stmt = $customersPdo->query("
        SELECT *
        FROM customers
        WHERE registration_status = 'Approved'
           OR registration_status IS NULL
        ORDER BY created_at DESC
    ");

}

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2 class="mb-0">
        Customers
    </h2>

</div>


<!--
|--------------------------------------------------------------------------
| Pending Registrations
|--------------------------------------------------------------------------
-->

<?php if (!empty($pendingRegistrations)): ?>

    <div class="card shadow-sm border-warning mb-4">

        <div class="card-header bg-warning">

            <strong>
                Pending Customer Registrations
            </strong>

            <span class="badge bg-dark ms-2">
                <?= count($pendingRegistrations) ?>
            </span>

        </div>

        <div class="card-body">

            <p class="text-muted">

                These customers have verified their email address
                and are waiting for administrator approval.

            </p>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Email Verification</th>
                            <th>Registration Status</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($pendingRegistrations as $customer): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['name']
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['email']
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['phone']
                                    ) ?>

                                </td>

                                <td>

                                    <?php if ((int) $customer['email_verified'] === 1): ?>

                                        <span class="badge bg-success">
                                            Verified
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Not Verified
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="badge bg-warning text-dark">

                                        Pending Admin Approval

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="?page=review-customer-registration&id=<?= (int) $customer['id'] ?>"
                                        class="btn btn-sm btn-primary">

                                        Review

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Existing Customers
|--------------------------------------------------------------------------
-->

<h4 class="mb-3">
    Registered Customers
</h4>


<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Company</th>
                <th>Status</th>
                <th>Action</th>

            </tr>

        </thead>

        <tbody>

            <?php if (empty($customers)): ?>

                <tr>

                    <td
                        colspan="6"
                        class="text-center text-muted py-4">

                        No registered customers found.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($customers as $customer): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                $customer['name']
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $customer['email']
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $customer['phone']
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $customer['company'] ?? ''
                            ) ?>

                        </td>

                        <td>

                            <span class="badge bg-success">

                                Approved

                            </span>

                        </td>

                        <td>

                            <a
                                href="?page=view-customer&id=<?= (int) $customer['id'] ?>"
                                class="btn btn-sm btn-info">

                                View

                            </a>

                            <a
                                href="?page=customer-status&id=<?= (int) $customer['id'] ?>"
                                class="btn btn-sm btn-warning">

                                Status

                            </a>

                            <a
                                href="?page=messages&id=<?= (int) $customer['id'] ?>"
                                class="btn btn-sm btn-primary">

                                Messages

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>