<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/retention_review_helper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$offset = getPageOffset($page, $limit);

$requests = getRetentionReviewRequests(
    $pdo,
    $search,
    $limit,
    $offset
);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    LEFT JOIN agents a
        ON a.id = r.agent_id
    WHERE r.workflow_stage = ?
      AND r.retention_review_at IS NOT NULL
      AND r.retention_review_at <= NOW()
      AND r.legal_hold = 0
      AND (
          r.retention_expires_at IS NULL
          OR r.retention_expires_at > NOW()
      )
      AND (
          ? = ''
          OR r.id LIKE ?
          OR r.description LIKE ?
          OR c.name LIKE ?
          OR c.email LIKE ?
          OR s.title LIKE ?
          OR a.name LIKE ?
      )
");

$searchValue = '%' . $search . '%';

$countStmt->execute([
    WORKFLOW_STAGE_ARCHIVED,
    $search,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue
]);

$totalRecords = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages($totalRecords, $limit);

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">
                Retention Review
            </h2>

            <p class="text-muted mb-0">
                Archived requests requiring administrator retention review.
            </p>
        </div>



        <span class="badge bg-warning text-dark">
            <?= count($requests) ?> Due
        </span>

    </div>

    <form method="GET" class="mb-3" id="searchForm">

    <input
        type="hidden"
        name="page"
        value="retention-review"
    >

    <div class="input-group">

        <input
            type="text"
            name="search"
            id="searchInput"
            class="form-control"
            placeholder="Search retention reviews..."
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
                href="?page=retention-review"
                class="btn btn-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </div>

</form>


    <?php if (empty($requests)): ?>

        <div class="alert alert-info">
            No requests are currently due for retention review.
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
                                <th>Archived</th>
                                <th>Review Due</th>
                                <th>Retention Expires</th>
                                <th>Extension</th>
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
                                    <?= !empty($request['archived_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime($request['archived_at'])
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['retention_review_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime(
                                                $request['retention_review_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= !empty($request['retention_expires_at'])
                                        ? date(
                                            'd M Y',
                                            strtotime(
                                                $request['retention_expires_at']
                                            )
                                        )
                                        : '-' ?>
                                </td>

                                <td>
                                    <?= (int) $request['retention_extension_years'] ?>
                                    year(s)
                                </td>

                                <td>

                                    <a
                                        href="?page=review-retention&id=<?= (int) $request['id'] ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                    <?php if ($totalPages > 1): ?>

    <nav
        aria-label="Retention review pagination"
        class="mt-3 mb-3"
    >

        <ul class="pagination justify-content-center mb-0">

            <?php if ($page > 1): ?>

                <li class="page-item">

                    <a
                        class="page-link"
                        href="<?= htmlspecialchars(
                            buildPaginationUrl(
                                'retention-review',
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
                                'retention-review',
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
                                'retention-review',
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