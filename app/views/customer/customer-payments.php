<?php

require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Customer database / authentication context
|--------------------------------------------------------------------------
|
| Normal Customer uses the main database through $pdo.
| Demo Customer uses the Demo database through $demoPdo and is restricted
| to the tenant stored in the Demo session.
|
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) ($_SESSION['demo_customer']['id'] ?? 0);

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    $demoCustomerCheck = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $demoCustomerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$demoCustomerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;
    }

    requireCustomerLogin();

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

require dirname(__DIR__) . '/layouts/header-customer.php';

/*
|--------------------------------------------------------------------------
| Search + Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();
$page   = getPageNumber();
$limit  = getPageLimit(10);

/*
|--------------------------------------------------------------------------
| Searchable columns
|--------------------------------------------------------------------------
|
| Broad partial search across:
|
| - Payment ID
| - Request ID
| - Service
| - Amount
| - Payment status
| - Payment notes
| - Payment date
|
| Date formats supported:
|
| DD-MM
| DD-MM-YYYY
| YYYY-MM-DD
|
|--------------------------------------------------------------------------
*/

$searchColumns = [

    'CAST(p.id AS CHAR)',

    'CAST(p.request_id AS CHAR)',

    's.title',

    'CAST(p.amount AS CHAR)',

    'p.status',

    'p.notes',

    "DATE_FORMAT(p.payment_date, '%d-%m')",

    "DATE_FORMAT(p.payment_date, '%d-%m-%Y')",

    "DATE_FORMAT(p.payment_date, '%Y-%m-%d')"

];

/*
|--------------------------------------------------------------------------
| Count matching records
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $countParams = [
        $customerId,
        $demoTenantId
    ];

} else {

    $countParams = [
        $customerId
    ];
}

$countSearchCondition = buildSearchCondition(
    $searchColumns,
    $search,
    $countParams
);

if ($isDemoCustomer) {

    $countSql = "
        SELECT COUNT(*)
        FROM payments p
        JOIN requests r
            ON p.request_id = r.id
        JOIN services s
            ON r.service_id = s.id
        JOIN customers c
            ON r.customer_id = c.id
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          
          AND s.demo_tenant_id = c.demo_tenant_id
          {$countSearchCondition}
    ";

} else {

    $countSql = "
        SELECT COUNT(*)
        FROM payments p
        JOIN requests r
            ON p.request_id = r.id
        JOIN services s
            ON r.service_id = s.id
        WHERE r.customer_id = ?
          {$countSearchCondition}
    ";
}

$countStmt = $customerPdo->prepare($countSql);

/*
|--------------------------------------------------------------------------
| Bind count parameters
|--------------------------------------------------------------------------
|
| The helper generates all search parameters.
| We bind exactly what exists in $countParams.
|
|--------------------------------------------------------------------------
*/

foreach ($countParams as $index => $value) {

    $countStmt->bindValue(
        $index + 1,
        $value,
        PDO::PARAM_STR
    );

}

$countStmt->execute();

$totalRecords = (int) $countStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Calculate total pages
|--------------------------------------------------------------------------
*/

$totalPages = getTotalPages(
    $totalRecords,
    $limit
);

/*
|--------------------------------------------------------------------------
| Prevent invalid page number
|--------------------------------------------------------------------------
*/

if ($page > $totalPages) {

    $page = $totalPages;

}

$offset = getPageOffset(
    $page,
    $limit
);

/*
|--------------------------------------------------------------------------
| Get paginated payments
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $requestParams = [
        $customerId,
        $demoTenantId
    ];

} else {

    $requestParams = [
        $customerId
    ];
}

$requestSearchCondition = buildSearchCondition(
    $searchColumns,
    $search,
    $requestParams
);

if ($isDemoCustomer) {

    $sql = "
        SELECT
            p.*,
            s.title AS service_title
        FROM payments p
        JOIN requests r
            ON p.request_id = r.id
        JOIN services s
            ON r.service_id = s.id
        JOIN customers c
            ON r.customer_id = c.id
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          
          AND s.demo_tenant_id = c.demo_tenant_id
          {$requestSearchCondition}
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    ";

} else {

    $sql = "
        SELECT
            p.*,
            s.title AS service_title
        FROM payments p
        JOIN requests r
            ON p.request_id = r.id
        JOIN services s
            ON r.service_id = s.id
        WHERE r.customer_id = ?
          {$requestSearchCondition}
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    ";
}

$stmt = $customerPdo->prepare($sql);

/*
|--------------------------------------------------------------------------
| Bind customer ID + search parameters
|--------------------------------------------------------------------------
*/

