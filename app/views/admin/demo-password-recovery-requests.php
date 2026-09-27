<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/SearchPaginationHelper.php';
require_once CONFIG_PATH . '/demo-database.php';


/*
|--------------------------------------------------------------------------
| Search & Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();
$page   = getPageNumber();
$limit  = 10;
$offset = getPageOffset($page, $limit);

$searchValue = '%' . $search . '%';

$where = '';
$params = [];

if ($search !== '') {

    $where = "
        AND (
            CAST(id AS CHAR) LIKE ?
            OR account_type LIKE ?
            OR username LIKE ?
            OR email LIKE ?
            OR reason LIKE ?
            OR status LIKE ?
            OR DATE_FORMAT(requested_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(requested_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(requested_at, '%Y-%m-%d') LIKE ?
        )
    ";

    $params = [
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    ];
}


/*
|--------------------------------------------------------------------------
| Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM demo_password_recovery_requests
    WHERE 1 = 1
    {$where}
";

$countStmt = $demoPdo->prepare($countSql);
$countStmt->execute($params);

$totalRecords = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages($totalRecords, $limit);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = getPageOffset($page, $limit);
}


/*
|--------------------------------------------------------------------------
| Requests
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        demo_tenant_id,
        account_type,
        account_id,
        username,
        email,
        reason,
        status,
        requested_at
    FROM demo_password_recovery_requests
    WHERE 1 = 1
    {$where}
    ORDER BY
        CASE
            WHEN status = 'Pending' THEN 1
            ELSE 2
        END,
        requested_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $demoPdo->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container-fluid py-4">

    <div class="mb-4">
        <h2 class="mb-1">
            Demo Password Recovery Requests
        </h2>

        <p class="text-muted mb-0">
            Review password recovery requests submitted by Demo users.
        </p>
    </div>


    <!-- Search -->

    <form
        method="GET"
        action=""
        class="mb-3"
        id="searchForm">

        <input
            type="hidden"
            name="page"
            value="demo-password-recovery-requests">

        <div class="input-group">

            <input
                type="text"
                name="search"
                id="searchInput"
                class="form-control"
                placeholder="Search by ID, account type, username, email, reason, status or date..."
                value="<?= htmlspecialchars($search) ?>">

            <button
                type="submit"
                class="btn btn-primary">
                Search
            </button>

            <?php if ($search !== ''): ?>

                <a
                    href="?page=demo-password-recovery-requests"
                    class="btn btn-outline-secondary">
                    Clear
                </a>

            <?php endif; ?>

        </div>

    </form>


    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center">

            <strong>Recovery Requests</strong>

            <span class="text-muted small">
                <?= $totalRecords ?> request<?= $totalRecords === 1 ? '' : 's' ?>
            </span>

        </div>


        <div class="card-body p-0">

            <?php if (empty($requests)): ?>

                <div class="p-4 text-muted">
                    No Demo password recovery requests found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-bordered table-striped mb-0">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account Type</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($requests as $request): ?>

                                <tr>

                                    <td>
                                        #<?= (int) $request['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(ucfirst($request['account_type'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['username']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['email']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['reason'] ?? '') ?: '-' ?>
                                    </td>

                                    <td>

                                        <?php if ($request['status'] === 'Pending'): ?>

                                            <span class="badge bg-warning text-dark">
                                                Pending
                                            </span>

                                        <?php elseif ($request['status'] === 'Approved'): ?>

                                            <span class="badge bg-success">
                                                Approved
                                            </span>

                                        <?php elseif ($request['status'] === 'Rejected'): ?>

                                            <span class="badge bg-danger">
                                                Rejected
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($request['status']) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td style="white-space: nowrap;">
                                        <?= htmlspecialchars($request['requested_at']) ?>
                                    </td>

                                    <td style="white-space: nowrap;">

                                        <a
                                            href="?page=demo-password-recovery-request&id=<?= (int) $request['id'] ?>"
                                            class="btn btn-outline-primary btn-sm">
                                            View
                                        </a>


                                        <?php if ($request['status'] === 'Pending'): ?>

                                            <form
                                                method="POST"
                                                action="?page=admin-demo-password-recovery"
                                                class="d-inline"
                                                onsubmit="return confirm('Approve this Demo password recovery request?');">

                                                <input
                                                    type="hidden"
                                                    name="request_id"
                                                    value="<?= (int) $request['id'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve">

                                                <button
                                                    type="submit"
                                                    class="btn btn-success btn-sm">
                                                    Approve
                                                </button>

                                            </form>


                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                onclick="rejectRecoveryRequest(<?= (int) $request['id'] ?>)">
                                                Reject
                                            </button>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- Pagination -->

                <?php if ($totalPages > 1): ?>

                    <div class="d-flex justify-content-center py-3">

                        <nav aria-label="Recovery request pagination">

                            <ul class="pagination mb-0">

                                <?php if ($page > 1): ?>

                                    <li class="page-item">

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                buildPaginationUrl(
                                                    'demo-password-recovery-requests',
                                                    $page - 1,
                                                    ['search' => $search]
                                                )
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
                                                    'demo-password-recovery-requests',
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
                                                    'demo-password-recovery-requests',
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

            <?php endif; ?>

        </div>

    </div>

</div>


<script>
function rejectRecoveryRequest(requestId) {

    const reason = prompt(
        'Enter the reason for rejecting this recovery request:'
    );

    if (reason === null) {
        return;
    }

    if (reason.trim() === '') {
        alert('A rejection reason is required.');
        return;
    }

    const form = document.createElement('form');

    form.method = 'POST';
    form.action = '?page=admin-demo-password-recovery';

    form.innerHTML = `
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="rejection_reason" value="${reason.replace(/"/g, '&quot;')}">
    `;

    document.body.appendChild(form);
    form.submit();
}


/*
|--------------------------------------------------------------------------
| Live Search
|--------------------------------------------------------------------------
*/

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


<?php require VIEW_PATH . '/layouts/footer.php'; ?>