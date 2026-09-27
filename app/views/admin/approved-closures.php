<?php
require VIEW_PATH . '/layouts/header-admin.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;
$offset = getPageOffset($page, $limit);
?>

<div class="container mt-4">

    <h2>Approved Closures</h2>

    <div class="card mt-4">

    <div class="card-header bg-primary text-white">
        <strong>Ready to Close</strong>
    </div>

    <div class="card-body">

        <form method="GET" class="row g-3 align-items-end mb-4" id="searchForm">

            <input
                type="hidden"
                name="page"
                value="approved-closures"
            >

            <div class="col-md-10">
                <label for="searchInput" class="form-label">Search</label>

                <input
                    type="text"
                    name="search"
                    id="searchInput"
                    class="form-control"
                    placeholder="Search request, customer, or service..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="col-md-2 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary flex-fill"
                >
                    Search
                </button>

                <?php if ($search !== ''): ?>

                    <a
                        href="?page=approved-closures"
                        class="btn btn-secondary flex-fill"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </div>

        </form>
<?php if (empty($requests)): ?>

    <div class="alert alert-info">

        No approved closures available.

    </div>

<?php else: ?>

<table class="table table-bordered table-hover">

    <thead>

        <tr>

            <th>Request</th>

            <th>Customer</th>

            <th>Service</th>

            <th>Action</th>

        </tr>

    </thead>

    <tbody>

<?php foreach ($requests as $request): ?>

<tr>

    <td>#<?= $request['id'] ?></td>

    <td><?= htmlspecialchars($request['customer_name']) ?></td>

    <td><?= htmlspecialchars($request['service_name']) ?></td>

        <td>

            <a
                href="index.php?page=complete-consultation-closure&request_id=<?= $request['id'] ?>"
                class="btn btn-success btn-sm">

                Complete Closure

            </a>

        </td>

</tr>

<?php endforeach; ?>

    </tbody>

</table>

<?php if ($totalPages > 1): ?>

    <nav class="mt-4" aria-label="Approved closures pagination">

        <ul class="pagination justify-content-center">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a
                    class="page-link"
                    href="<?= buildPaginationUrl('approved-closures', max(1, $page - 1), ['search' => $search]) ?>"
                >
                    Previous
                </a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= $i === $page ? 'active' : '' ?>">

                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl('approved-closures', $i, ['search' => $search]) ?>"
                    >
                        <?= $i ?>
                    </a>

                </li>

            <?php endfor; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a
                    class="page-link"
                    href="<?= buildPaginationUrl('approved-closures', min($totalPages, $page + 1), ['search' => $search]) ?>"
                >
                    Next
                </a>
            </li>

        </ul>

    </nav>

<?php endif; ?>

<?php endif; ?>

    </div>

</div>

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

<?php
require VIEW_PATH . '/layouts/footer.php';