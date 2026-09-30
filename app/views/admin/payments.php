<?php

require_once HELPER_PATH . '/auth.php';
requireAdminLogin();

require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);
$isMainAdmin = isset($_SESSION['user']);


/*
|--------------------------------------------------------------------------
| Demo Super Admin Uses Separate Portal
|--------------------------------------------------------------------------
*/

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    require CONFIG_PATH . '/demo-database.php';

    $paymentsPdo = $demoPdo;

} else {

    require CONFIG_PATH . '/database.php';

    $paymentsPdo = $pdo;
}

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$offset = getPageOffset($page, $limit);

$demoTenantId = 0;

if ($isDemoAdmin) {

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

    $where = "
        WHERE customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
    ";

    $params = [$demoTenantId];

} else {

    $where = "";

    $params = [];
}

if ($search !== '') {

    $where .= "
        AND (
            customers.name LIKE ?
            OR services.title LIKE ?
            OR payments.status LIKE ?
            OR CAST(payments.amount AS CHAR) LIKE ?
            OR CAST(payments.payment_date AS CHAR) LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql = "
    SELECT
        payments.*,
        customers.name AS customer_name,
        services.title AS service_title
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    {$where}
    ORDER BY payments.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $paymentsPdo->prepare($sql);
$stmt->execute($params);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countWhere = "";

$countParams = [];

if (isset($_SESSION['demo_user'])) {

    $countWhere = "
        WHERE customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
    ";

    $countParams[] = $demoTenantId;
}

if ($search !== '') {

    $countWhere .= "
        AND (
            customers.name LIKE ?
            OR services.title LIKE ?
            OR payments.status LIKE ?
            OR CAST(payments.amount AS CHAR) LIKE ?
            OR CAST(payments.payment_date AS CHAR) LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
}

$countSql = "
    SELECT COUNT(*)
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    {$countWhere}
";

$countStmt = $paymentsPdo->prepare($countSql);
$countStmt->execute($countParams);

$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $limit));

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>
        Payments
    </h2>

</div>

<form method="GET" class="row g-3 align-items-end mb-4" id="searchForm">

    <input type="hidden" name="page" value="payments">

    <div class="col-md-10">

        <label for="searchInput" class="form-label">
            Search
        </label>

        <input
            type="text"
            name="search"
            id="searchInput"
            class="form-control"
            placeholder="Search customer, service, amount, status, or date..."
            value="<?= htmlspecialchars($search) ?>"
        >

    </div>

    <div class="col-md-2 d-flex gap-2">

        <button
            type="submit"
            class="btn btn-primary flex-fill">

            Search

        </button>

        <?php if ($search !== ''): ?>

            <a
                href="?page=payments"
                class="btn btn-secondary flex-fill">

                Clear

            </a>

        <?php endif; ?>

    </div>

</form>

<table class="table table-bordered table-hover">

    <thead>

        <tr>

            <th>Customer</th>
            <th>Service</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>

        </tr>

    </thead>

    <tbody>

        <?php foreach ($payments as $payment): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($payment['customer_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($payment['service_title']) ?>
                </td>

                <td>
                    AED <?= number_format($payment['amount'], 2) ?>
                </td>

                <td>
                    <?= htmlspecialchars(($payment['payment_method'] ?? null) ?: 'Bank Transfer') ?>
                </td>

                <td>

                    <?php if ($payment['status'] === 'Paid'): ?>

                        <span class="badge bg-success">
                            Paid
                        </span>

                    <?php elseif ($payment['status'] === 'Unpaid'): ?>

                        <span class="badge bg-danger">
                            Unpaid
                        </span>

                    <?php elseif ($payment['status'] === 'Partially Paid'): ?>

                        <span class="badge bg-warning">
                            Partial
                        </span>

                    <?php else: ?>

                        <span class="badge bg-secondary">
                            <?= htmlspecialchars($payment['status']) ?>
                        </span>

                    <?php endif; ?>

                </td>

                <td>
                    <?= $payment['payment_date'] ?>
                </td>

                <td>

                    <a
                        href="?page=view-payment&id=<?= $payment['id'] ?>"
                        class="btn btn-info btn-sm">

                        View

                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<?php if ($totalPages > 1): ?>

    <nav aria-label="Payments pagination">

        <ul class="pagination justify-content-center">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                <a
                    class="page-link"
                    href="<?= buildPaginationUrl(
                        'payments',
                        max(1, $page - 1),
                        ['search' => $search]
                    ) ?>">

                    Previous

                </a>

            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'payments',
                            $i,
                            ['search' => $search]
                        ) ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                <a
                    class="page-link"
                    href="<?= buildPaginationUrl(
                        'payments',
                        min($totalPages, $page + 1),
                        ['search' => $search]
                    ) ?>">

                    Next

                </a>

            </li>

        </ul>

    </nav>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');

    if (!searchInput || !searchForm) {
        return;
    }

    let timer;

    searchInput.addEventListener('input', function () {

        clearTimeout(timer);

        timer = setTimeout(function () {
            searchForm.submit();
        }, 300);

    });

});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>