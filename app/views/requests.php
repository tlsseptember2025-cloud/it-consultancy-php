<?php

require_once __DIR__ . '/../helpers/auth.php';
requireAdminLogin();
require dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("
    SELECT
        requests.*,
        customers.name AS customer_name,
        services.title AS service_title,
        requests.workflow_stage,
        ps.id AS slip_id

    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    LEFT JOIN payment_slips ps
        ON ps.id = (
            SELECT MAX(id)
            FROM payment_slips
            WHERE request_id = requests.id
        )

     WHERE requests.workflow_stage <> 'Completed'
     ORDER BY requests.created_at DESC
");

$requests = $stmt->fetchAll();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>



<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th>Customer</th>
                <th>Service</th>
                <th>Quoted Price</th>
                <th>Status</th>
                <th>Workflow Stage</th>
                <th>Date</th>
                
                <th>Action</th>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($requests as $request): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($request['customer_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request['service_title']) ?>
                    </td>

                    <td>

                        <?php if ($request['quoted_price'] > 0): ?>

                            AED <?= number_format($request['quoted_price'], 2) ?>

                        <?php else: ?>

                            <span class="text-muted">
                                Awaiting Quote
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if ($request['status'] === 'Pending'): ?>

                            <span class="badge bg-warning">
                                Pending
                            </span>

                        <?php elseif ($request['status'] === 'Completed'): ?>

                            <span class="badge bg-success">
                                Completed
                            </span>

                        <?php elseif ($request['status'] === 'Cancelled'): ?>

                            <span class="badge bg-danger">
                                Cancelled
                            </span>

                        <?php else: ?>

                            <span class="badge bg-primary">
                                <?= htmlspecialchars($request['status']) ?>
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= htmlspecialchars($request['workflow_stage']) ?>
                    </td>

                    <td>
                        <?= $request['created_at'] ?>
                    </td>

                   
<td>

    <a
        href="?page=view-request&id=<?= $request['id'] ?>"
        class="btn btn-info btn-sm">
        View
    </a>

    <?php if ($request['workflow_stage'] === 'Submitted'): ?>

        <a
            href="?page=approve-consultation&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Approve Consultation
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

        <a
            href="?page=confirm-consultation-admin&id=<?= $request['id'] ?>"
            class="btn btn-primary btn-sm">
            Confirm Consultation
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Consultation Confirmed'): ?>

        <a
            href="?page=complete-consultation&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Complete Consultation
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Consultation Completed'): ?>

        <?php if (empty($request['proposal'])): ?>

            <a
                href="?page=create-proposal&id=<?= $request['id'] ?>"
                class="btn btn-dark btn-sm">
                Create Proposal
            </a>

        <?php endif; ?>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Proposal Rejected'): ?>

        <a
            href="?page=edit-request&id=<?= $request['id'] ?>"
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


    <?php if ($request['workflow_stage'] === 'Service Scheduled'): ?>

        <a
            href="?page=approve-service-schedule&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Approve Service
        </a>

    <?php endif; ?>


    <?php if ($request['workflow_stage'] === 'Service Active'): ?>

        <a
            href="?page=complete-service-form&id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">
            Complete Service
        </a>

    <?php endif; ?>


    <?php if (
        !in_array(
            $request['workflow_stage'],
            [
                'Proposal Sent',
                'Service Active',
                'Completed'
            ]
        )
    ): ?>

        <a
            href="?page=edit-request&id=<?= $request['id'] ?>"
            class="btn btn-warning btn-sm">
            Edit
        </a>

    <?php endif; ?>


    <a
        href="?page=delete-request&id=<?= $request['id'] ?>"
        class="btn btn-danger btn-sm"
        onclick="return confirm('Delete request?')">
        Delete
    </a>

</td>


                    
                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>