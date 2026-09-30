<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Customer database / authentication context
|--------------------------------------------------------------------------
|
| Normal Customer:
|   - Uses the main/local database through $pdo.
|
| Demo Customer:
|   - Uses the Demo database through $demoPdo.
|   - Must belong to the tenant stored in the Demo session.
|   - Must be marked as a Demo account.
|
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Demo Customer belongs to current Demo tenant
    |--------------------------------------------------------------------------
    */

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

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

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
| Base Query Conditions
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $where = "
        WHERE r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          
          AND s.demo_tenant_id = c.demo_tenant_id
    ";

    $params = [
        $customerId,
        $demoTenantId
    ];

} else {

    $where = "
        WHERE r.customer_id = ?
    ";

    $params = [
        $customerId
    ];
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Broad search across:
|
| - Refund reference
| - Request reference
| - Service
| - Reason type
| - Reason details
| - Status
| - Refund status
| - Refund amount
| - Requested date
|
| Supports:
|
| DD-MM
| DD-MM-YYYY
| YYYY-MM-DD
|
|--------------------------------------------------------------------------
*/

$searchColumns = [

    "CAST(rr.id AS CHAR)",

    "CAST(rr.request_id AS CHAR)",

    "s.title",

    "rr.reason_type",

    "rr.reason_details",

    "rr.status",

    "rr.refund_status",

    "CAST(rr.refund_amount AS CHAR)",

    "DATE_FORMAT(rr.created_at, '%d-%m')",

    "DATE_FORMAT(rr.created_at, '%d-%m-%Y')",

    "DATE_FORMAT(rr.created_at, '%Y-%m-%d')"

];

$where .= buildSearchCondition(
    $searchColumns,
    $search,
    $params
);

/*
|--------------------------------------------------------------------------
| Count Total Refunds
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)

    FROM refund_requests rr

    JOIN requests r
        ON rr.request_id = r.id

    JOIN services s
        ON r.service_id = s.id

    JOIN customers c
        ON r.customer_id = c.id

    {$where}
";

$stmt = $customerPdo->prepare($countSql);

$countParams = $params;

$stmt->execute($countParams);

$totalRefunds = (int) $stmt->fetchColumn();

$totalPages = getTotalPages(
    $totalRefunds,
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
| Load Refunds
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rr.*,
        s.title AS service_title

    FROM refund_requests rr

    JOIN requests r
        ON rr.request_id = r.id

    JOIN services s
        ON r.service_id = s.id

    JOIN customers c
        ON r.customer_id = c.id

    {$where}

    ORDER BY rr.created_at DESC, rr.id DESC

    LIMIT {$limit}

    OFFSET {$offset}
";

$stmt = $customerPdo->prepare($sql);

/*
|--------------------------------------------------------------------------
| Bind Search Parameters
|--------------------------------------------------------------------------
|
| The helper creates one parameter for every searchable column.
| Bind them in the same order they were created.
|
|--------------------------------------------------------------------------
*/

$stmt->execute($params);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>

<div class="container py-5">

    <!-- Page Header -->

    <div class="mb-4">

        <h2 class="mb-1">

            My Refunds

        </h2>

        <p class="text-muted mb-0">

            Track your refund requests and view the complete refund history for each request.

        </p>

    </div>

    <!-- Search -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                action=""
                id="refundSearchForm"
                class="row g-2 align-items-center">

                <input
                    type="hidden"
                    name="page"
                    value="customer-refunds">

                <input
                    type="hidden"
                    name="p"
                    value="1">

                <div class="col-md-9">

                    <input
                        type="text"
                        name="search"
                        id="refundSearch"
                        class="form-control"
                        placeholder="Search refund, service, reason, status, amount or date..."
                        value="<?= htmlspecialchars($search) ?>"
                        autocomplete="off">

                </div>

                <div class="col-md-3 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-fill">

                        Search

                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="?page=customer-refunds"
                            class="btn btn-secondary">

                            Clear

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

    </div>

    <!-- Refund Table -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>
                                Reference
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Reason
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Requested On
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($refunds): ?>

                            <?php foreach ($refunds as $refund): ?>

                                <?php

                                /*
                                |--------------------------------------------------------------------------
                                | Determine Customer-Facing Status
                                |--------------------------------------------------------------------------
                                */

                                $refundStatus = $refund['status'] ?? '';

                                $paymentStatus = $refund['refund_status'] ?? '';

                                $displayStatus = 'Pending';

                                $statusClass = 'bg-warning text-dark';

                                $statusText =
                                    'Your refund request is under review.';

                                if ($refundStatus === 'Rejected') {

                                    $displayStatus = 'Rejected';

                                    $statusClass = 'bg-danger';

                                    $statusText =
                                        'This refund request was not approved.';

                                } elseif (
                                    $refundStatus === 'Approved'
                                    &&
                                    $paymentStatus === 'Completed'
                                ) {

                                    $displayStatus = 'Completed';

                                    $statusClass = 'bg-success';

                                    $statusText =
                                        'Refund completed.';

                                } elseif (
                                    $refundStatus === 'Approved'
                                    &&
                                    $paymentStatus === 'Processing'
                                ) {

                                    $displayStatus = 'Processing';

                                    $statusClass =
                                        'bg-warning text-dark';

                                    $statusText =
                                        'Refund is being processed.';

                                } elseif (
                                    $refundStatus === 'Approved'
                                ) {

                                    $displayStatus = 'Approved';

                                    $statusClass = 'bg-primary';

                                    $statusText =
                                        'Refund approved.';

                                } elseif ($refundStatus !== '') {

                                    $displayStatus = $refundStatus;

                                    if ($refundStatus === 'Pending') {

                                        $statusClass =
                                            'bg-warning text-dark';

                                        $statusText =
                                            'Your refund request is under review.';

                                    } else {

                                        $statusClass =
                                            'bg-secondary';

                                        $statusText = '';
                                    }
                                }

                                ?>

                                <tr>

                                    <!-- Reference -->

                                    <td>

                                        <strong>

                                            RF-<?= str_pad(
                                                $refund['id'],
                                                6,
                                                '0',
                                                STR_PAD_LEFT
                                            ) ?>

                                        </strong>

                                    </td>

                                    <!-- Service -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $refund['service_title']
                                        ) ?>

                                    </td>

                                    <!-- Reason -->

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $refund['reason_type']
                                            ) ?>

                                        </strong>

                                        <?php if (!empty($refund['reason_details'])): ?>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $refund['reason_details']
                                                ) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <!-- Status -->

                                    <td>

                                        <span class="badge <?= $statusClass ?>">

                                            <?= htmlspecialchars(
                                                $displayStatus
                                            ) ?>

                                        </span>

                                        <?php if ($statusText !== ''): ?>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $statusText
                                                ) ?>

                                            </small>

                                        <?php endif; ?>

                                        <?php if (
                                            in_array(
                                                $displayStatus,
                                                [
                                                    'Approved',
                                                    'Processing',
                                                    'Completed'
                                                ],
                                                true
                                            )
                                        ): ?>

                                            <br>

                                            <small>

                                                Refund Amount:

                                                <strong>

                                                    <?php

                                                    $amount =
                                                        $refund['refund_amount']
                                                        ?? null;

                                                    if ($amount !== null) {

                                                        echo 'AED ' .
                                                            number_format(
                                                                (float) $amount,
                                                                2
                                                            );

                                                    } else {

                                                        echo 'Pending';
                                                    }

                                                    ?>

                                                </strong>

                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <!-- Requested On -->

                                    <td>

                                        <?= formatDateTime(
                                            $refund['created_at']
                                        ) ?>

                                    </td>

                                    <!-- Action -->

                                    <td>

                                        <a
                                            href="?page=customer-view-refund&id=<?= (int) $refund['id'] ?>"
                                            class="btn btn-sm btn-outline-primary">

                                            View Details

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-4">

                                    <?php if ($search !== ''): ?>

                                        No refunds found matching
                                        "<strong><?= htmlspecialchars($search) ?></strong>".

                                    <?php else: ?>

                                        No refund requests found.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <!-- Result Count -->

            <?php if ($totalRefunds > 0): ?>

                <div class="text-muted mt-3">

                    Showing

                    <strong>
                        <?= $offset + 1 ?>
                    </strong>

                    to

                    <strong>
                        <?= min(
                            $offset + $limit,
                            $totalRefunds
                        ) ?>
                    </strong>

                    of

                    <strong>
                        <?= $totalRefunds ?>
                    </strong>

                    refund<?= $totalRefunds == 1 ? '' : 's' ?>.

                </div>

            <?php endif; ?>

            <!-- Pagination -->

            <?php if ($totalPages > 1): ?>

                <div class="d-flex justify-content-center mt-4">

                    <nav aria-label="Refund pagination">

                        <ul class="pagination mb-0">

                            <?php if ($page > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            buildPaginationUrl(
                                                'customer-refunds',
                                                $page - 1,
                                                ['search' => $search]
                                            )
                                        ) ?>">

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
                                    class="page-item <?= $i === $page ? 'active' : '' ?>">

                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            buildPaginationUrl(
                                                'customer-refunds',
                                                $i,
                                                ['search' => $search]
                                            )
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
                                                'customer-refunds',
                                                $page + 1,
                                                ['search' => $search]
                                            )
                                        ) ?>">

                                        Next

                                    </a>

                                </li>

                            <?php endif; ?>

                        </ul>

                    </nav>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Live Search -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('refundSearch');

    const searchForm =
        document.getElementById('refundSearchForm');

    if (!searchInput || !searchForm) {

        return;
    }

    let searchTimer;

    searchInput.addEventListener('input', function () {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {

            const searchValue =
                searchInput.value.trim();

            const url =
                new URL(window.location.href);

            url.searchParams.set(
                'page',
                'customer-refunds'
            );

            url.searchParams.set(
                'p',
                '1'
            );

            if (searchValue !== '') {

                url.searchParams.set(
                    'search',
                    searchValue
                );

            } else {

                url.searchParams.delete(
                    'search'
                );
            }

            window.location.href =
                url.toString();

        }, 300);

    });

});

</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>