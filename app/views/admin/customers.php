<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/SearchPaginationHelper.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
*/

$isMainAdmin = isset($_SESSION['user']);
$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    !$isMainAdmin &&
    !$isDemoAdmin &&
    !$isDemoSuperAdmin
) {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $customersPdo = $demoPdo;

} else {

    require CONFIG_PATH . '/database.php';

    $customersPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Demo Tenant
|--------------------------------------------------------------------------
*/

$demoTenantId = null;

if ($isDemoAdmin) {

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}


/*
|--------------------------------------------------------------------------
| Pending Customer Registrations
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $customersPdo->prepare("
        SELECT *
        FROM customers
        WHERE registration_status = 'Pending Admin Approval'
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        ORDER BY created_at DESC
    ");

    $stmt->execute([
        $demoTenantId
    ]);

} elseif ($isDemoSuperAdmin) {

    $stmt = $customersPdo->query("
        SELECT *
        FROM customers
        WHERE registration_status = 'Pending Admin Approval'
          AND is_demo_account = 1
        ORDER BY created_at DESC
    ");

} else {

    $stmt = $customersPdo->query("
        SELECT *
        FROM customers
        WHERE registration_status = 'Pending Admin Approval'
        ORDER BY created_at DESC
    ");
}

$pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Approved / Existing Customers
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();

$page = getPageNumber();

$limit = getPageLimit(10);

$params = [];

if ($isDemoAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Admin - Own Tenant Only
    |--------------------------------------------------------------------------
    */

    $where = "
        WHERE demo_tenant_id = ?
          AND is_demo_account = 1
    ";

    $params[] = $demoTenantId;

} elseif ($isDemoSuperAdmin) {

    /*
    |--------------------------------------------------------------------------
    | Demo Super Admin - All Demo Customers
    |--------------------------------------------------------------------------
    */

    $where = "
        WHERE is_demo_account = 1
    ";

} else {

    /*
    |--------------------------------------------------------------------------
    | Main Admin - All Main Customers
    |--------------------------------------------------------------------------
    */

    $where = "
        WHERE (
            registration_status = 'Approved'
            OR registration_status IS NULL
        )
    ";
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$where .= buildSearchCondition(
    [
        'name',
        'email',
        'phone',
        'company'
    ],
    $search,
    $params
);


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $customersPdo->prepare("
    SELECT COUNT(*)
    FROM customers
    $where
");

$countStmt->execute($params);

$totalCustomers = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalCustomers,
    $limit
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load Customers
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM customers
    $where
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $customersPdo->prepare($sql);

$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Admin Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

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
                                        $customer['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $customer['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $customer['phone'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>

                                    <?php if (
                                        (int) $customer['email_verified'] === 1
                                    ): ?>

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

<div class="card shadow-sm mb-3">

    <div class="card-body">

        <form method="GET" class="row g-3 align-items-end">

            <input
                type="hidden"
                name="page"
                value="customers">

            <div class="col-md-8">

                <label class="form-label">
                    Search Customers
                </label>

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Name, email, phone, or company"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">

            </div>

            <div class="col-md-4 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary">
                    Search
                </button>

                <?php if ($search !== ''): ?>

                    <a
                        href="?page=customers"
                        class="btn btn-outline-secondary">
                        Clear
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>

</div>

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
                                $customer['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $customer['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $customer['phone'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $customer['company'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <?php if (
                                ($customer['status'] ?? 'Active') === 'Suspended'
                            ): ?>

                                <span class="badge bg-danger">
                                    Suspended
                                </span>

                            <?php else: ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php endif; ?>

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


                            <?php if (
                                ($customer['status'] ?? 'Active') === 'Suspended'
                            ): ?>

                                <a
                                    href="?page=admin-suspension-chat&id=<?= (int) $customer['id'] ?>"
                                    class="btn btn-sm btn-danger">

                                    Suspension Chat

                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php if ($totalPages > 1): ?>

    <nav aria-label="Customer pagination">

        <ul class="pagination justify-content-center mt-4">

            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'customers',
                                $page - 1,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        Previous

                    </a>

                </li>

            <?php endif; ?>


            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'customers',
                                $i,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'customers',
                                $page + 1,
                                ['search' => $search]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        Next

                    </a>

                </li>

            <?php endif; ?>

        </ul>

    </nav>

<?php endif; ?>

<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>