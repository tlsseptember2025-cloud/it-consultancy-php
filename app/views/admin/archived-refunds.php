<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/auth.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';

$perPage = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];

if ($status == 'completed') {

    $where .= "
        AND rr.status='Approved'
        AND rr.refund_status='Completed'
    ";

} elseif ($status == 'rejected') {

    $where .= "
        AND rr.status='Rejected'
    ";

} else {

    $where .= "
        AND
        (
            rr.status='Rejected'

            OR

            (
                rr.status='Approved'
                AND rr.refund_status='Completed'
            )
        )
    ";

}

if ($search !== '') {

    $where .= "
        AND
        (
            c.name LIKE :search
            OR
            s.title LIKE :search
        )
    ";

    $params[':search'] = "%{$search}%";

}

/*
|--------------------------------------------------------------------------
| Count Records
|--------------------------------------------------------------------------
*/

$countSql = "

SELECT COUNT(*)

FROM refund_requests rr

JOIN requests r
ON r.id = rr.request_id

JOIN customers c
ON c.id = r.customer_id

JOIN services s
ON s.id = r.service_id

{$where}

";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);

$totalRefunds = $stmt->fetchColumn();

$totalPages = ceil($totalRefunds / $perPage);

/*
|--------------------------------------------------------------------------
| Get Records
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    rr.*,

    c.name AS customer_name,

    s.title AS service_title

FROM refund_requests rr

JOIN requests r
ON r.id = rr.request_id

JOIN customers c
ON c.id = r.customer_id

JOIN services s
ON s.id = r.service_id

{$where}

ORDER BY rr.reviewed_at DESC

LIMIT {$perPage} OFFSET {$offset}

";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRefunds = count($refunds);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h2 class="mb-4">Archived Refunds</h2>

<form method="GET" class="row mb-3">

    <input type="hidden" name="page" value="archived-refunds">

    <div class="col-md-5">

        <input
            type="text"
            name="search"
            class="form-control"
            placeholder="Search customer or service..."
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

    </div>

    <div class="col-md-3">

        <select
            name="status"
            class="form-select">

            <option value="all"
                <?= ($_GET['status'] ?? 'all') == 'all' ? 'selected' : '' ?>>
                Show All
            </option>

            <option value="completed"
                <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>
                Completed
            </option>

            <option value="rejected"
                <?= ($_GET['status'] ?? '') == 'rejected' ? 'selected' : '' ?>>
                Rejected
            </option>

        </select>

    </div>

    <div class="col-md-2">

        <button
            type="submit"
            class="btn btn-primary w-100">

            Search

        </button>

    </div>

    <div class="col-md-2">

        <a
            href="?page=archived-refunds"
            class="btn btn-secondary w-100">

            Reset

        </a>

    </div>

</form>

<div class="alert alert-light border d-flex justify-content-between align-items-center">

    <?php

switch ($status) {

    case 'completed':
        $summary = 'completed refund';
        break;

    case 'rejected':
        $summary = 'rejected refund';
        break;

    default:
        $summary = 'archived refund';

}

?>

<div class="alert alert-light border d-flex justify-content-between align-items-center">

    <span>

        Showing

        <strong><?= $totalRefunds ?></strong>

        <?= $summary ?><?= $totalRefunds == 1 ? '' : 's' ?>.

    </span>

</div>

</div>

<table class="table table-bordered">

    <thead>

        <tr>

            <th>Customer</th>
            <th>Service</th>
            <th>Refund Amount</th>
            <th>Status</th>
            <th>Closed On</th>
            <th width="120">Action</th>

        </tr>

    </thead>

    <tbody>

<?php if (count($refunds) > 0): ?>

    <?php foreach ($refunds as $refund): ?>

    <tr>

        <td><?= htmlspecialchars($refund['customer_name']) ?></td>

        <td><?= htmlspecialchars($refund['service_title']) ?></td>

        <td>

            <?php if ($refund['status'] == 'Rejected'): ?>

                -

            <?php else: ?>

                AED <?= number_format($refund['refund_amount'], 2) ?>

            <?php endif; ?>

        </td>

        <td>

            <?php if ($refund['status'] == 'Rejected'): ?>

                <span class="badge rounded-pill bg-danger">
                    Rejected
                </span>

            <?php else: ?>

                <span class="badge rounded-pill bg-success">
                    Completed
                </span>

            <?php endif; ?>

        </td>

        <td>

            <?= date(
                'l, d M Y - h:i A',
                strtotime($refund['reviewed_at'])
            ) ?>

        </td>

        <td>

            <a
                href="?page=view-refund&id=<?= $refund['id'] ?>"
                class="btn btn-sm btn-primary">

                View

            </a>

        </td>

    </tr>

    <?php endforeach; ?>

<?php else: ?>

<tr>

    <td colspan="6" class="text-center py-5">

        <div class="text-muted">

            <i class="bi bi-search fs-1 d-block mb-3"></i>

            <h5>No archived refunds found</h5>

            <p class="mb-0">

                No archived refunds match your search criteria.

            </p>

        </div>

    </td>

</tr>

<?php endif; ?>

</tbody>

</table>

<?php if ($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php if ($page > 1): ?>

<li class="page-item">

<a class="page-link"
href="?page=archived-refunds&p=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

Previous

</a>

</li>

<?php endif; ?>

<?php for ($i=1; $i<=$totalPages; $i++): ?>

<li class="page-item <?= $page==$i ? 'active' : '' ?>">

<a class="page-link"
href="?page=archived-refunds&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

<?php if ($page < $totalPages): ?>

<li class="page-item">

<a class="page-link"
href="?page=archived-refunds&p=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

Next

</a>

</li>

<?php endif; ?>

</ul>

</nav>

<?php endif; ?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>