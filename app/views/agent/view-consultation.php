<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/meeting.php';

$requestId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT

        r.*,

        c.name  AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

    FROM requests r

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE r.id = ?
    AND cb.agent_id = ?

    LIMIT 1
");

$stmt->execute([
    $requestId,
    $_SESSION['agent']['id']
]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_contact_result'])
) {

    $contactResult    = trim($_POST['contact_result'] ?? '');
    $contactNotes     = trim($_POST['contact_notes'] ?? '');
    $customerDecision = trim($_POST['customer_decision'] ?? '');

    if ($contactResult === '' || $contactNotes === '') {
        die('Please complete all required fields.');
    }

    if (
        $contactResult === 'Customer Answered'
        && $customerDecision === ''
    ) {
        die('Please select the customer decision.');
    }

    /*
    |--------------------------------------------------------------------------
    | Determine Next Workflow Stage
    |--------------------------------------------------------------------------
    */

    $workflowStage = $consultation['workflow_stage'];

    if ($contactResult === 'No Answer') {

        // Agent can try again later.
        $workflowStage = 'Customer Contact';

    } elseif ($contactResult === 'Wrong Number') {

        // Administrator must review.
        $workflowStage = 'Needs Admin Review';

    } elseif ($contactResult === 'Customer Requested Reschedule') {

        // Administrator must arrange a new consultation.
        $workflowStage = 'Needs Admin Review';

    } elseif ($contactResult === 'Customer Answered') {

        if ($customerDecision === 'Continue Consultation') {

            $workflowStage = 'Consultation In Progress';

        } elseif ($customerDecision === 'Close Request') {

            // Customer requested closure.
            // Administrator must review and confirm.
            $workflowStage = 'Needs Admin Review';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests
        SET
            contact_result = ?,
            contact_notes = ?,
            contact_attempts = contact_attempts + 1,
            job_status = 'In Progress',
            workflow_stage = ?,
            review_type = CASE
                WHEN ? = 'Needs Admin Review'
                    THEN 'customer_contact'
                ELSE review_type
            END
        WHERE id = ?
    ");

    $update->execute([
        $contactResult,
        $contactNotes,
        $workflowStage,
        $workflowStage,
        $consultation['id']
    ]);

   /*
|--------------------------------------------------------------------------
| Record Consultation In Progress Event
|--------------------------------------------------------------------------
*/

if (
    $contactResult === 'Customer Answered' &&
    $customerDecision === 'Continue Consultation'
) {
    RequestEventHelper::addCurrentUser(
        $pdo,
        $consultation['id'],
        'CONSULTATION_IN_PROGRESS',
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation In Progress',
        'The customer was contacted and chose to continue with the consultation.',
        true
    );
}

    header('Location: ?page=agent-consultations&success=contact-saved');
    exit;
}

$meetingLink = getMeetingLink(
    $consultation['consultation_method'],
    $consultation['slot_time']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_notes'])) {

        $notes = trim($_POST['agent_notes']);

        $update = $pdo->prepare("
            UPDATE requests
            SET completion_notes = ?
            WHERE id = ?
        ");

        $update->execute([
            $notes,
            $requestId
        ]);

        header("Location: ?page=view-consultation&id=".$requestId);
        exit;
    }

    elseif (isset($_POST['complete_consultation'])) {

        // Customer Contact requests must not be completed from this form.
        if ($consultation['workflow_stage'] === 'Customer Contact') {
            die('This request is currently in Customer Contact and cannot be completed from the consultation form.');
        }

        $notes = trim($_POST['agent_notes'] ?? '');

        $update = $pdo->prepare("
            UPDATE requests
            SET
                completion_notes = ?,
                job_status = 'Completed',
                completed_at = NOW(),
                workflow_stage = 'Needs Admin Review'
            WHERE id = ?
        ");

        $update->execute([
            $notes,
            $requestId
        ]);

        header("Location: ?page=view-consultation&id=" . $requestId);
        exit;
    }

    elseif (isset($_POST['start_consultation'])) {

        $update = $pdo->prepare("
            UPDATE requests
            SET job_status = 'In Progress'
            WHERE id = ?
        ");

        $update->execute([$requestId]);

        header("Location: ?page=view-consultation&id=" . $requestId);
        exit;
    }

}

if (!$consultation) {

    die('Consultation not found.');
}

$status = $consultation['job_status'];

$badge = 'secondary';

switch ($status) {

    case 'Pending':
        $badge = 'warning';
        break;

    case 'In Progress':
        $badge = 'primary';
        break;

    case 'Completed':
        $badge = 'success';
        break;

    case 'Could Not Complete':
        $badge = 'danger';
        break;
}

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">

    <div>

        <h2 class="mb-1">

            Consultation Details

        </h2>

        <p class="text-muted mb-0">

            Request #<?= $consultation['id'] ?>

        </p>

    </div>

    <div class="text-end">


    <span class="badge bg-<?= $badge ?> fs-5 px-4 py-2">

        <?= htmlspecialchars($status) ?>

    </span>

    <?php if (
    $consultation['job_status'] == 'Completed' ||
    $consultation['job_status'] == 'Could Not Complete'
): ?>

        <small class="text-muted d-block mt-2">

            Completed on<br>

            <?= date('d M Y h:i A', strtotime($consultation['completed_at'])) ?>

        </small>

    <?php endif; ?>

    <?php if ($consultation['job_status'] == 'Could Not Complete'): ?>

        <small class="text-danger d-block mt-2">

            Reason

            <br>

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </small>

    <?php endif; ?>

</div>

</div>

    <div class="row">

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    Customer Information

                </div>

                <div class="card-body">

                    <p><strong>Name:</strong> <?= htmlspecialchars($consultation['customer_name']) ?></p>

                    <p><strong>Email:</strong> <?= htmlspecialchars($consultation['email']) ?></p>

                    <p><strong>Phone:</strong> <?= htmlspecialchars($consultation['phone']) ?></p>

                </div>

            </div>

        </div>

        <div class="col-lg-6">

            <div class="card shadow-sm mb-4">

                <div class="card-header">

                    Service Information

                </div>

                <div class="card-body">

                    <p><strong>Service:</strong> <?= htmlspecialchars($consultation['service_name']) ?></p>

                    <p><strong>Quoted Price:</strong> AED <?= number_format($consultation['quoted_price'],2) ?></p>

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

                    <?= formatDate($consultation['slot_date']) ?>

                </div>

                <div class="col-md-3">

                    <strong>Time</strong><br>

                    <?= formatTime($consultation['slot_time']) ?>

                </div>

                <div class="col-md-3">

                    <strong>Method</strong><br>

                    <?= $consultation['consultation_method'] ?: 'Not Assigned' ?>

                </div>

                <div class="col-md-3">

                    <strong>Meeting</strong><br>

                   <?php if (shouldShowMeetingLink(
    $consultation['slot_date'],
    $consultation['slot_time']
)): ?>

    <a
        href="<?= htmlspecialchars($meetingLink) ?>"
        target="_blank"
        class="btn btn-success btn-sm mt-2">

        Join <?= htmlspecialchars($consultation['consultation_method']) ?>

    </a>

<?php else: ?>

    <div class="small text-muted mt-2">
        Meeting link will be available
        10 minutes before your consultation.
    </div>

<?php endif; ?>
                </div>

            </div>

        </div>

    </div>

   <?php if ($consultation['workflow_stage'] === 'Customer Contact'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">
        Administrator Instructions
    </div>

    <div class="card-body">

        <div class="border rounded bg-light p-3">
            <?= nl2br(htmlspecialchars($consultation['admin_instruction'])) ?>
        </div>

    </div>

</div>

<?php endif; ?>

<?php if ($consultation['workflow_stage'] === 'Customer Contact'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">
        Contact Customer
    </div>

    <div class="card-body">

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Contact Result
                </label>

                <select
                    name="contact_result"
                    id="contact_result"
                    class="form-select"
                    required>

                    <option value="">
                        Select Result
                    </option>

                    <option value="Customer Answered">
                        Customer Answered
                    </option>

                    <option value="No Answer">
                        No Answer
                    </option>

                    <option value="Wrong Number">
                        Wrong Number
                    </option>

                    <option value="Customer Requested Reschedule">
                        Customer Requested Reschedule
                    </option>

                </select>

            </div>

            <div
                id="customerDecisionSection"
                class="mb-3"
                style="display:none;">

                <label class="form-label">
                    Customer Decision
                </label>

                <select
                    name="customer_decision"
                    id="customerDecision"
                    class="form-select">

                    <option value="">
                        Select Decision
                    </option>

                    <option value="Continue Consultation">
                        Continue Consultation
                    </option>

                    <option value="Close Request">
                        Close Request
                    </option>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Agent Notes
                </label>

                <textarea
                    name="contact_notes"
                    class="form-control"
                    rows="5"
                    required></textarea>

            </div>

            <button
                type="submit"
                name="save_contact_result"
                class="btn btn-success">

                Save Contact Result

            </button>

        </form>

    </div>

</div>

<?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Customer Request

        </div>

        <div class="card-body">

            <div class="bg-light border rounded p-3">

                <?= nl2br(htmlspecialchars($consultation['description'])) ?>

            </div>

        </div>

    </div>

    <?php if ($consultation['job_status'] == 'Could Not Complete'): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-danger text-white">

        Incomplete Reason

    </div>

    <div class="card-body">

        <p>

            <strong>Reason:</strong>

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </p>

    </div>

</div>

<?php endif; ?>

<?php if (!empty($consultation['admin_review_comments'])): ?>

<div class="card shadow-sm mb-4 border-warning">

    <div class="card-header bg-warning">

        Administrator Review

    </div>

    <div class="card-body">

        <p class="mb-2">

            The administrator has requested changes before approving this consultation.

        </p>

        <div class="border rounded bg-light p-3">

            <?= nl2br(htmlspecialchars($consultation['admin_review_comments'])) ?>

        </div>

    </div>

</div>

<?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            Consultation Notes

        </div>

        <div class="card-body">

           <form method="POST">

                <textarea
                    class="form-control"
                    rows="8"
                    name="agent_notes"
                    >

                <?= htmlspecialchars($consultation['completion_notes']) ?>

                </textarea>

        </div>

    </div>

   <div class="d-flex justify-content-between mt-4">

    <a
        href="?page=agent-consultations"
        class="btn btn-secondary px-4">

        ← Back

    </a>

    <div>

        <?php if ($consultation['job_status'] == 'Pending'): ?>

            <button
                type="submit"
                name="start_consultation"
                class="btn btn-success">

                ▶ Start Consultation

            </button>

        <?php endif; ?>


        <?php if (
            $consultation['job_status'] === 'In Progress'
            && $consultation['workflow_stage'] !== 'Customer Contact'
        ): ?>

    <button type="submit" name="save_notes" class="btn btn-primary">
        💾 Save Notes
    </button>

    <button type="submit" name="complete_consultation" class="btn btn-success">
        ✅ Complete Consultation
    </button>

    <a href="?page=cannot-complete-consultation&id=<?= $consultation['id'] ?>"
       class="btn btn-danger">
        ❌ Could Not Complete
    </a>

<?php endif; ?>

    </div>

</div>

</form>

</div>

<script>

const contactResult = document.getElementById('contact_result');
const customerDecisionSection = document.getElementById('customerDecisionSection');

function toggleCustomerDecision() {

    if (!contactResult || !customerDecisionSection) {
        return;
    }

    if (contactResult.value === 'Customer Answered') {

        customerDecisionSection.style.display = 'block';

    } else {

        customerDecisionSection.style.display = 'none';

    }

}

document.addEventListener('DOMContentLoaded', function () {

    toggleCustomerDecision();

    contactResult.addEventListener('change', toggleCustomerDecision);

});

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>