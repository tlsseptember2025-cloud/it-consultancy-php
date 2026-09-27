<?php 

    require VIEW_PATH . '/layouts/header-admin.php'; 
    require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

    $search = getSearchTerm();
    $page = getPageNumber();
    $limit = 10;
    $offset = getPageOffset($page, $limit);

?>

<div class="container mt-4">

    <h2>Pending Closure Agreements</h2>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'review-saved'): ?>

    <div class="alert alert-success" id="successMessage">

        <strong>Success!</strong>

        The closure agreement review has been completed successfully.

    </div>

<?php endif; ?>

    
        <div class="card">
            <div class="card-header bg-primary text-white">
                <strong>Submitted Closure Agreements</strong>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" class="row g-3 align-items-end" id="searchForm">

                    <input type="hidden" name="page" value="closure-agreements">

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
    <button type="submit" class="btn btn-primary flex-fill">
        Search
    </button>

    <?php if ($search !== ''): ?>
        <a
            href="?page=closure-agreements"
            class="btn btn-secondary flex-fill"
        >
            Clear
        </a>
    <?php endif; ?>
</div>

                </form>
            </div>




    <div class="card-body">

        <?php if (empty($agreements)): ?>

            <div class="alert alert-info mb-0">

                No closure agreements have been submitted.

            </div>

        <?php else: ?>

            <table class="table table-striped table-hover">

                <thead>

                    <tr>

                        <th>Request</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Signed Name</th>
                        <th>Signed</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($agreements as $agreement): ?>

                    <tr>

                        <td>

                            #<?= $agreement['request_id'] ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($agreement['customer_name']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($agreement['service_name']) ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($agreement['typed_name']) ?>
                        </td>

                        <td>

                            <?= date('d M Y H:i', strtotime($agreement['signed_at'])) ?>

                        </td>

                        <td>

                            <span class="badge bg-warning text-dark">

                                Pending Review

                            </span>

                        </td>

                        <td>

                            <a
                                href="?page=review-closure-agreement&agreement_id=<?= $agreement['id'] ?>"
                                class="btn btn-primary btn-sm">

                                Review

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

               <?php endif; ?>

        <?php if ($totalPages > 1): ?>

            <nav class="mt-4" aria-label="Closure agreements pagination">
                <ul class="pagination justify-content-center">

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= buildPaginationUrl('closure-agreements', max(1, $page - 1), ['search' => $search]) ?>"
                        >
                            Previous
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= buildPaginationUrl('closure-agreements', $i, ['search' => $search]) ?>"
                            >
                                <?= $i ?>
                            </a>
                        </li>

                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= buildPaginationUrl('closure-agreements', min($totalPages, $page + 1), ['search' => $search]) ?>"
                        >
                            Next
                        </a>
                    </li>

                </ul>
            </nav>

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

<?php require VIEW_PATH . '/layouts/footer.php'; ?>