<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/WorkflowHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->query("
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

     WHERE COALESCE(requests.workflow_stage, '') <> 'Completed'
     ORDER BY requests.created_at DESC
");

$requests = $stmt->fetchAll();

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>



<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th class="text-center" style="width:100px;">Request #</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Quoted Price</th>
                <th>Status</th>
                <th>Workflow Stage</th>
                <th>Request Date</th>
                
                <th>Action</th>

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

                    <td>
                        <?= formatDate($request['created_at']) ?>
                    </td>

                   
<td>

    <a
        href="?page=view-request&id=<?= $request['id'] ?>"
        class="btn btn-info btn-sm">
        View
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


    <?php if ($request['workflow_stage'] === 'Service Scheduled'): ?>

        <a href="?page=review-service&id=<?= $request['id'] ?>" class="btn btn-success btn-sm">
            Review
        </a>

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

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>