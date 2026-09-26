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
| Load Notifications
|--------------------------------------------------------------------------
*/

$limit = 10;

$page = getPageNumber();

$params = [];

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE recipient_type = 'admin'
");

$countStmt->execute();

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

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE recipient_type = 'admin'
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mb-4 pt-3">
    Notification History
</h1>


<div class="mb-3">

    <a
        href="?page=mark-all-notifications-read"
        class="btn btn-success"
        onclick="return confirm('Mark all notifications as read?');">

        ✓ Mark All as Read

    </a>

</div>


<div class="card">

    <div class="card-body">

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

                <?php foreach ($notifications as $notification): ?>

                    <tr class="<?= !$notification['is_read'] ? 'table-warning' : '' ?>">

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

</div>

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
                                $page - 1
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
                                'notifications',
                                $i
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
                                $page + 1
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

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>