$bindIndex = 1;

foreach ($requestParams as $value) {

    $stmt->bindValue(
        $bindIndex++,
        $value,
        PDO::PARAM_STR
    );

}

/*
|--------------------------------------------------------------------------
| Bind pagination values
|--------------------------------------------------------------------------
*/

$stmt->bindValue(
    $bindIndex++,
    $limit,
    PDO::PARAM_INT
);

$stmt->bindValue(
    $bindIndex++,
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$payments = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Pagination URLs
|--------------------------------------------------------------------------
*/

$paginationParams = [];

if ($search !== '') {

    $paginationParams['search'] = $search;

}

$previousPageUrl = null;
$nextPageUrl     = null;

if ($page > 1) {

    $previousPageUrl = buildPaginationUrl(
        'customer-payments',
        $page - 1,
        $paginationParams
    );

}

if ($page < $totalPages) {

    $nextPageUrl = buildPaginationUrl(
        'customer-payments',
        $page + 1,
        $paginationParams
    );

}

?>

<h1 class="mb-4">
    My Payments
</h1>

<!--
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
-->

<div class="card mb-3">

    <div class="card-body">

        <form
            method="get"
            action=""
            id="customerPaymentSearchForm"
        >

            <input
                type="hidden"
                name="page"
                value="customer-payments"
            >

            <div class="row align-items-end">

                <div class="col-md-8">

                    <label
                        for="customerPaymentSearch"
                        class="form-label"
                    >
                        Search Payments
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="customerPaymentSearch"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search payment #, request #, service, amount or date..."
                        autocomplete="off"
                    >

                    <small class="text-muted">

                        Date search supports
                        <strong>DD-MM</strong>,
                        <strong>DD-MM-YYYY</strong>,
                        and
                        <strong>YYYY-MM-DD</strong>.

                    </small>

                </div>

                <div class="col-md-4 mt-3 mt-md-0">

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Search
                        </button>

                        <?php if ($search !== ''): ?>

                            <a
                                href="?page=customer-payments"
                                class="btn btn-outline-secondary"
                            >
                                Clear
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>

<!--
|--------------------------------------------------------------------------
| Results information
|--------------------------------------------------------------------------
-->

<div class="d-flex justify-content-between align-items-center mb-2">

    <div class="text-muted">

        <?php if ($totalRecords > 0): ?>

            Showing

            <strong>
                <?= $offset + 1 ?>
            </strong>

            -

            <strong>
                <?= min(
                    $offset + $limit,
                    $totalRecords
                ) ?>
            </strong>

            of

            <strong>
                <?= $totalRecords ?>
            </strong>

            payment<?= $totalRecords === 1 ? '' : 's' ?>

        <?php else: ?>

            No payments found.

        <?php endif; ?>

    </div>

    <?php if ($search !== ''): ?>

        <div class="text-muted">

            Search:

            <strong>
                <?= htmlspecialchars($search) ?>
            </strong>

        </div>

    <?php endif; ?>

</div>

<!--
|--------------------------------------------------------------------------
| Payments table
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm">

    <div class="card-body">

        <?php if (empty($payments)): ?>

            <div class="text-center py-4">

                <?php if ($search !== ''): ?>

                    <p class="text-muted mb-3">

                        No payments matched your search.

                    </p>

                    <a
                        href="?page=customer-payments"
                        class="btn btn-outline-secondary"
                    >
                        Clear Search
                    </a>

                <?php else: ?>

                    <p class="text-muted mb-0">

                        No payments found.

                    </p>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>
                                Service
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $payment['service_title']
                                    ) ?>

                                </td>

                                <td>

                                    AED

                                    <?= number_format(
                                        $payment['amount'],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        ($payment['payment_method'] ?? null)
                                            ?: 'Bank Transfer'
                                    ) ?>

                                </td>

                                <td>

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $payment['payment_date']
                                        )
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<!--
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
|
| Self-contained styling.
| This avoids depending on the existing site's pagination CSS.
|
|--------------------------------------------------------------------------
-->

