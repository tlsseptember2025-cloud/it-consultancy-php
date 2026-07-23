<?php 

/** @var array $agent */
/** @var array $requests */

require VIEW_PATH . '/layouts/header-agent.php'; 

?>

<div class="container py-4">

    <h2>Customer Contact Approved</h2>

    <div class="table-responsive">

    <table class="table table-bordered table-hover align-middle">

        <thead class="table-dark">
            <tr>
                <th>Customer</th>
                <th>Service</th>
                <th>Administrator Instructions</th>
                <th width="170">Action</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($requests as $request): ?>

            <tr>
                <td><?= htmlspecialchars($request['customer_name']) ?></td>
                <td><?= htmlspecialchars($request['service_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($request['admin_instruction'])) ?></td>
                <td>
                    <a href="?page=contact-customer&request_id=<?= $request['id'] ?>"
                    class="btn btn-primary btn-sm">
                        Contact Customer
                    </a>

                </td>
            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>