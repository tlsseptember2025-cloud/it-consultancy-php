<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;

}

require CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
    SELECT
        rr.id AS refund_id,
        rr.*,
        c.name AS customer_name,
        s.title AS service_title
    FROM refund_requests rr
    JOIN requests r
        ON rr.request_id = r.id
    JOIN customers c
        ON r.customer_id = c.id
    JOIN services s
        ON r.service_id = s.id
    WHERE rr.status = 'Pending'
    ORDER BY rr.created_at DESC
");

$refundRequests = $stmt->fetchAll();

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<h1 class="mb-4">
    Refund Requests
</h1>

<div class="card shadow-sm">

    <div class="card-body">

        <table class="table table-bordered table-hover">

            <thead>

                <tr>

                    <th>Customer</th>

                    <th>Service</th>

                    <th>Reason</th>

                    <th>Status</th>

                    <th>Requested On</th>

                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($refundRequests as $request): ?> 

                    <tr>

                        <td>
                            <?= htmlspecialchars($request['customer_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['service_title']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['reason_type']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['status']) ?>
                        </td>

                        <td>
                            <?= formatDate($request['created_at']) ?>
                        </td>

                        <td>

                           <?php if ($request['status'] === 'Pending'): ?>

                                <a
                                    href="?page=review-refund&id=<?= $request['refund_id'] ?>"
                                    class="btn btn-primary btn-sm">

                                    Review

                                </a>

                            <?php else: ?>

                                <?php if ($request['status'] === 'Approved'): ?>

                                    <span class="badge bg-success">
                                        Approved
                                    </span>

                                <?php elseif ($request['status'] === 'Rejected'): ?>

                                    <span class="badge bg-danger">
                                        Rejected
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">
                                        Pending
                                    </span>

                                <?php endif; ?>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>