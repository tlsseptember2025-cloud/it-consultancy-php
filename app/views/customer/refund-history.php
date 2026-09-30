<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Type
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheckStmt = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id = ?
        LIMIT 1
    ");

    $customerCheckStmt->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheckStmt->fetch(PDO::FETCH_ASSOC)) {

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
| Search / Filters / Pagination
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';

$perPage = 10;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;


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
| Status Filter
|--------------------------------------------------------------------------
*/

switch ($status) {

    case 'completed':

        $where .= "
            AND rr.status = 'Approved'
            AND rr.refund_status = 'Completed'
        ";

        break;


    case 'processing':

        $where .= "
            AND rr.refund_status = 'Processing'
        ";

        break;


    case 'pending':

        $where .= "
            AND rr.status = 'Pending'
        ";

        break;


    case 'rejected':

        $where .= "
            AND rr.status = 'Rejected'
        ";

        break;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where .= "
        AND s.title LIKE ?
    ";

    $params[] = "%{$search}%";
}


/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    $countSql = "

        SELECT COUNT(*)

        FROM refund_requests rr

        JOIN requests r
            ON r.id = rr.request_id

        JOIN services s
            ON s.id = r.service_id

        JOIN customers c
            ON c.id = r.customer_id

        {$where}

    ";

} else {

    $countSql = "

        SELECT COUNT(*)

        FROM refund_requests rr

        JOIN requests r
            ON r.id = rr.request_id

        JOIN services s
            ON s.id = r.service_id

        JOIN customers c
            ON c.id = r.customer_id

        {$where}

    ";
}


$stmt = $customerPdo->prepare($countSql);
$stmt->execute($params);

$totalRefunds = (int) $stmt->fetchColumn();

$totalPages = (int) ceil($totalRefunds / $perPage);


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT
        rr.*,
        s.title AS service_title

    FROM refund_requests rr

    JOIN requests r
        ON r.id = rr.request_id

    JOIN services s
        ON s.id = r.service_id

    JOIN customers c
        ON c.id = r.customer_id

    {$where}

    ORDER BY rr.created_at DESC

    LIMIT {$perPage}

    OFFSET {$offset}

";


$stmt = $customerPdo->prepare($sql);
$stmt->execute($params);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

switch ($status) {

    case 'completed':
        $summary = 'completed refund';
        break;

    case 'processing':
        $summary = 'processing refund';
        break;

    case 'pending':
        $summary = 'pending refund';
        break;

    case 'rejected':
        $summary = 'rejected refund';
        break;

    default:
        $summary = 'refund';
}


require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="container py-5">

    <h2 class="mb-4">Refund History</h2>

    <form method="GET" class="row mb-4">

        <input
            type="hidden"
            name="page"
            value="refund-history">

        <div class="col-md-5">

            <input
                type="text"
                class="form-control"
                name="search"
                placeholder="Search service..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">

        </div>

        <div class="col-md-3">

            <select
                class="form-select"
                name="status">

                <option
                    value="all"
                    <?= $status === 'all' ? 'selected' : '' ?>>
                    Show All
                </option>

                <option
                    value="pending"
                    <?= $status === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option
                    value="processing"
                    <?= $status === 'processing' ? 'selected' : '' ?>>
                    Processing
                </option>

                <option
                    value="completed"
                    <?= $status === 'completed' ? 'selected' : '' ?>>
                    Completed
                </option>

                <option
                    value="rejected"
                    <?= $status === 'rejected' ? 'selected' : '' ?>>
                    Rejected
                </option>

            </select>

        </div>

        <div class="col-md-2">

            <button
                class="btn btn-primary w-100">

                Search

            </button>

        </div>

        <div class="col-md-2">

            <a
                href="?page=refund-history"
                class="btn btn-secondary w-100">

                Reset

            </a>

        </div>

    </form>


    <div class="alert alert-light border">

        Showing

        <strong><?= $totalRefunds ?></strong>

        <?= htmlspecialchars($summary) ?>
        <?= $totalRefunds == 1 ? '' : 's' ?>.

    </div>


    <div class="table-responsive">

        <table class="table table-bordered table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th>Service</th>

                    <th>Refund Amount</th>

                    <th>Status</th>

                    <th>Requested On</th>

                    <th width="120">Action</th>

                </tr>

            </thead>

            <tbody>

            <?php if (count($refunds) > 0): ?>

                <?php foreach ($refunds as $refund): ?>

                <tr>

                    <td>

                        <?= htmlspecialchars(
                            $refund['service_title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </td>

                    <td>

                        <?php if ($refund['status'] === 'Rejected'): ?>

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

                        <?php

                        if ($refund['status'] === 'Pending') {

                            echo '<span class="badge bg-primary">Pending</span>';

                        } elseif (
                            $refund['refund_status'] === 'Processing'
                        ) {

                            echo '<span class="badge bg-warning text-dark">Processing</span>';

                        } elseif (
                            $refund['status'] === 'Rejected'
                        ) {

                            echo '<span class="badge bg-danger">Rejected</span>';

                        } else {

                            echo '<span class="badge bg-success">Completed</span>';
                        }

                        ?>

                    </td>

                    <td>

                        <?= formatDateTime(
                            $refund['created_at']
                        ) ?>

                    </td>

                    <td>

                        <a
                            href="?page=customer-view-refund&id=<?= (int) $refund['id'] ?>"
                            class="btn btn-sm btn-primary">

                            View

                        </a>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="text-center py-5">

                        <div class="text-muted">

                            <i class="bi bi-search fs-1 d-block mb-3"></i>

                            <h5>No refunds found</h5>

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
                        href="?page=refund-history&p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

                        Previous

                    </a>

                </li>

            <?php endif; ?>


            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li
                    class="page-item <?= $page == $i ? 'active' : '' ?>">

                    <a
                        class="page-link"
                        href="?page=refund-history&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="?page=refund-history&p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

                        Next

                    </a>

                </li>

            <?php endif; ?>

        </ul>

    </nav>

    <?php endif; ?>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>