<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Environment
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

/*
|--------------------------------------------------------------------------
| Authentication / Admin Context
|--------------------------------------------------------------------------
*/

requireAdminLogin();

if ($isDemoSuperAdmin) {

    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $refundPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    require_once CONFIG_PATH . '/database.php';

    $refundPdo = $pdo;

}


/*
|--------------------------------------------------------------------------
| Search + Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();
$page   = getPageNumber();
$limit  = 10;
$offset = getPageOffset($page, $limit);


/*
|--------------------------------------------------------------------------
| Base WHERE
|--------------------------------------------------------------------------
*/

$where = "
    WHERE rr.status = 'Pending'
";

$params = [];


/*
|--------------------------------------------------------------------------
| Demo Tenant Isolation
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $where .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
    ";

    $params[] = $demoTenantId;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where .= "
        AND (
            c.name LIKE ?
            OR s.title LIKE ?
            OR rr.reason_type LIKE ?
            OR rr.status LIKE ?
            OR DATE_FORMAT(rr.created_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(rr.created_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(rr.created_at, '%Y-%m-%d') LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Load Refund Requests
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rr.id AS refund_id,
        rr.*,
        c.name AS customer_name,
        s.title AS service_title

    FROM refund_requests rr

    JOIN requests r
        ON rr.request_id = r.id

    JOIN customers c
        ON r.customer_id = c.id

    JOIN services s
        ON r.service_id = s.id

    {$where}

    ORDER BY rr.created_at DESC

    LIMIT {$limit}
    OFFSET {$offset}
";

$stmt = $refundPdo->prepare($sql);

$stmt->execute($params);

$refundRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Count Records
|--------------------------------------------------------------------------
*/

$countWhere = "
    WHERE rr.status = 'Pending'
";

$countParams = [];


/*
|--------------------------------------------------------------------------
| Demo Tenant Isolation For Count
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $countWhere .= "
        AND c.demo_tenant_id = ?
        AND c.is_demo_account = 1
    ";

    $countParams[] = $demoTenantId;
}


/*
|--------------------------------------------------------------------------
| Search For Count
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $countWhere .= "
        AND (
            c.name LIKE ?
            OR s.title LIKE ?
            OR rr.reason_type LIKE ?
            OR rr.status LIKE ?
            OR DATE_FORMAT(rr.created_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(rr.created_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(rr.created_at, '%Y-%m-%d') LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
    $countParams[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Count Query
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)

    FROM refund_requests rr

    JOIN requests r
        ON rr.request_id = r.id

    JOIN customers c
        ON r.customer_id = c.id

    JOIN services s
        ON r.service_id = s.id

    {$countWhere}
";

$countStmt = $refundPdo->prepare($countSql);

$countStmt->execute($countParams);

$totalRecords = (int) $countStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil($totalRecords / $limit)
);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h1 class="mb-4">
    Refund Requests
</h1>


<form
    method="GET"
    class="row g-3 align-items-end mb-4"
    id="searchForm"
>

    <input
        type="hidden"
        name="page"
        value="refund-requests"
    >


    <div class="col-md-10">

        <label
            for="searchInput"
            class="form-label"
        >
            Search
        </label>

        <input
            type="text"
            name="search"
            id="searchInput"
            class="form-control"
            placeholder="Search customer, service, reason, status, or date..."
            value="<?= htmlspecialchars($search) ?>"
        >

    </div>


    <div class="col-md-2 d-flex gap-2">

        <button
            type="submit"
            class="btn btn-primary flex-fill"
        >
            Search
        </button>


        <?php if ($search !== ''): ?>

            <a
                href="?page=refund-requests"
                class="btn btn-secondary flex-fill"
            >
                Clear
            </a>

        <?php endif; ?>

    </div>

</form>


<div class="card shadow-sm">

    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>

                    <tr>

                        <th>Customer</th>

                        <th>Service</th>

                        <th>Reason</th>

                        <th>Status</th>

                        <th>Requested On</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if ($refundRequests): ?>

                        <?php foreach ($refundRequests as $request): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $request['customer_name']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['service_title']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['reason_type']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $request['status']
                                    ) ?>
                                </td>


                                <td>
                                    <?= formatDate(
                                        $request['created_at']
                                    ) ?>
                                </td>


                                <td>

                                    <?php if (
                                        $request['status'] === 'Pending'
                                    ): ?>

                                        <a
                                            href="?page=review-refund&id=<?= (int) $request['refund_id'] ?>"
                                            class="btn btn-primary btn-sm"
                                        >
                                            Review
                                        </a>

                                    <?php else: ?>

                                        <?php if (
                                            $request['status'] === 'Approved'
                                        ): ?>

                                            <span class="badge bg-success">
                                                Approved
                                            </span>

                                        <?php elseif (
                                            $request['status'] === 'Rejected'
                                        ): ?>

                                            <span class="badge bg-danger">
                                                Rejected
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">
                                                Pending
                                            </span>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center py-4"
                            >
                                No refund requests found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php if ($totalPages > 1): ?>

    <nav aria-label="Refund Requests pagination">

        <ul class="pagination justify-content-center">


            <li
                class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"
            >

                <a
                    class="page-link"
                    href="<?= buildPaginationUrl(
                        'refund-requests',
                        max(1, $page - 1),
                        ['search' => $search]
                    ) ?>"
                >
                    Previous
                </a>

            </li>


            <?php for (
                $i = 1;
                $i <= $totalPages;
                $i++
            ): ?>

                <li
                    class="page-item <?= $i === $page ? 'active' : '' ?>"
                >

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'refund-requests',
                            $i,
                            ['search' => $search]
                        ) ?>"
                    >
                        <?= $i ?>
                    </a>

                </li>

            <?php endfor; ?>


            <li
                class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"
            >

                <a
                    class="page-link"
                    href="<?= buildPaginationUrl(
                        'refund-requests',
                        min($totalPages, $page + 1),
                        ['search' => $search]
                    ) ?>"
                >
                    Next
                </a>

            </li>

        </ul>

    </nav>

<?php endif; ?>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('searchInput');

    const searchForm =
        document.getElementById('searchForm');


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