<?php require VIEW_PATH . '/layouts/header-admin.php'; ?>

<div class="container py-4">

    <h2 class="mb-4">
        Awaiting Customer Response
    </h2>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>Customer</th>
                            <th>Service</th>
                            <th>Email #</th>
                            <th>Response Deadline</th>
                            <th>Status</th>
                            <th width="140">Action</th>

                        </tr>

                    </thead>

                    <tbody>

<?php if (empty($requests)): ?>

<tr>

    <td colspan="6" class="text-center text-muted py-4">

        No requests awaiting customer response.

    </td>

</tr>

<?php else: ?>

<?php foreach ($requests as $request): ?>

<tr>

    <td><?= htmlspecialchars($request['customer_name']) ?></td>

    <td><?= htmlspecialchars($request['service_name']) ?></td>

    <td>

        <span class="badge bg-primary">

            <?= $request['verification_email_count'] ?>

        </span>

    </td>

    <td>

        <?= htmlspecialchars($request['customer_response_deadline']) ?>

    </td>

    <td>

        <span class="badge bg-warning text-dark">

            <?= htmlspecialchars($request['job_status']) ?>

        </span>

    </td>

    <td>

        <a
            href="?page=view-awaiting-customer-response&id=<?= $request['id'] ?>"
            class="btn btn-primary btn-sm">

            View

        </a>

    </td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>