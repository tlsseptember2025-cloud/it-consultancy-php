<?php require __DIR__ . '/layouts/header.php'; ?>

<?php

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        r.*,

        s.title AS service_title,

        cs.slot_date,

        cs.slot_time

    FROM requests r

    JOIN services s
        ON r.service_id = s.id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.customer_id = ?

    ORDER BY r.id DESC
");

$stmt->execute([$customerId]);

$requests = $stmt->fetchAll();

?>

<h1 class="mb-4">My Requests</h1>

<div class="card">

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

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
                            <?= htmlspecialchars($request['service_title']) ?>
                        </td>

                        <td>

                            <?php if ($request['quoted_price'] > 0): ?>

                                $<?= number_format($request['quoted_price'], 2) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Awaiting Quote
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($request['status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($request['workflow_stage']) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($request['created_at'])) ?>
                        </td>

                        <td>

    <?php if ($request['workflow_stage'] === 'Consultation Approved'): ?>

        <a
            href="?page=schedule-consultation&request_id=<?= $request['id'] ?>"
            class="btn btn-success btn-sm">

            Schedule Consultation

        </a>

    <?php elseif ($request['workflow_stage'] === 'Consultation Scheduled'): ?>

        <span class="badge bg-info">

            <?= date(
                'M d, Y',
                strtotime($request['slot_date'])
            ) ?>

            @

            <?= date(
                'h:i A',
                strtotime($request['slot_time'])
            ) ?>

        </span>

    <?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Proposal Sent'): ?>

        <a
            href="?page=view-proposal&request_id=<?= $request['id'] ?>"
            class="btn btn-primary btn-sm">

            View Proposal

        </a>

    <?php endif; ?>

    <?php if ($request['workflow_stage'] === 'Proposal Accepted'): ?>

    <a
        href="?page=schedule-service&request_id=<?= $request['id'] ?>"
        class="btn btn-success btn-sm">

        Schedule Service

    </a>

    <?php endif; ?>

    <?php if ($request['workflow_stage'] === WF_AWAITING_PAYMENT): ?>
        

    <a
        href="?page=customer-upload-slip&request_id=<?= $request['id'] ?>"
        class="btn btn-warning btn-sm">

        Upload Deposit Slip

    </a>

    <?php elseif ($request['workflow_stage'] === WF_PAYMENT_SUBMITTED): ?>

    <span class="badge bg-info">
        Payment Under Review
    </span>

    <?php endif; ?>

</td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>