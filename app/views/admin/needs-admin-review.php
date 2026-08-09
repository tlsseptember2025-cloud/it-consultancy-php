<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$stmt = $pdo->prepare("
    SELECT

        r.id,
        r.job_status,
        r.workflow_stage,
        r.review_type,
        r.incomplete_reason,
        r.contact_result,

        c.name AS customer_name,

        a.name AS agent_name,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.workflow_stage = 'Needs Admin Review'

    ORDER BY cs.slot_date DESC,
             cs.slot_time DESC
");

$stmt->execute();

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require VIEW_PATH . '/layouts/header-admin.php';
?>

<div class="container py-4">

    <?php if (isset($_GET['success']) && $_GET['success'] === 'rescheduled'): ?>

    <div
        id="successAlert"
        class="alert alert-success alert-dismissible fade show"
        role="alert">

        <strong>Success!</strong>

        The consultation has been successfully rescheduled.

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close">
        </button>

    </div>

    <script>

        // Remove the success parameter from the URL
        if (window.history.replaceState) {

            const url = new URL(window.location);

            url.searchParams.delete('success');

            window.history.replaceState({}, document.title, url.pathname + url.search);

        }

        // Automatically hide the alert after 5 seconds
        setTimeout(function () {

            const alert = document.getElementById('successAlert');

            if (alert) {

                alert.classList.remove('show');

                setTimeout(function () {

                    alert.remove();

                }, 300);

            }

        }, 5000);

    </script>

<?php endif; ?>

    <h2 class="mb-1">

        Needs Admin Review

    </h2>

    <p class="text-muted mb-4">

        Consultations that require an administrator's decision.

    </p>

   <?php if (empty($consultations)): ?>

    <div class="alert alert-success">

        There are currently no consultations waiting for administrator review.

    </div>

<?php else: ?>

<div class="card shadow-sm">

    <div class="card-body p-0">

        <table class="table table-hover mb-0">

            <thead class="table-dark">

                <tr>

                    <th class="text-center" style="width:100px;">Request #</th>
                    <th>Customer</th>
                    <th>Agent</th>
                    <th>Service</th>
                    <th>Workflow</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($consultations as $consultation): ?>

                <tr>

                    <td class="text-center">
                        <strong>#<?= (int)$consultation['id'] ?></strong>
                    </td>

                    <td><?= htmlspecialchars($consultation['customer_name']) ?></td>

                    <td><?= htmlspecialchars($consultation['agent_name']) ?></td>

                    <td><?= htmlspecialchars($consultation['service_name']) ?></td>

<td>

<?php

$statusClass = 'bg-warning text-dark';

if ($consultation['job_status'] == 'Completed') {
    $statusClass = 'bg-success';
}

?>

<span class="badge <?= $statusClass ?>">

    <?= htmlspecialchars($consultation['job_status']) ?>

</span>

</td>

<td>

    <?= htmlspecialchars($consultation['incomplete_reason']) ?>

</td>

<td>

    <?= formatDate($consultation['slot_date']) ?>

    <br>

    <small class="text-muted">

        <?= formatTime($consultation['slot_time']) ?>

    </small>

</td>

                 <td>

                    <?php

/*
|--------------------------------------------------------------------------
| Determine Review Page
|--------------------------------------------------------------------------
|
| Customer Contact reviews normally go to:
| admin-contact-customer
|
| EXCEPTION:
| Customer Answered + Needs Admin Review means the
| customer requested closure.
|
*/

if (
    $consultation['review_type'] === 'customer_contact'
    && $consultation['workflow_stage'] === 'Needs Admin Review'
    && $consultation['contact_result'] === 'Customer Answered'
) {

    $reviewPage = 'admin-close-request';

} elseif (
    $consultation['review_type'] === 'customer_contact'
) {

    $reviewPage = 'admin-contact-customer';

} else {

    $reviewPage = 'admin-review-consultation';

}

?>

                    <a
                        href="?page=<?= $reviewPage ?>&id=<?= $consultation['id'] ?>"
                        class="btn btn-primary btn-sm">

                        Review →

                    </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>