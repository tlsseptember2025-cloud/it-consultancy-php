<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/email.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        a.name AS agent_name,
        s.title AS service_name,
        cb.id AS booking_id,
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

/*
|--------------------------------------------------------------------------
| Load Available Agents For Consultation Reassignment
|--------------------------------------------------------------------------
*/

$agentsStmt = $pdo->prepare("
    SELECT
        id,
        name
    FROM agents
    WHERE id != ?
    ORDER BY name ASC
");

$agentsStmt->execute([
    $consultation['agent_id']
]);

$availableAgents =
    $agentsStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Consultation not found.');
}


/*
|--------------------------------------------------------------------------
| Customer Contact Workflow
|--------------------------------------------------------------------------
*/

$contactAttempts = (int)($consultation['contact_attempts'] ?? 0);

$canRetryContact =
    $contactAttempts < MAX_CONTACT_ATTEMPTS;

$maximumAttemptsReached =
    $contactAttempts >= MAX_CONTACT_ATTEMPTS;


/*
|--------------------------------------------------------------------------
| Review Type
|--------------------------------------------------------------------------
*/

$reviewType = $consultation['review_type'] ?? 'consultation';

$isConsultationReview =
    ($reviewType === 'consultation');

$isCustomerContactReview =
    ($reviewType === 'customer_contact');

$isOverdueConsultationReview =
    ($reviewType === 'consultation_overdue');

$isConsultationNotCompletedReview =
    ($reviewType === 'consultation_not_completed');

/*
|--------------------------------------------------------------------------
| Overdue Consultation Administrator Decision
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        $isOverdueConsultationReview
        || $isConsultationNotCompletedReview
    )
    && isset($_POST['admin_decision'])
) {

    $decision =
        $_POST['admin_decision'] ?? '';

    $comments =
        trim($_POST['admin_review_comments'] ?? '');

    if ($isConsultationNotCompletedReview) {

    if ($decision !== 'reassign') {

        die('Consultation not completed cases must be reassigned.');

    }

} else {

    if (
        !in_array(
            $decision,
            [
                'accept',
                'reject',
                'reassign'
            ],
            true
        )
    ) {

        die('Invalid administrator decision.');

    }

}

    if ($comments === '') {

        die('Administrator comments are required.');

    }


    /*
    |--------------------------------------------------------------------------
    | Identify Current Administrator
    |--------------------------------------------------------------------------
    */

    $adminStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $adminStmt->execute([
        $_SESSION['user']
    ]);

    $currentAdmin =
        $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentAdmin) {

        die(
            'Unable to identify the current administrator.'
        );

    }

    $currentAdminId =
        (int) $currentAdmin['id'];


    /*
|--------------------------------------------------------------------------
| Accept Explanation
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Accept Explanation
|--------------------------------------------------------------------------
*/

