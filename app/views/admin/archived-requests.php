<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$params = [];

$where = "
    WHERE requests.workflow_stage = 'Archived'
";

$where .= buildSearchCondition(
    [
        'requests.id',
        'requests.description',
        'customers.name',
        'services.title',
        'agents.name',
        'requests.status',
        'requests.job_status'
    ],
    $search,
    $params
);

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        customers.name AS customer_name,
        services.title AS service_title,
        agents.name AS agent_name
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    LEFT JOIN agents
        ON agents.id = requests.agent_id
    {$where}
    ORDER BY requests.archived_at DESC
    LIMIT {$limit} OFFSET " . getPageOffset($page, $limit)
);

$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    LEFT JOIN agents
        ON agents.id = requests.agent_id
    {$where}
");

$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages($totalRecords, $limit);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container-fluid mt-4">

    <!-- Page Heading -->
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2>Archived Requests</h2>

            <p class="text-muted mb-0">
                Requests retained in the archive.
            </p>
        </div>

        <span class="badge bg-secondary">
            <?= $totalRecords ?> Archived
        </span>

    </div>

    <!-- Search -->
    <form method="GET" class="mb-3" id="searchForm">

        <input
            type="hidden"
            name="page"
            value="archived-requests"
        >

        <div class="input-group">

            <input
                type="text"
                name="search"
                id="searchInput"
                class="form-control"
                placeholder="Search archived requests..."
                value="<?= htmlspecialchars($search) ?>"
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
                    href="?page=archived-requests"
                    class="btn btn-secondary"
                >
                    Clear
                </a>

            <?php endif; ?>

        </div>

    </form>

    <?php if (empty($requests)): ?>

        <div class="alert alert-info">
            No archived requests found.
        </div>

    <?php else: ?>

        <div class="card shadow-sm">

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Description</th>
                                <th>Agent</th>
                                <th>Completed</th>
                                <th>Archived</th>
                                <th>Status</th>
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
                                        $request['description'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= $request['agent_name']
                                        ? htmlspecialchars(
                                            $request['agent_name']
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['completed_at'])
                                        ? date(
                                            'd M Y H:i',
                                            strtotime(
                                                $request['completed_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['archived_at'])
                                        ? date(
                                            'd M Y H:i',
                                            strtotime(
                                                $request['archived_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <span class="badge bg-secondary">
                                        Archived
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="?page=view-archived-request&id=<?= (int) $request['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($totalPages > 1): ?>

                    <nav
                        aria-label="Archived requests pagination"
                        class="mt-3 mb-3"
                    >

                        <ul class="pagination justify-content-center mb-0">

                            <?php if ($page > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            buildPaginationUrl(
                                                'archived-requests',
                                                $page - 1,
                                                ['search' => $search]
                                            )
                                        ) ?>"
                                    >
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
                                                'archived-requests',
                                                $i,
                                                ['search' => $search]
                                            )
                                        ) ?>"
                                    >
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
                                                'archived-requests',
                                                $page + 1,
                                                ['search' => $search]
                                            )
                                        ) ?>"
                                    >
                                        Next
                                    </a>

                                </li>

                            <?php endif; ?>

                        </ul>

                    </nav>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</div>

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