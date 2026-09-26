<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/WorkflowHelper.php';
require_once APP_PATH . '/helpers/SearchPaginationHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/database.php';

$search = getSearchTerm();
$page = getPageNumber();
$limit = 10;

$params = [];

$where = "
    WHERE COALESCE(requests.workflow_stage, '') NOT IN ('Closed', 'Archived')
";

$where .= buildSearchCondition(
    [
        'requests.id',
        'requests.description',
        'customers.name',
        'services.title',
        'requests.job_status',
        'requests.workflow_stage'
    ],
    $search,
    $params
);

/*
|--------------------------------------------------------------------------
| Count total records
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    $where
");

$countStmt->execute($params);

$totalRequests = (int) $countStmt->fetchColumn();

$totalPages = getTotalPages($totalRequests, $limit);

$page = min($page, $totalPages);

$offset = getPageOffset($page, $limit);


/*
|--------------------------------------------------------------------------
| Load current page
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        customers.name AS customer_name,
        services.title AS service_title,
        requests.workflow_stage,
        requests.agent_id,
        agents.name AS agent_name,
        ps.id AS slip_id

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    LEFT JOIN agents
        ON agents.id = requests.agent_id

    LEFT JOIN payment_slips ps
        ON ps.id = (
            SELECT MAX(id)
            FROM payment_slips
            WHERE request_id = requests.id
        )

    $where

    ORDER BY requests.created_at DESC

    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$requests = $stmt->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">

    <h5 class="mb-0">
        Current Requests
    </h5>

    <form method="get" class="d-flex gap-2" id="requestSearchForm">

    <input
        type="hidden"
        name="page"
        value="requests"
    >

    <input
        type="text"
        name="search"
        id="requestSearch"
        class="form-control"
        placeholder="Search requests..."
        value="<?= htmlspecialchars($search) ?>"
        autocomplete="off"
        style="width:280px;"
    >

    <?php if ($search !== ''): ?>

        <a
            href="?page=requests"
            class="btn btn-outline-secondary"
            id="clearRequestSearch"
        >
            Clear
        </a>

    <?php endif; ?>

</form>

</div>

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th class="text-center" style="width:100px;">Request #</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Description</th>
                <th>Quoted Price</th>
                <th>Status</th>
                <th>Workflow Stage</th>
                <th
                    style="
                        width:130px;
                        white-space:nowrap;
                    "
                    >
                    Request Date
                </th>
                
                <th style="width:220px; white-space:nowrap;">Action</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($requests as $request): ?>

                <tr>

                   <td class="text-center">
                        #<strong><?= (int)$request['id']; ?></strong>
                    </td>

                    <td>
                        <?= htmlspecialchars($request['customer_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request['service_title']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request['description'] ?? '') ?>
                    </td>

                    <td>

                       <?php if ($request['quoted_price'] > 0): ?>

                            <span class="badge bg-success">
                                AED <?= number_format($request['quoted_price'], 2) ?>
                            </span>

                        <?php else: ?>

                            <?php if ($request['quoted_price'] > 0): ?>

    AED <?= number_format($request['quoted_price'], 2) ?>

<?php else: ?>

    <span class="text-muted">
        Awaiting Quote
    </span>

<?php endif; ?>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if ($request['job_status'] === 'Pending'): ?>

                            <span class="badge bg-warning text-dark">
                                Pending
                            </span>

                        <?php elseif ($request['job_status'] === 'Completed'): ?>

                            <span class="badge bg-success">
                                Completed
                            </span>

                        <?php elseif ($request['job_status'] === 'Cancelled'): ?>

                            <span class="badge bg-danger">
                                Cancelled
                            </span>

                        <?php else: ?>

                            <span class="badge bg-primary">
                                <?= htmlspecialchars($request['job_status']) ?>
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= workflowBadge($request['workflow_stage']) ?>
                    </td>

                   <td
                        style="
                            width:130px;
                            white-space:nowrap;
                        "
                        >
                        <?= formatDate($request['created_at']) ?>
                    </td>

                   
<td style="width:220px; white-space:nowrap;">

    <a
        href="?page=view-request&id=<?= $request['id'] ?>"
        class="btn btn-info btn-sm">
        View Service
    </a>

    <?php if (
    $request['workflow_stage'] === 'Submitted'
    && empty($request['agent_id'])
): ?>

    <a
        href="?page=admin-assign-agent&id=<?= $request['id'] ?>"
        class="btn btn-success btn-sm">
        Assign Agent
    </a>

<?php endif; ?>


<?php if (
    $request['workflow_stage'] === 'Submitted'
    && !empty($request['agent_id'])
): ?>

    <span class="badge bg-info">
        Waiting for Customer
    </span>

    <br>

    <small class="text-muted">
        Agent:
        <?= htmlspecialchars($request['agent_name']) ?>
    </small>

<?php endif; ?>


    <?php if ($request['workflow_stage'] == 'Consultation Scheduled'): ?>

        <a
            href="?page=review-consultation&id=<?= $request['id'] ?>"
            class="btn btn-primary btn-sm">

            Confirm Schedule

        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Waiting Customer Response'): ?>

    <button
        type="button"
        class="btn btn-secondary btn-sm"
        disabled>
        Waiting for Customer Response
    </button>

<?php elseif ($request['workflow_stage'] === 'Consultation Confirmed'): ?>

    <span class="ms-2 text-muted fw-semibold">
        Awaiting Agent Outcome
    </span>

<?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Consultation Completed'): ?>

    <a
        href="?page=create-proposal&id=<?= (int)$request['id'] ?>"
        class="btn btn-dark btn-sm">
        Create Proposal
    </a>

<?php endif; ?>


<?php if ($request['workflow_stage'] === 'Proposal Draft'): ?>

    <?php if (empty($request['proposal'])): ?>

        <a
            href="?page=create-proposal&id=<?= $request['id'] ?>"
            class="btn btn-secondary btn-sm">
            Create Proposal
        </a>

    <?php else: ?>

        <a
            href="?page=create-proposal&id=<?= $request['id'] ?>"
            class="btn btn-warning btn-sm">
            Edit Proposal
        </a>

        <a
            href="?page=admin-view-proposal&id=<?= $request['id'] ?>"
            class="btn btn-info btn-sm">
            View Proposal
        </a>

        <a
            href="?page=send-proposal&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Send Proposal
        </a>

    <?php endif; ?>
<?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Proposal Rejected'): ?>

        <a
            href="?page=create-proposal&id=<?= $request['id'] ?>"
            class="btn btn-danger btn-sm">
            Revise Proposal
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Payment Submitted'): ?>

        <a
            href="?page=view-slip&id=<?= $request['slip_id'] ?>"
            class="btn btn-success btn-sm">
            Review Payment
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Needs Admin Review'): ?>

    <?php if (
        in_array(
            $request['review_type'] ?? '',
            ['service_missed', 'service_overdue'],
            true
        )
    ): ?>

        <a
            href="?page=admin-review-service-job&id=<?= (int)$request['id'] ?>"
            class="btn btn-success btn-sm">
            Review Service
        </a>

    <?php else: ?>

        <a
            href="?page=admin-review-consultation&id=<?= (int)$request['id'] ?>"
            class="btn btn-success btn-sm">
            Review Consultation
        </a>

    <?php endif; ?>

<?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Service Active'): ?>

        <a
            href="?page=complete-service-form&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Complete Service
        </a>

    <?php endif; ?>


   

</td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php if ($totalPages > 1): ?>

    <nav class="mt-3" aria-label="Requests pagination">

        <ul class="pagination justify-content-center">

            <?php if ($page > 1): ?>

                <li class="page-item">
                    <a
                        class="page-link"
                        href="<?= buildPaginationUrl(
                            'requests',
                            $page - 1,
                            ['search' => $search]
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
                        href="<?= buildPaginationUrl(
                            'requests',
                            $i,
                            ['search' => $search]
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
                        href="<?= buildPaginationUrl(
                            'requests',
                            $page + 1,
                            ['search' => $search]
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

    const searchInput = document.getElementById('requestSearch');

    if (!searchInput) {
        return;
    }

    let searchTimer;

    searchInput.addEventListener('input', function () {

        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {

            const search = searchInput.value.trim();

            const url = new URL(window.location.href);

            url.searchParams.set('page', 'requests');
            url.searchParams.set('p', '1');

            if (search !== '') {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }

            window.location.href = url.toString();

        }, 300);

    });

});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>