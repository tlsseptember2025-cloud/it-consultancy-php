<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$agentId = $_SESSION['agent']['id'];

/*
|--------------------------------------------------------------------------
| Active Consultations
|--------------------------------------------------------------------------
|
| These are the consultations that still require normal agent action.
|
*/

$stmt = $pdo->prepare("
    SELECT

        cb.id,

        r.id AS request_id,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,

        cs.slot_time,

r.job_status,
r.workflow_stage,
r.incomplete_reason

    FROM consultation_bookings cb

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    INNER JOIN requests r
        ON r.id = cb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE
        cb.agent_id = ?

        AND r.workflow_stage IN (
    'Consultation Confirmed',
    'Customer Contact',
    'Missed Consultation',
    'Consultation Decision Required'
)

    ORDER BY
        cs.slot_date,
        cs.slot_time
");

$stmt->execute([$agentId]);

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Overdue Consultations Requiring Explanation
|--------------------------------------------------------------------------
|
| These were started but remained In Progress after the
| one-hour consultation session and were automatically moved
| to Needs Admin Review.
|
*/

$stmt = $pdo->prepare("
    SELECT

        cb.id,

        r.id AS request_id,

        c.name AS customer_name,

        s.title AS service_name,

        cs.slot_date,

        cs.slot_time,

        r.job_status,
        r.workflow_stage,
        r.incomplete_reason

    FROM consultation_bookings cb

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    INNER JOIN requests r
        ON r.id = cb.request_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE
        cb.agent_id = ?

        AND r.workflow_stage = 'Needs Admin Review'

        AND r.job_status = 'Needs Admin Review'

        AND r.review_type = 'consultation_overdue'

        AND r.missed_consultation_reason IS NULL

    ORDER BY
        cs.slot_date DESC,
        cs.slot_time DESC
");

$stmt->execute([$agentId]);

$overdueConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>

            My Consultations

        </h2>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th class="text-center" style="width:100px;">Request #</th>

                            <th>Date</th>

                            <th>Time</th>

                            <th>Customer</th>

                            <th>Service</th>

                            <th>Stage</th>

                            <th>Job Status</th>

                            <th style="width:260px;">Action</th>

                        </tr>

                    </thead>

                    <tbody>

<?php if (empty($consultations)): ?>

<tr>

    <td colspan="6" class="text-center text-muted py-4">

        No consultations assigned.

    </td>

</tr>

<?php else: ?>

<?php foreach ($consultations as $consultation): ?>

<tr>

    <td class="text-center">

        <strong>#<?= (int)$consultation['request_id'] ?></strong>

    </td>

    <td>

        <?= formatDate($consultation['slot_date']) ?>

    </td>

    <td>

        <?= formatTime($consultation['slot_time']) ?>

    </td>

    <td>

        <?= htmlspecialchars($consultation['customer_name']) ?>

    </td>

    <td>

        <?= htmlspecialchars($consultation['service_name']) ?>

    </td>

    <td>
        <?= htmlspecialchars($consultation['workflow_stage']) ?>
    </td>

    <td>

        <?= htmlspecialchars($consultation['job_status']) ?>

    </td>

    <td>

        <?php if ($consultation['workflow_stage'] === 'Customer Contact'): ?>

    <a href="?page=contact-customer&request_id=<?= $consultation['request_id'] ?>"
       class="btn btn-primary btn-sm">

        Contact Customer

    </a>

<?php elseif (
    $consultation['workflow_stage'] === 'Missed Consultation'
    || $consultation['workflow_stage'] === 'Consultation Decision Required'
): ?>

    <a
        href="?page=explain-missed-consultation&id=<?= (int) $consultation['request_id'] ?>"
        class="btn btn-danger btn-sm text-nowrap">

        Explain Missed Consultation

    </a>

<?php elseif ($consultation['workflow_stage'] === 'Consultation Confirmed'): ?>

    <a
        href="?page=view-consultation&id=<?= $consultation['request_id'] ?>"
        class="btn btn-success btn-sm">

        View Consultation

    </a>

<?php endif; ?>
        
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

<?php if (!empty($overdueConsultations)): ?>

    <div class="card shadow-sm border-danger mt-4">

        <div class="card-header bg-danger text-white">

            <strong>
                Consultations Requiring Explanation
            </strong>

        </div>

        <div class="card-body">

            <p class="text-muted">

                These consultations exceeded the allowed session time
                and were automatically sent for administrator review.

                Please provide an explanation for each case.

            </p>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>Request #</th>

                            <th>Date</th>

                            <th>Time</th>

                            <th>Customer</th>

                            <th>Service</th>

                            <th>Status</th>

                            <th style="width:240px;">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($overdueConsultations as $consultation): ?>

                        <tr>

                            <td class="text-center">

                                <strong>
                                    #<?= (int) $consultation['request_id'] ?>
                                </strong>

                            </td>

                            <td>
                                <?= formatDate(
                                    $consultation['slot_date']
                                ) ?>
                            </td>

                            <td>
                                <?= formatTime(
                                    $consultation['slot_time']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $consultation['customer_name']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $consultation['service_name']
                                ) ?>
                            </td>

                            <td>

                                <span class="badge bg-danger">
                                    Needs Admin Review
                                </span>

                            </td>

                            <td>

                            <?php if (empty($consultation['incomplete_reason'])): ?>

    <a
        href="?page=explain-overdue-consultation&id=<?= (int) $consultation['request_id'] ?>"
        class="btn btn-danger btn-sm text-nowrap">

        Explain Overdue Consultation

    </a>

<?php else: ?>

    <span class="badge bg-success">

        Explanation Submitted

    </span>

<?php endif; ?>    
                            
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

<?php endif; ?>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>