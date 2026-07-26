<?php

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT

        r.*,

        c.name  AS customer_name,
        c.email,
        c.phone,

        a.name  AS agent_name,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN consultation_bookings cb
        ON cb.request_id = r.id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE r.id = ?

    LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {

    die('Consultation not found.');

}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_review'])
) {

    $decision = $_POST['admin_decision'] ?? '';
    $comments = trim($_POST['admin_review_comments'] ?? '');

    if ($decision === '') {

        die('Please select a decision.');

    }

    if ($decision === 'approve') {

    $update = $pdo->prepare("
        UPDATE requests
        SET
            admin_review_comments = ?,
            workflow_stage = 'Proposal Draft'
        WHERE id = ?
    ");

    $update->execute([
        $comments,
        $consultation['id']
    ]);

    header("Location: ?page=needs-admin-review&success=consultation-approved");
    exit;

}

    if ($decision === 'return') {

    $update = $pdo->prepare("
        UPDATE requests
        SET
            admin_review_comments = ?,
            workflow_stage = 'Consultation In Progress',
            job_status = 'In Progress'
        WHERE id = ?
    ");

    $update->execute([
        $comments,
        $consultation['id']
    ]);

    header("Location: ?page=needs-admin-review&success=returned-to-agent");
    exit;

}

}

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="row mb-4">

    <div class="col-md-8">

        <h2>

            Review Consultation

        </h2>

        <p class="text-muted">

            Request #<?= $consultation['id'] ?>

        </p>

    </div>

    <div class="col-md-4 text-end">

        <small class="text-muted">

            Current Status

        </small>

        <br>

        <span class="badge bg-warning text-dark fs-6 px-4 py-2">

            <?= htmlspecialchars($consultation['job_status']) ?>

        </span>

    </div>

</div>


<div class="row">

    <!-- Customer Information -->

    <div class="col-md-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">

                Customer Information

            </div>

            <div class="card-body">

                <p>

                    <strong>Name:</strong>

                    <?= htmlspecialchars($consultation['customer_name']) ?>

                </p>

                <p>

                    <strong>Email:</strong>

                    <?= htmlspecialchars($consultation['email']) ?>

                </p>

                <p class="mb-0">

                    <strong>Phone:</strong>

                    <?= htmlspecialchars($consultation['phone']) ?>

                </p>

            </div>

        </div>

    </div>

    <!-- Service Information -->

    <div class="col-md-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">

                Service Information

            </div>

            <div class="card-body">

                <p>

                    <strong>Service:</strong>

                    <?= htmlspecialchars($consultation['service_name']) ?>

                </p>

                <p>

                    <strong>Quoted Price:</strong>

                    AED <?= number_format($consultation['quoted_price'],2) ?>

                </p>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header">

        Meeting Information

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3">

                <strong>Date</strong><br>

                <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

            </div>

            <div class="col-md-3">

                <strong>Time</strong><br>

                <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

            </div>

            <div class="col-md-3">

                <strong>Method</strong><br>

                <?= $consultation['consultation_method'] ?: 'Not Assigned' ?>

            </div>

            <div class="col-md-3">

                <strong>Meeting</strong><br>

                <?php if (!empty($consultation['meeting_link'])): ?>

                    <a href="<?= htmlspecialchars($consultation['meeting_link']) ?>" target="_blank">

                        Join Meeting

                    </a>

                <?php else: ?>

                    Not Available

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header">

        Customer Request

    </div>

    <div class="card-body">

        <div class="border rounded p-3 bg-light">

            <?= nl2br(htmlspecialchars($consultation['description'])) ?>

        </div>

    </div>

</div>

<?php if ($consultation['job_status'] === 'Could Not Complete'): ?>

<div class="card shadow-sm mb-4 border-danger">

    <div class="card-header bg-danger text-white">

        Consultation Outcome

    </div>

    <div class="card-body">

        <p class="mb-3">

            The assigned agent could not complete this consultation and requested an administrator review.

        </p>

        <strong>Reason for Review</strong>

        <div class="border rounded bg-light p-3 mt-2">

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </div>

    </div>

</div>

<?php elseif ($consultation['job_status'] === 'Completed'): ?>

<div class="card shadow-sm mb-4 border-success">

    <div class="card-header bg-success text-white">

        Consultation Completed

    </div>

    <div class="card-body">

        <p class="mb-3">

            The assigned agent completed this consultation and submitted it for administrator review.

        </p>

        <strong>Consultation Notes</strong>

        <div class="border rounded bg-light p-3 mt-2">

            <?= nl2br(htmlspecialchars($consultation['completion_notes'])) ?>

        </div>

    </div>

</div>

<?php endif; ?>

<form method="POST">

<?php if ($consultation['job_status'] === 'Completed'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        Final Review

    </div>

    <div class="card-body">

        <p class="mb-4">

            Please review the completed consultation before continuing.

        </p>

        <div class="mb-3">

            <label class="form-label">

                Review Comments

            </label>

            <textarea
                name="admin_review_comments"
                class="form-control"
                rows="3"><?= htmlspecialchars($consultation['admin_review_comments'] ?? '') ?></textarea>

        </div>

        <label class="form-label mt-3">
    Decision
</label>

<div class="row mt-2 mb-4">

    <div class="col-md-6">

        <div class="form-check">

            <input
                class="form-check-input"
                type="radio"
                name="admin_decision"
                id="approveConsultation"
                value="approve">

            <label
                class="form-check-label"
                for="approveConsultation">

                Approve Consultation

            </label>

        </div>

    </div>

    <div class="col-md-6">

        <div class="form-check">

            <input
                class="form-check-input"
                type="radio"
                name="admin_decision"
                id="returnAgent"
                value="return">

            <label
                class="form-check-label"
                for="returnAgent">

                Return to Agent

            </label>

        </div>

    </div>

</div>

        </div>

        <div class="text-end mt-4">

            <button
                type="submit"
                name="submit_review"
                class="btn btn-success">

                Continue →

            </button>

        </div>

    </div>

</div>

<?php endif; ?>

</form>

<?php if ($consultation['job_status'] === 'Could Not Complete'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        Administrator Decision

    </div>

    <div class="card-body">

        <p class="text-muted mb-4">

            Choose the next action for this consultation.

        </p>

        

        <h4 class="mb-3">Workflow Decisions</h4>

<div class="row g-3">

    <!-- Reschedule -->
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 decision-option">
            <div class="card-body p-4">
                <input class="form-check-input float-end"
                       type="radio"
                       name="decision"
                       value="reschedule">

                <h5 class="mt-2">
                    📅 Reschedule Consultation
                </h5>

                <small class="text-muted">
                    Schedule another consultation with the customer.
                </small>
            </div>
        </div>
    </div>

    <!-- Reassign -->
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 decision-option">
            <div class="card-body">
                <input class="form-check-input float-end"
                       type="radio"
                       name="decision"
                       value="reassign">

                <h5 class="mt-2">
                    👤 Assign Another Agent
                </h5>

                <small class="text-muted">
                    Transfer this consultation to another consultant.
                </small>
            </div>
        </div>
    </div>

    <!-- Approve Customer Contact -->
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 decision-option">
            <div class="card-body">
                <input class="form-check-input float-end"
                       type="radio"
                       name="decision"
                       value="contact">

                <h5 class="mt-2">
                    <i class="bi bi-person-check-fill me-2"></i>Approve Customer Contact
                </h5>

                <small class="text-muted">
                    Approve the original agent to contact the customer and continue the consultation.
                </small>
            </div>
        </div>
    </div>

    <!-- Close -->
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 decision-option">
            <div class="card-body">
                <input class="form-check-input float-end"
                       type="radio"
                       name="decision"
                       value="close">

                <h5 class="mt-2">
                    <i class="bi bi-lock-fill me-2"></i>Close Request
                </h5>

                <small class="text-muted">
                    Close this consultation permanently.
                </small>
            </div>
        </div>
    </div>

</div>

<hr class="my-4">

        <div class="text-end mt-4">

            <button

                id="continueBtn"

                class="btn btn-success btn-lg px-4"

                disabled>

                Continue →

            </button>

        </div>

    </div>

</div>

<?php endif; ?>

<script>

const options = document.querySelectorAll('.decision-option');
const button = document.getElementById('continueBtn');

options.forEach(option => {

    option.addEventListener('click', function () {

        options.forEach(o => {
            o.classList.remove('border-primary', 'shadow', 'bg-light');
        });

        this.classList.add('border-primary', 'shadow', 'bg-light');

        this.querySelector('input').checked = true;

        button.disabled = false;

    });

});

button.addEventListener('click', function () {

    const selected = document.querySelector('input[name="decision"]:checked');

    if (!selected) return;

    const id = <?= (int)$consultation['id']; ?>;

    switch (selected.value) {

    case 'reschedule':
        window.location =
            '?page=admin-reschedule-consultation&id=' + id;
        break;

    case 'reassign':
        window.location =
            '?page=admin-assign-agent&id=' + id;
        break;

    case 'contact':
        window.location =
            '?page=admin-contact-customer&id=' + id;
        break;

    case 'close':
        window.location =
            '?page=admin-close-request&id=' + id;
        break;

}

});

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>