if ($decision === 'accept') {

    $pdo->beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | Update Request
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE requests
            SET
                workflow_stage = 'Awaiting Customer Confirmation',
                job_status = 'Pending',
                review_type = NULL,
                admin_review_comments = ?
            WHERE
                id = ?
                AND workflow_stage = 'Needs Admin Review'
                AND review_type = 'consultation_overdue'
        ");

        $update->execute([
            $comments,
            $consultation['id']
        ]);

        if ($update->rowCount() !== 1) {

            throw new Exception(
                'The consultation could not be updated because its review status changed.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Record Review History
        |--------------------------------------------------------------------------
        */

        $history = $pdo->prepare("
            INSERT INTO consultation_review_history
            (
                request_id,
                actor_type,
                admin_id,
                action_type,
                decision_type,
                message
            )
            VALUES
            (
                ?,
                'admin',
                ?,
                'admin_decision',
                'accept',
                ?
            )
        ");

        $history->execute([
            $consultation['id'],
            $currentAdminId,
            $comments
        ]);


        /*
        |--------------------------------------------------------------------------
        | Request Event
        |--------------------------------------------------------------------------
        */

        RequestEventHelper::addCurrentUser(
            $pdo,
            (int) $consultation['id'],
            'CONSULTATION_REVIEW_ACCEPTED',
            RequestEventHelper::TYPE_CONSULTATION,
            'Consultation Explanation Accepted',
            'The administrator accepted the agent explanation. Customer confirmation is now required before the consultation can be completed. Administrator comments: ' . $comments,
            true
        );


        /*
        |--------------------------------------------------------------------------
        | Commit Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        header(
            'Location: ?page=needs-admin-review&success=consultation-customer-confirmation-requested'
        );

        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();

        }

        die($e->getMessage());

    }

}

elseif ($decision === 'reassign') {

    $newAgentId = (int) ($_POST['new_agent_id'] ?? 0);

    if ($newAgentId <= 0) {

        die('Please select a new agent.');

    }

    if ($newAgentId === (int) $consultation['agent_id']) {

        die('The consultation must be reassigned to a different agent.');

    }

    $pdo->beginTransaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Consultation reassignment processing
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| Update Consultation Booking With New Agent
|--------------------------------------------------------------------------
*/

$bookingUpdate = $pdo->prepare("
    UPDATE consultation_bookings
    SET
        agent_id = ?
    WHERE
        id = ?
");

$bookingUpdate->execute([
    $newAgentId,
    $consultation['booking_id']
]);

if ($bookingUpdate->rowCount() !== 1) {

    throw new Exception(
        'The consultation booking could not be reassigned.'
    );

}

/*
|--------------------------------------------------------------------------
| Update Request For New Agent And Customer Reschedule
|--------------------------------------------------------------------------
*/

$requestUpdate = $pdo->prepare("
    UPDATE requests
    SET
        agent_id = ?,
        workflow_stage = 'Awaiting Customer Reschedule',
        job_status = 'Pending',
        review_type = NULL,
        admin_instruction = '__RESCHEDULE_ALLOWED__',
        admin_review_comments = ?,
        completed_at = NULL,
        completion_notes = NULL,
        incomplete_reason = NULL
    WHERE
        id = ?
        AND workflow_stage = 'Needs Admin Review'
        AND review_type = 'consultation_not_completed'
");

$requestUpdate->execute([
    $newAgentId,
    $comments,
    $consultation['id']
]);

if ($requestUpdate->rowCount() !== 1) {

    throw new Exception(
        'The request could not be updated for consultation reassignment.'
    );

}


/*
|--------------------------------------------------------------------------
| Record Consultation Reassignment History
|--------------------------------------------------------------------------
*/

$history = $pdo->prepare("
    INSERT INTO consultation_review_history
    (
        request_id,
        actor_type,
        admin_id,
        action_type,
        decision_type,
        message
    )
    VALUES
    (
        ?,
        'admin',
        ?,
        'admin_decision',
        'reassign',
        ?
    )
");

$history->execute([
    $consultation['id'],
    $currentAdminId,
    $comments
]);

/*
|--------------------------------------------------------------------------
| Record Consultation Reassignment Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $consultation['id'],
    'CONSULTATION_REASSIGNED',
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Reassigned',
    'The administrator reassigned the consultation to a new agent after the customer reported that the consultation was not completed. The customer can now select a new consultation appointment. Administrator comments: ' . $comments,
    true
);

/*
|--------------------------------------------------------------------------
| Commit Consultation Reassignment
|--------------------------------------------------------------------------
*/

$pdo->commit();


$_SESSION['success'] =
    'The consultation has been reassigned. The customer can now select a new consultation appointment.';


header(
    'Location: ?page=needs-admin-review'
);

exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    die($e->getMessage());

}

}

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


    /*
    |--------------------------------------------------------------------------
    | Record Consultation Approved Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::addCurrentUser(
        $pdo,
        $consultation['id'],
        'CONSULTATION_APPROVED',
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation Approved',
        'The administrator approved the completed consultation.',
        true
    );


    /*
    |--------------------------------------------------------------------------
    | Send Consultation Completed Email
    |--------------------------------------------------------------------------
    */

    $ratingLink =
    APP_URL . '/index.php?page=customer-rate-agent&booking_id='
    . (int) $consultation['booking_id'];


    sendEmail(
        $consultation['email'],
        'Consultation Completed',
        "
        <h2>Hello {$consultation['customer_name']},</h2>

        <p>
            Your consultation has been completed successfully.
        </p>

        <p>
            Our team is now preparing your quotation/proposal.
        </p>

        <p>
            You will receive another email once your proposal is ready
            for review.
        </p>

        <hr>

        <p>
            <strong>How was your consultation?</strong>
        </p>

        <p>
            We would appreciate your feedback about the consultation
            provided by your consultant.
        </p>

        <p>
            <a
                href='{$ratingLink}'
                style='
                    display:inline-block;
                    padding:10px 18px;
                    background:#0d6efd;
                    color:#ffffff;
                    text-decoration:none;
                    border-radius:5px;
                '>
                ⭐ Rate Your Consultation
            </a>
        </p>

        <br>

        <p>
            Kind regards,<br>
            <strong>IT Consultancy Team</strong>
        </p>
        "
    );


    header(
        "Location: ?page=needs-admin-review&success=consultation-approved"
    );

    exit;

} elseif ($decision === 'return') {

    $update = $pdo->prepare("
        UPDATE requests
        SET
            admin_review_comments = ?,
            workflow_stage = 'Consultation Confirmed',
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

<h2>

<?php if ($isOverdueConsultationReview): ?>

    Review Overdue Consultation

<?php elseif ($isConsultationReview): ?>

    Review Consultation

<?php else: ?>

    Review Customer Contact

<?php endif; ?>

</h2>

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


    <?php if (!empty($consultation['completed_at'])): ?>

        <?php

        $completedAt = new DateTimeImmutable(
            $consultation['completed_at'],
            new DateTimeZone('UTC')
        );

        $completedAt = $completedAt->setTimezone(
            new DateTimeZone('Asia/Dubai')
        );

        ?>

        <small class="text-muted d-block mt-2">

            Completed on<br>

            <?= $completedAt->format('d M Y h:i A') ?>

        </small>

    <?php endif; ?>

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

                    <?php if ((float)$consultation['quoted_price'] > 0): ?>

                        AED <?= number_format($consultation['quoted_price'], 2) ?>

                    <?php else: ?>

                        <span class="text-muted">Pending</span>

                    <?php endif; ?>

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

<?php if ($isOverdueConsultationReview): ?>

    <div class="card shadow-sm mb-4 border-danger">

        <div class="card-header bg-danger text-white">

            <strong>
                Overdue Consultation Investigation
            </strong>

        </div>

        <div class="card-body">

            <div class="alert alert-warning">

                <strong>Consultation Session Expired</strong>

                <br>

                The consultation was started by the assigned agent,
                but it remained open after the scheduled one-hour
                consultation session ended.

            </div>


            <div class="mb-4">

                <h5>
                    Agent Explanation
                </h5>

                <div class="border rounded bg-light p-3">

                    <?php if (!empty($consultation['incomplete_reason'])): ?>

                        <?= nl2br(
                            htmlspecialchars(
                                $consultation['incomplete_reason']
                            )
                        ) ?>

                    <?php else: ?>

                        <span class="text-muted">
                            No explanation was submitted.
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <div class="row">

                <div class="col-md-4">

                    <strong>Agent</strong><br>

                    <?= htmlspecialchars(
                        $consultation['agent_name']
                    ) ?>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Date</strong><br>

                    <?= formatDate(
                        $consultation['slot_date']
                    ) ?>

                </div>


                <div class="col-md-4">

                    <strong>Scheduled Time</strong><br>

                    <?= formatTime(
                        $consultation['slot_time']
                    ) ?>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>

<?php if (
    $consultation['workflow_stage'] === 'Needs Admin Review'
    && $consultation['job_status'] === 'Could Not Complete'
    && ($isConsultationReview || $isCustomerContactReview)
): ?>

<div class="card shadow-sm mb-4 border-danger">

    <div class="card-header bg-danger text-white">

        <?= $isConsultationReview
        ? 'Consultation Outcome'
        : 'Customer Contact Report'; ?>

    </div>

    <p class="mb-3">

    <?= $isConsultationReview
        ? 'The assigned agent could not complete this consultation and requested an administrator review.'
        : 'The assigned agent completed the customer contact and requested an administrator review.'; ?>

</p>

<?php if ($isCustomerContactReview): ?>

<div class="mb-3">

    <strong>Contact Result</strong>

    <div class="border rounded bg-light p-3 mt-2">

<?php if (!empty($consultation['contact_result'])):

    switch ($consultation['contact_result']) {

        case 'No Answer':
            $badgeClass = 'bg-warning text-dark';
            break;

        case 'Wrong Number':
            $badgeClass = 'bg-danger';
            break;

        case 'Customer Answered':
            $badgeClass = 'bg-success';
            break;

        default:
            $badgeClass = 'bg-secondary';
    }

?>

    <span class="badge <?= $badgeClass ?> fs-6">
        <?= htmlspecialchars($consultation['contact_result']) ?>
    </span>

<?php else: ?>

    <span class="text-muted">No contact result provided.</span>

<?php endif; ?>

</div>

</div>

<div class="mb-3">

    <strong>Agent Notes</strong>

    <div class="border rounded bg-light p-3 mt-2">

        <?= !empty($consultation['contact_notes'])
            ? nl2br(htmlspecialchars($consultation['contact_notes']))
            : '<span class="text-muted">No agent notes provided.</span>' ?>

    </div>

</div>

<?php endif; ?>

<?php if ($isConsultationReview): ?>

<div class="mb-3">

    <strong>Reason for Review</strong>

    <div class="border rounded bg-light p-3 mt-2">

        <?= !empty($consultation['incomplete_reason'])
            ? nl2br(htmlspecialchars($consultation['incomplete_reason']))
            : '<span class="text-muted">No reason provided.</span>' ?>

    </div>

</div>

<?php endif; ?>

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

<?php if (
    $isOverdueConsultationReview
    || $isConsultationNotCompletedReview
): ?>

    <form method="POST">

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-primary text-white">

                Administrator Decision

            </div>

            <div class="card-body">

                <p class="text-muted mb-4">

                    Review the agent's explanation and choose the next
                    action for this overdue consultation.

                </p>


                <div class="row g-4">
                
                <?php if ($isOverdueConsultationReview): ?>

                    <!-- Accept Explanation -->

                    <div class="col-md-4">

                        <div class="card h-100 border-success">

                            <div class="card-body">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="admin_decision"
                                        id="decisionAccept"
                                        value="accept"
                                        required
                                    >

                                    <label
                                        class="form-check-label"
                                        for="decisionAccept"
                                    >

                                        <strong>

                                            ✅ Accept Explanation & Request Customer Confirmation

                                        </strong>

                                    </label>

                                </div>

                                <p class="text-muted small mt-3 mb-0">

                                    Accept the agent's explanation and ask the
                                    customer to confirm whether the consultation
                                    was actually completed.

                                </p>

                            </div>

                        </div>

                    </div>


                    <!-- Reject Explanation -->

                    <div class="col-md-4">

                        <div class="card h-100 border-danger">

                            <div class="card-body">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="admin_decision"
                                        id="decisionReject"
                                        value="reject"
                                    >

                                    <label
                                        class="form-check-label"
                                        for="decisionReject"
                                    >

                                        <strong>

                                            ❌ Reject Explanation

                                        </strong>

                                    </label>

                                </div>

                                <p class="text-muted small mt-3 mb-0">

                                    Reject the explanation and return the case
                                    to the current agent for further handling.

                                </p>

                            </div>

                        </div>

                    </div>

                   <?php endif; ?>


                    <!-- Reassign Consultation -->

                    <div class="<?= $isConsultationNotCompletedReview ? 'col-md-12' : 'col-md-4' ?>">

                        <div class="card h-100 border-primary">

                            <div class="card-body">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="admin_decision"
                                        id="decisionReassign"
                                        value="reassign"
                                        <?= $isConsultationNotCompletedReview ? 'checked' : '' ?>
                                        required
                                    >

                                    <label
                                        class="form-check-label"
                                        for="decisionReassign"
                                    >

                                        <strong>

                                            👤 Reassign Consultation

                                        </strong>

                                    </label>

                                </div>

                                <p class="text-muted small mt-3 mb-0">

                                    Assign the consultation to another agent.
                                    The customer will then reschedule with the
                                    newly assigned agent.

                                </p>

                                <?php if ($isConsultationNotCompletedReview): ?>

    <div class="mt-3">

        <label for="newAgentId" class="form-label">

            <strong>Select New Agent</strong>

        </label>

        <select
            name="new_agent_id"
            id="newAgentId"
            class="form-select"
            required
        >

            <option value="">
                -- Select New Agent --
            </option>

            <?php foreach ($availableAgents as $agent): ?>

                <option value="<?= (int) $agent['id'] ?>">

                    <?= htmlspecialchars($agent['name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

        <small class="text-muted">

            Select the agent who will handle the replacement consultation.

        </small>

    </div>

<?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Administrator Comments -->

                <div class="mt-4">

                    <label class="form-label">

                        <strong>Administrator Comments</strong>

                    </label>

                    <textarea
                        name="admin_review_comments"
                        class="form-control"
                        rows="4"
                        required
                        placeholder="Enter the reason for your decision, investigation findings, and any instructions for the agent or customer..."
                    ></textarea>

                    <small class="text-muted">

                        Required for every decision.

                    </small>

                </div>


                <div class="text-end mt-4">

                    <button
                        type="submit"
                        name="submit_review"
                        class="btn btn-primary px-4"
                    >

                        Continue →

                    </button>

                </div>

            </div>

        </div>

    </form>

<?php endif; ?>

<form method="POST">

<?php if (
    $isConsultationReview
    && $consultation['job_status'] === 'Completed'
): ?>

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

        <div class="text-end mt-4">

            <button

                type="submit"
                name="submit_review"
                id="finalReviewBtn"
                class="btn btn-success btn-lg px-4"
                disabled>

                Continue →

            </button>

        </div>

    </div>

</div>

<?php endif; ?>

</form>

<?php if (
    $consultation['workflow_stage'] === 'Needs Admin Review'
    && $consultation['job_status'] !== 'Completed'
    && !$isOverdueConsultationReview
): ?>

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        Administrator Decision

    </div>

    <div class="card-body">

        <p class="text-muted mb-4">

            <?= $isConsultationReview
        ? 'Choose the next action for this consultation.'
        : 'Choose the next action for this customer contact.'; ?>

        </p>

        

        <h4 class="mb-3">Workflow Decisions</h4>

        <?php if ($isConsultationReview): ?>

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

</div>

<?php elseif ($isCustomerContactReview): ?>

    <div class="row g-3">

        <!-- No Answer -->
        <div class="col-lg-3 col-md-6">

            <div class="card h-100 decision-option">

                <div class="card-body">

                    <input
                        class="form-check-input float-end"
                        type="radio"
                        name="decision"
                        value="no-answer">

                    <h5 class="mt-2">
                        📞 No Answer
                    </h5>

                    <small class="text-muted">
                        Contact the customer again.
                    </small>

                </div>

            </div>

        </div>

        <!-- Wrong Number -->
        <div class="col-lg-3 col-md-6">

            <div class="card h-100 decision-option">

                <div class="card-body">

                    <input
                        class="form-check-input float-end"
                        type="radio"
                        name="decision"
                        value="wrong-number">

                    <h5 class="mt-2">
                        ☎️ Wrong Number
                    </h5>

                    <small class="text-muted">
                        Update the customer's contact information.
                    </small>

                </div>

            </div>

        </div>

        <!-- Customer Requested New Consultation -->
        <div class="col-lg-3 col-md-6">

            <div class="card h-100 decision-option">

                <div class="card-body">

                    <input
                        class="form-check-input float-end"
                        type="radio"
                        name="decision"
                        value="new-consultation">

                    <h5 class="mt-2">
                        📅 Customer Requested New Consultation
                    </h5>

                    <small class="text-muted">
                        Schedule a new consultation appointment for the customer.
                    </small>

                </div>

            </div>

        </div>

        <!-- Customer Requested Closure -->
        <div class="col-lg-3 col-md-6">

            <div class="card h-100 decision-option">

                <div class="card-body">

                    <input
                        class="form-check-input float-end"
                        type="radio"
                        name="decision"
                        value="close-request">

                    <h5 class="mt-2">
                        🔒 Customer Requested Closure
                    </h5>

                    <small class="text-muted">
                        Close this request as requested by the customer.
                    </small>

                </div>

            </div>

        </div>

    </div>


<?php endif; ?>

<hr class="my-4">

        <div class="text-end mt-4">

            <button

                id="workflowContinueBtn"
                class="btn btn-success btn-lg px-4"
                disabled>

                Continue →

            </button>

        </div>

    </div>

</div>

<?php endif; ?>

<script>

/*
|--------------------------------------------------------------------------
| Final Review
|--------------------------------------------------------------------------
| Approve Consultation / Return to Agent
|--------------------------------------------------------------------------
*/

const finalReviewButton =
    document.getElementById('finalReviewBtn');

const adminDecisionOptions =
    document.querySelectorAll(
        'input[name="admin_decision"]'
    );


adminDecisionOptions.forEach(function (option) {

    option.addEventListener('change', function () {

        if (finalReviewButton) {

            finalReviewButton.disabled = false;

        }

    });

});


/*
|--------------------------------------------------------------------------
| Other Administrator Workflow Decisions
|--------------------------------------------------------------------------
*/

const workflowOptions =
    document.querySelectorAll('.decision-option');

const workflowButton =
    document.getElementById('workflowContinueBtn');


workflowOptions.forEach(function (option) {

    option.addEventListener('click', function () {

        workflowOptions.forEach(function (item) {

            item.classList.remove(
                'border-primary',
                'shadow',
                'bg-light'
            );

        });


        this.classList.add(
            'border-primary',
            'shadow',
            'bg-light'
        );


        const input =
            this.querySelector('input[name="decision"]');


        if (input) {

            input.checked = true;

        }


        if (workflowButton) {

            workflowButton.disabled = false;

        }

    });

});


if (workflowButton) {

    workflowButton.addEventListener('click', function () {

        const selected =
            document.querySelector(
                'input[name="decision"]:checked'
            );


        if (!selected) {

            return;

        }


        const id =
            <?= (int)$consultation['id']; ?>;


        switch (selected.value) {

            case 'reschedule':

                window.location =
                    '?page=admin-reschedule-consultation&id='
                    + id;

                break;


            case 'reassign':

                window.location =
                    '?page=admin-assign-agent&id='
                    + id;

                break;


            case 'contact':

                window.location =
                    '?page=admin-contact-customer&id='
                    + id;

                break;


            case 'close':

                window.location =
                    '?page=admin-close-request&id='
                    + id;

                break;


            case 'no-answer':

                window.location =
                    '?page=admin-contact-customer&id='
                    + id
                    + '&action=no-answer';

                break;


            case 'wrong-number':

                window.location =
                    '?page=admin-contact-customer&id='
                    + id
                    + '&action=wrong-number';

                break;


            case 'new-consultation':

                window.location =
                    '?page=admin-reschedule-consultation&id='
                    + id;

                break;


            case 'close-request':

                window.location =
                    '?page=admin-close-request&id='
                    + id;

                break;

        }

    });

}

</script>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>