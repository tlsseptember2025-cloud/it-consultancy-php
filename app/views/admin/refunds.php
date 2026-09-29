<?php

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';


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
| Search & Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();

$page = getPageNumber();

$limit = 5;

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Build Refund Query
|--------------------------------------------------------------------------
*/

$where = "
    WHERE
        rr.status = 'Approved'
        AND rr.refund_status = 'Processing'
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
}


/*
|--------------------------------------------------------------------------
| Load Refunds
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rr.id,
        rr.request_id,
        rr.reason_type,
        rr.reason_details,
        rr.refund_amount,
        rr.refund_status,
        rr.status,
        rr.reviewed_at,

        c.name,
        s.title

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    {$where}

    ORDER BY rr.reviewed_at DESC

    LIMIT {$limit}
    OFFSET {$offset}
";


$stmt = $refundPdo->prepare($sql);

$stmt->execute($params);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Count Refunds
|--------------------------------------------------------------------------
*/

$countWhere = "
    WHERE
        rr.status = 'Approved'
        AND rr.refund_status = 'Processing'
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
        ON r.id = rr.request_id

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

    {$countWhere}
";


$countStmt = $refundPdo->prepare(
    $countSql
);

$countStmt->execute(
    $countParams
);

$totalRecords = (int) $countStmt->fetchColumn();


$totalPages = max(
    1,
    (int) ceil(
        $totalRecords / $limit
    )
);

?>


<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>


<div class="d-flex justify-content-between align-items-center mb-4">

    <h1>
        Refunds Pending Finance
    </h1>

</div>


<!-- Search -->

<form
    method="GET"
    class="row g-3 align-items-end mb-4"
    id="searchForm"
>

    <input
        type="hidden"
        name="page"
        value="refunds"
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
            placeholder="Search ID, customer, service, amount, reason, or date..."
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
                href="?page=refunds"
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

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Customer</th>

                        <th>Service</th>

                        <th>Amount</th>

                        <th>Date</th>

                        <th>Reason</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($refunds)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center text-muted"
                            >
                                No refunds found.
                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($refunds as $refund): ?>

                            <tr>

                                <td>
                                    <?= (int) $refund['id'] ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $refund['name']
                                    ) ?>
                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $refund['title']
                                    ) ?>
                                </td>


                                <td>

                                    AED <?= number_format(
                                        (float) $refund['refund_amount'],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $refund['reviewed_at']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $refund['reason_type']
                                    ) ?>

                                </td>


                                <td>

                                    <?php

                                    $status = trim(
                                        (string) (
                                            $refund['refund_status']
                                            ?? ''
                                        )
                                    );

                                    ?>


                                    <?php if (
                                        $status === 'Processing'
                                    ): ?>

                                        <span
                                            class="badge bg-warning text-dark"
                                        >
                                            Processing
                                        </span>


                                        <br>


                                        <small
                                            class="text-muted"
                                        >
                                            Awaiting completion by
                                            finance team.
                                        </small>


                                        <br><br>


                                        <a
                                            href="?page=complete-refund&id=<?= (int) $refund['id'] ?>"
                                            class="btn btn-success btn-sm"
                                        >
                                            Complete Refund
                                        </a>


                                    <?php elseif (
                                        $status === 'Completed'
                                    ): ?>

                                        <span
                                            class="badge bg-success"
                                        >
                                            Completed
                                        </span>


                                        <br>


                                        <small
                                            class="text-muted"
                                        >
                                            Refund successfully
                                            processed.
                                        </small>


                                    <?php else: ?>

                                        <span
                                            class="badge bg-secondary"
                                        >
                                            <?= htmlspecialchars(
                                                $status ?: 'Unknown'
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- Pagination -->

        <?php if ($totalPages > 1): ?>

            <nav
                aria-label="Refund Management pagination"
            >

                <ul class="pagination justify-content-center">


                    <li
                        class="page-item
                        <?= $page <= 1 ? 'disabled' : '' ?>"
                    >

                        <a
                            class="page-link"
                            href="<?= buildPaginationUrl(
                                'refunds',
                                max(
                                    1,
                                    $page - 1
                                ),
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
                            class="page-item
                            <?= $i === $page
                                ? 'active'
                                : ''
                            ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= buildPaginationUrl(
                                    'refunds',
                                    $i,
                                    ['search' => $search]
                                ) ?>"
                            >
                                <?= $i ?>
                            </a>

                        </li>

                    <?php endfor; ?>


                    <li
                        class="page-item
                        <?= $page >= $totalPages
                            ? 'disabled'
                            : ''
                        ?>"
                    >

                        <a
                            class="page-link"
                            href="<?= buildPaginationUrl(
                                'refunds',
                                min(
                                    $totalPages,
                                    $page + 1
                                ),
                                ['search' => $search]
                            ) ?>"
                        >
                            Next
                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

    </div>

</div>


<!-- Live Search -->

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