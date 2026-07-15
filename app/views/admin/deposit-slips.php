<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__) . '/layouts/header-admin.php';


$stmt = $pdo->query("
    SELECT
        ps.*,
        c.name AS customer_name,
        s.title AS service_title
    FROM payment_slips ps
    JOIN customers c
        ON ps.customer_id = c.id
    JOIN requests r
        ON ps.request_id = r.id
    JOIN services s
        ON r.service_id = s.id
    ORDER BY ps.uploaded_at DESC
");

$slips = $stmt->fetchAll();

?>

<div class="d-flex justify-content-between align-items-center mb-4">


<h1>Deposit Slips</h1>


</div>

<div class="card shadow-sm">


<div class="card-body">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th>ID</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Slip</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>

            </tr>

        </thead>

        <tbody>

            <?php if (count($slips) > 0): ?>

                <?php foreach ($slips as $slip): ?>

                    <tr>

                        <td>
                            <?= $slip['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($slip['customer_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($slip['service_title']) ?>
                        </td>

                        <td>

                            <a
                                href="uploads/slips/<?= $slip['file_name'] ?>"
                                target="_blank"
                                class="btn btn-info btn-sm">

                                View Slip

                            </a>

                        </td>

                        <td>

                            <?php if ($slip['status'] === 'Pending'): ?>

                                <span class="badge bg-warning">
                                    Pending
                                </span>

                            <?php elseif ($slip['status'] === 'Approved'): ?>

                                <span class="badge bg-success">
                                    Approved
                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger">
                                    Rejected
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($slip['uploaded_at'])) ?>
                        </td>

                        <td>

                            <?php if ($slip['status'] === 'Pending'): ?>

                                <a
                                    href="?page=approve-slip&id=<?= $slip['id'] ?>"
                                    class="btn btn-success btn-sm">

                                    Approve

                                </a>

                                <a
                                    href="?page=reject-slip&id=<?= $slip['id'] ?>"
                                    class="btn btn-danger btn-sm">

                                    Reject

                                </a>

                            <?php else: ?>

                                <span class="text-muted">
                                    Processed
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="7" class="text-center">

                        No deposit slips uploaded.

                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>


</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>