<?php if ($totalPages > 1): ?>

    <div
        style="
            width:100% !important;
            display:block !important;
            text-align:center !important;
            margin-top:20px !important;
            margin-bottom:20px !important;
        "
    >

        <div
            style="
                display:inline-flex !important;
                align-items:center !important;
                justify-content:center !important;
                gap:8px !important;
            "
        >

            <!-- Previous -->

            <?php if ($page > 1): ?>

                <a
                    href="<?= htmlspecialchars($previousPageUrl) ?>"
                    style="
                        display:inline-block;
                        padding:6px 12px;
                        border:1px solid #0d6efd;
                        border-radius:4px;
                        text-decoration:none;
                        color:#0d6efd;
                        background:#fff;
                        font-size:14px;
                    "
                >
                    Previous
                </a>

            <?php else: ?>

                <span
                    style="
                        display:inline-block;
                        padding:6px 12px;
                        border:1px solid #ced4da;
                        border-radius:4px;
                        color:#6c757d;
                        background:#e9ecef;
                        font-size:14px;
                    "
                >
                    Previous
                </span>

            <?php endif; ?>

            <!-- Page numbers -->

            <?php for (
                $pageNumber = 1;
                $pageNumber <= $totalPages;
                $pageNumber++
            ): ?>

                <?php if ($pageNumber === $page): ?>

                    <span
                        style="
                            display:inline-block;
                            padding:6px 12px;
                            border:1px solid #0d6efd;
                            border-radius:4px;
                            color:#fff;
                            background:#0d6efd;
                            font-size:14px;
                            font-weight:500;
                        "
                    >
                        <?= $pageNumber ?>
                    </span>

                <?php else: ?>

                    <a
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'customer-payments',
                                $pageNumber,
                                $paginationParams
                            )
                        ) ?>"
                        style="
                            display:inline-block;
                            padding:6px 12px;
                            border:1px solid #0d6efd;
                            border-radius:4px;
                            text-decoration:none;
                            color:#0d6efd;
                            background:#fff;
                            font-size:14px;
                        "
                    >
                        <?= $pageNumber ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>

            <!-- Next -->

            <?php if ($page < $totalPages): ?>

                <a
                    href="<?= htmlspecialchars($nextPageUrl) ?>"
                    style="
                        display:inline-block;
                        padding:6px 12px;
                        border:1px solid #0d6efd;
                        border-radius:4px;
                        text-decoration:none;
                        color:#0d6efd;
                        background:#fff;
                        font-size:14px;
                    "
                >
                    Next
                </a>

            <?php else: ?>

                <span
                    style="
                        display:inline-block;
                        padding:6px 12px;
                        border:1px solid #ced4da;
                        border-radius:4px;
                        color:#6c757d;
                        background:#e9ecef;
                        font-size:14px;
                    "
                >
                    Next
                </span>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

<!--
|--------------------------------------------------------------------------
| Live Search - 300ms debounce
|--------------------------------------------------------------------------
|
| The search remains server-side.
| JavaScript waits 300ms after typing stops and reloads
| the page with the search term.
|
|--------------------------------------------------------------------------
-->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById(
            'customerPaymentSearch'
        );

    const searchForm =
        document.getElementById(
            'customerPaymentSearchForm'
        );

    if (!searchInput || !searchForm) {

        return;

    }

    let searchTimer = null;

    searchInput.addEventListener(
        'input',
        function () {

            clearTimeout(searchTimer);

            searchTimer = setTimeout(
                function () {

                    const searchValue =
                        searchInput.value.trim();

                    const currentUrl =
                        new URL(
                            window.location.href
                        );

                    currentUrl.searchParams.set(
                        'page',
                        'customer-payments'
                    );

                    /*
                     * Every new search starts
                     * from page 1.
                     */

                    currentUrl.searchParams.delete(
                        'p'
                    );

                    if (searchValue === '') {

                        currentUrl.searchParams.delete(
                            'search'
                        );

                    } else {

                        currentUrl.searchParams.set(
                            'search',
                            searchValue
                        );

                    }

                    window.location.href =
                        currentUrl.toString();

                },
                300
            );

        }
    );

});

</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>