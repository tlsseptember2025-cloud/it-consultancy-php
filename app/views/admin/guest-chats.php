<?php

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';
require_once CONFIG_PATH . '/database.php';


requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}


/*
|--------------------------------------------------------------------------
| Search & Pagination
|--------------------------------------------------------------------------
*/

$search = getSearchTerm();

$page = getPageNumber();

$limit = getPageLimit(10);

$params = [];


/*
|--------------------------------------------------------------------------
| Base Query
|--------------------------------------------------------------------------
*/

$where = "
    WHERE status = 'Closed'
";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where .= "
        AND (
            CAST(id AS CHAR) LIKE ?
            OR guest_name LIKE ?
            OR guest_email LIKE ?
            OR subject LIKE ?
            OR DATE_FORMAT(started_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(started_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(started_at, '%Y-%m-%d') LIKE ?
            OR DATE_FORMAT(ended_at, '%d-%m-%Y') LIKE ?
            OR DATE_FORMAT(ended_at, '%d-%m') LIKE ?
            OR DATE_FORMAT(ended_at, '%Y-%m-%d') LIKE ?
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
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Count Records
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM guest_chat_conversations
    {$where}
");

$countStmt->execute($params);

$totalRecords = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages(
    $totalRecords,
    $limit
);

if ($page > $totalPages) {

    $page = $totalPages;

}


/*
|--------------------------------------------------------------------------
| Offset
|--------------------------------------------------------------------------
*/

$offset = getPageOffset(
    $page,
    $limit
);


/*
|--------------------------------------------------------------------------
| Load Conversations
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        guest_name,
        guest_email,
        subject,
        status,
        started_at,
        ended_at

    FROM guest_chat_conversations

    {$where}

    ORDER BY ended_at DESC, id DESC

    LIMIT {$limit}
    OFFSET {$offset}
");

$stmt->execute($params);

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Admin Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="container py-4">


    <div class="mb-4">

        <h2 class="mb-1">
            Guest Chats
        </h2>

        <p class="text-muted mb-0">
            Closed guest chat conversations and their complete history.
        </p>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    -->

    <form
        method="GET"
        id="searchForm"
        class="mb-3"
    >

        <input
            type="hidden"
            name="page"
            value="guest-chats"
        >

        <input
            type="hidden"
            name="p"
            value="1"
        >


        <div class="input-group">

            <input
                type="text"
                name="search"
                id="searchInput"
                class="form-control"
                placeholder="Search customer, email, subject, ID or date..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                autocomplete="off"
            >


            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>


            <?php if ($search !== ''): ?>

                <a
                    href="?page=guest-chats"
                    class="btn btn-secondary"
                >
                    Clear
                </a>

            <?php endif; ?>

        </div>

    </form>


    <!--
    |--------------------------------------------------------------------------
    | Results Count
    |--------------------------------------------------------------------------
    -->

    <div class="alert alert-light border mb-3">

        Showing

        <strong>
            <?= $totalRecords ?>
        </strong>

        closed guest chat
        <?= $totalRecords == 1 ? 'conversation' : 'conversations' ?>.

    </div>


    <div class="card shadow-sm">

        <div class="card-body">


            <?php if (empty($conversations)): ?>

                <div class="alert alert-secondary mb-0">

                    No closed guest chats match your search.

                </div>


            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">


                        <thead>

                            <tr>

                                <th>
                                    Customer Name
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Start Date/Time
                                </th>

                                <th>
                                    End Date/Time
                                </th>

                                <th>
                                    View
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($conversations as $conversation): ?>


                                <tr>


                                    <td>

                                        <?= htmlspecialchars(
                                            $conversation['guest_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $conversation['subject'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= formatDateTime(
                                            $conversation['started_at']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= !empty(
                                            $conversation['ended_at']
                                        )

                                            ? formatDateTime(
                                                $conversation['ended_at']
                                            )

                                            : '—'
                                        ?>

                                    </td>


                                    <td>

                                        <a
                                            href="?page=guest-chat-conversation-admin&id=<?= (int) $conversation['id'] ?>"
                                            class="btn btn-sm btn-primary"
                                        >
                                            View
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


    <!--
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    -->

    <?php if ($totalPages > 1): ?>

        <nav class="mt-4">

            <ul class="pagination justify-content-center">


                <?php if ($page > 1): ?>

                    <li class="page-item">

                        <a
                            class="page-link"
                            href="?page=guest-chats&p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"
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
                            href="?page=guest-chats&p=<?= $i ?>&search=<?= urlencode($search) ?>"
                        >
                            <?= $i ?>
                        </a>

                    </li>

                <?php endfor; ?>


                <?php if ($page < $totalPages): ?>

                    <li class="page-item">

                        <a
                            class="page-link"
                            href="?page=guest-chats&p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"
                        >
                            Next
                        </a>

                    </li>

                <?php endif; ?>


            </ul>

        </nav>

    <?php endif; ?>


</div>


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