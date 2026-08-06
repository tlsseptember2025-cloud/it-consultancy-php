<?php
require VIEW_PATH . '/layouts/header-admin.php';
?>

<div class="container mt-4">

    <h2>Approved Closures</h2>

    <div class="card mt-4">

    <div class="card-header bg-primary text-white">

        <strong>Ready to Close</strong>

    </div>

    <div class="card-body">

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

        <button
            class="btn btn-success btn-sm"
            disabled>

            Complete Closure

        </button>

    </td>

</tr>

<?php endforeach; ?>

    </tbody>

</table>

<?php endif; ?>

    </div>

</div>

</div>

<?php
require VIEW_PATH . '/layouts/footer.php';