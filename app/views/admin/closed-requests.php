<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$params = [];

$where = "
    WHERE requests.workflow_stage = 'Closed'
";

$where .= buildSearchCondition(
    [
        'requests.id',
        'requests.description',
        'customers.name',
        'services.title',
        'agents.name',
        'requests.quoted_price',
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
    JOIN customers ON customers.id = requests.customer_id
    JOIN services ON services.id = requests.service_id
    LEFT JOIN agents ON agents.id = requests.agent_id
    {$where}
    ORDER BY requests.completed_at DESC
    LIMIT {$limit} OFFSET " . getPageOffset($page, $limit)
);

$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    JOIN customers ON customers.id = requests.customer_id
    JOIN services ON services.id = requests.service_id
    LEFT JOIN agents ON agents.id = requests.agent_id
    {$where}
");

$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages($totalRecords, $limit);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2 class="mb-4">Closed Requests</h2>

<form method="GET" class="mb-3" id="searchForm">
    <input type="hidden" name="page" value="closed-requests">

    <div class="input-group">
        <input
            type="text"
            name="search"
            id="searchInput"
            class="form-control"
            placeholder="Search closed requests..."
            value="<?= htmlspecialchars($search) ?>"
            autocomplete="off"
        >
        <button type="submit" class="btn btn-primary">Search</button>

        <?php if ($search !== ''): ?>
            <a href="?page=closed-requests" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<table class="table table-bordered">

    <thead>

        <tr>

            <th>Request #</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Description</th>
            <th>Assigned Agent</th>
            <th>Quoted Price</th>
            <th>Closed On</th>
            <th>Action</th>

        </tr>

    </thead>

    <?php foreach ($requests as $request): ?>

    <tr>

        <td><?= $request['id'] ?></td>

        <td><?= htmlspecialchars($request['customer_name']) ?></td>

        <td><?= htmlspecialchars($request['service_title']) ?></td>

        <td><?= htmlspecialchars($request['description'] ?? '—') ?></td>

        <td>
            <?= !empty($request['agent_name'])
                ? htmlspecialchars($request['agent_name'])
                : '-' ?>
        </td>

        <td>AED <?= number_format($request['quoted_price'], 2) ?></td>

        <td>
            <?= !empty($request['completed_at'])
                ? date('M d, Y', strtotime($request['completed_at']))
                : '-' ?>
        </td>

        <td>

            <a
                href="index.php?page=review-closed-request&request_id=<?= $request['id'] ?>"
                class="btn btn-info btn-sm">

                View

            </a>

        </td>

    </tr>

<?php endforeach; ?>

</tbody>

</table>

<?php if ($totalPages > 1): ?>
    <nav aria-label="Closed requests pagination">
        <ul class="pagination justify-content-center">

            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'closed-requests',
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
                                'closed-requests',
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
                                'closed-requests',
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