<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/SearchPaginationHelper.php';


/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
|
| Main Admin:
|     $_SESSION['user']
|
| Demo Admin:
|     $_SESSION['demo_user']
|
| Demo Super Admin:
|     $_SESSION['demo_super_admin']
|
*/

$isDemoAdmin =
    isset($_SESSION['demo_user']) ||
    isset($_SESSION['demo_super_admin']);

$isMainAdmin = isset($_SESSION['user']);


if (!$isMainAdmin && !$isDemoAdmin) {
    header("Location: ?page=login");
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $adminPdo = $demoPdo;

} else {

    require_once CONFIG_PATH . '/database.php';

    $adminPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Admin Header
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/layouts/header-admin.php';


/*
|--------------------------------------------------------------------------
| Search & Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();

$limit = 10;

$page = getPageNumber();

$params = [];

$where = "
    WHERE recipient_type = 'admin'
";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue = '%' . $search . '%';

    $where .= "
        AND (
            title LIKE ?
            OR message LIKE ?
            OR CASE
                WHEN is_read = 1 THEN 'Read'
                WHEN is_read = 0 THEN 'New'
                ELSE ''
            END LIKE ?
            OR DATE_FORMAT(created_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(created_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(created_at, '%Y-%m-%d') LIKE ?
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Count Notifications
|--------------------------------------------------------------------------
*/

$countStmt = $adminPdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    $where
");

$countStmt->execute($params);

$totalNotifications = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalNotifications,
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
| Load Notifications
|--------------------------------------------------------------------------
*/

$stmt = $adminPdo->prepare("
    SELECT *
    FROM notifications
    $where
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

$adminNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<h1 class="mb-4 pt-3">
    My Notifications
</h1>


<!-- Search -->

<form
    method="GET"
    action=""
    class="mb-3"
    id="searchForm">

    <input
        type="hidden"
        name="page"
        value="notifications">

    <div class="input-group">

        <input
            type="text"
            name="search"
            id="searchInput"
            class="form-control"
            placeholder="Search by status, title, message or date..."
            value="<?= htmlspecialchars(
                $search,
                ENT_QUOTES,
                'UTF-8'
            ) ?>">

        <button
            type="submit"
            class="btn btn-primary">

            Search

        </button>


        <?php if ($search !== ''): ?>

            <a
                href="?page=notifications"
                class="btn btn-outline-secondary">

                Clear

            </a>

        <?php endif; ?>

    </div>

</form>


<div class="card">

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <h5 class="mb-0">
                Notifications
            </h5>

            <?php if (!empty($adminNotifications)): ?>

                <a
                    href="?page=mark-all-notifications-read"
                    class="btn btn-primary btn-sm"
                    onclick="return confirm('Mark all notifications as read?');">

                    Mark All as Read

                </a>

            <?php endif; ?>

        </div>


        <?php if (empty($adminNotifications)): ?>

            <div class="card shadow-sm">

                <div class="card-body text-center py-5">

                    <div class="fs-1 mb-3">
                        🔔
                    </div>

                    <h5 class="mb-2">
                        No notifications
                    </h5>

                    <p class="text-muted mb-0">
                        You currently have no notifications.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>

                        <tr>

                            <th>Status</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($adminNotifications as $notification): ?>

                            <tr class="<?= !$notification['is_read']
                                ? 'table-warning'
                                : '' ?>">

                                <td>

                                    <?php if ($notification['is_read']): ?>

                                        <span class="badge bg-success">

                                            ✓ Read

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning text-dark">

                                            🔔 New

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $notification['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $notification['message'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= formatDateTime(
                                        $notification['created_at']
                                    ) ?>

                                </td>


                                <td>

                                    <a
                                        href="?page=open-notification&id=<?= (int) $notification['id'] ?>"
                                        class="btn btn-sm btn-primary">

                                        Open

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- Pagination -->

<?php if ($totalPages > 1): ?>

    <nav aria-label="Notification pagination">

        <ul class="pagination justify-content-center mt-4">


            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'notifications',
                                $page - 1,
                                [
                                    'search' => $search
                                ]
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">

                        Previous

                    </a>

                </li>

            <?php endif; ?>


            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= $i === $page
                    ? 'active'
                    : '' ?>">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'notifications',
                                $i,
                                [
                                    'search' => $search
                                ]
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
                                'notifications',
                                $page + 1,
                                [
                                    'search' => $search
                                ]
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