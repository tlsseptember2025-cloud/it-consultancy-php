<?php

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Environment
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();

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

    requireAdminLogin();

    $refundPdo = $pdo;

}


/*
|--------------------------------------------------------------------------
| Search & Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$status = $_GET['status'] ?? 'all';


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 10;

$page = max(
    1,
    (int) ($_GET['p'] ?? 1)
);

$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Base Query
|--------------------------------------------------------------------------
*/

$where = "
    WHERE 1=1
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
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status === 'completed') {

    $where .= "
        AND rr.status = 'Approved'
        AND rr.refund_status = 'Completed'
    ";

} elseif ($status === 'rejected') {

    $where .= "
        AND rr.status = 'Rejected'
    ";

} else {

    $where .= "
        AND (
            rr.status = 'Rejected'

            OR

            (
                rr.status = 'Approved'
                AND rr.refund_status = 'Completed'
            )
        )
    ";
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
            OR rr.reason_details LIKE ?
            OR CAST(rr.refund_amount AS CHAR) LIKE ?
            OR DATE_FORMAT(
                rr.reviewed_at,
                '%d-%m-%Y'
            ) LIKE ?
            OR DATE_FORMAT(
                rr.reviewed_at,
                '%d-%m'
            ) LIKE ?
            OR DATE_FORMAT(
                rr.reviewed_at,
                '%Y-%m-%d'
            ) LIKE ?
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
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Count Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    {$where}
";


$countStmt = $refundPdo->prepare(
    $countSql
);

$countStmt->execute(
    $params
);

$totalRecords = (int) $countStmt->fetchColumn();


$totalPages = max(
    1,
    (int) ceil(
        $totalRecords / $perPage
    )
);


/*
|--------------------------------------------------------------------------
| Get Records
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rr.*,
        c.name AS customer_name,
        s.title AS service_title

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    {$where}

    ORDER BY rr.reviewed_at DESC

    LIMIT {$perPage}
    OFFSET {$offset}
";


$stmt = $refundPdo->prepare(
    $sql
);

$stmt->execute(
    $params
);

$refunds = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="mb-4">

    <h2>
        Completed Refunds
    </h2>

</div>


<form
    method="GET"
    id="searchForm"
    class="row g-2 mb-3"
>

    <input
        type="hidden"
        name="page"
        value="archived-refunds"
    >

    <input
        type="hidden"
        name="p"
        value="1"
    >


    <div class="col-md-5">

        <input
            type="text"
            id="searchInput"
            name="search"
            class="form-control"
            placeholder="Search ID, customer, service, reason, amount or date..."
            value="<?= htmlspecialchars($search) ?>"
        >

    </div>


    <div class="col-md-3">

        <select
            name="status"
            class="form-select"
        >

            <option
                value="all"
                <?= $status === 'all' ? 'selected' : '' ?>
            >
                Show All
            </option>


            <option
                value="completed"
                <?= $status === 'completed' ? 'selected' : '' ?>
            >
                Completed
            </option>


            <option
                value="rejected"
                <?= $status === 'rejected' ? 'selected' : '' ?>
            >
                Rejected
            </option>

        </select>

    </div>


    <div class="col-md-2">

        <button
            type="submit"
            class="btn btn-primary w-100"
        >
            Search
        </button>

    </div>


    <div class="col-md-2">

        <a
            href="?page=archived-refunds"
            class="btn btn-secondary w-100"
        >
            Reset
        </a>

    </div>

</form>


<div class="alert alert-light border mb-3">

    Showing

    <strong>
        <?= $totalRecords ?>
    </strong>

    <?= $status === 'rejected'
        ? 'rejected refund' . (
            $totalRecords == 1 ? '' : 's'
        )
        : (
            $status === 'completed'
                ? 'completed refund' . (
                    $totalRecords == 1 ? '' : 's'
                )
                : 'closed refund' . (
                    $totalRecords == 1 ? '' : 's'
                )
        )
    ?>.

</div>


<div class="table-responsive">

    <table class="table table-bordered">

        <thead>

            <tr>

                <th>Customer</th>

                <th>Service</th>

                <th>Refund Amount</th>

                <th>Status</th>

                <th>Closed On</th>

                <th width="120">
                    Action
                </th>

            </tr>

        </thead>


        <tbody>

            <?php if ($refunds): ?>

                <?php foreach ($refunds as $refund): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                $refund['customer_name']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $refund['service_title']
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                $refund['status'] === 'Rejected'
                            ): ?>

                                -

                            <?php else: ?>

                                AED
                                <?= number_format(
                                    (float) $refund['refund_amount'],
                                    2
                                ) ?>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $refund['status'] === 'Rejected'
                            ): ?>

                                <span
                                    class="badge rounded-pill bg-danger"
                                >
                                    Rejected
                                </span>

                            <?php else: ?>

                                <span
                                    class="badge rounded-pill bg-success"
                                >
                                    Completed
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= date(
                                'l, d M Y - h:i A',
                                strtotime(
                                    $refund['reviewed_at']
                                )
                            ) ?>

                        </td>


                        <td>

                            <a
                                href="?page=view-refund&id=<?= (int) $refund['id'] ?>"
                                class="btn btn-sm btn-primary"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="text-center py-5"
                    >

                        <div class="text-muted">

                            <i
                                class="bi bi-search fs-1 d-block mb-3"
                            ></i>

                            <h5>
                                No completed refunds found
                            </h5>

                            <p class="mb-0">
                                No refunds match your search criteria.
                            </p>

                        </div>

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?php if ($totalPages > 1): ?>

    <nav class="mt-4">

        <ul class="pagination justify-content-center">


            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="?page=archived-refunds&p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                    >
                        Previous
                    </a>

                </li>

            <?php endif; ?>


            <?php for (
                $i = 1;
                $i <= $totalPages;
                $i++
            ): ?>

                <li
                    class="page-item <?= $page === $i ? 'active' : '' ?>"
                >

                    <a
                        class="page-link"
                        href="?page=archived-refunds&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                    >
                        <?= $i ?>
                    </a>

                </li>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="?page=archived-refunds&p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                    >
                        Next
                    </a>

                </li>

            <?php endif; ?>


        </ul>

    </nav>

<?php endif; ?>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById(
                'searchInput'
            );

        const searchForm =
            document.getElementById(
                'searchForm'
            );


        if (
            !searchInput ||
            !searchForm
        ) {
            return;
        }


        let timer;


        searchInput.addEventListener(
            'input',
            function () {

                clearTimeout(timer);


                timer = setTimeout(
                    function () {

                        searchForm.submit();

                    },
                    300
                );

            }
        );

    }
);

</script>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>