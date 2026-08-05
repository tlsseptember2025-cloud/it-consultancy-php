<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/consultation_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$requestId = (int)($_GET['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT

        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        s.title AS service_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE r.id = ?

    LIMIT 1

");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Check Consultation Booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT

        cb.id AS booking_id,
        cb.agent_id,

        a.name AS agent_name,

        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

    FROM consultation_bookings cb

    INNER JOIN agents a
        ON a.id = cb.agent_id

    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE cb.request_id = ?

    LIMIT 1

");

$stmt->execute([$requestId]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

$isReassignment = $booking ? true : false;

if ($isReassignment) {

    $consultation = array_merge($consultation, $booking);

}

/*
|--------------------------------------------------------------------------
| Prevent Duplicate Initial Assignment
|--------------------------------------------------------------------------
*/

if (
    !$isReassignment &&
    !empty($consultation['agent_id'])
) {

    header('Location: ?page=requests&success=agent-already-assigned');
    exit;

}

/*
|--------------------------------------------------------------------------
| Load Agents
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id,name
    FROM agents
    ORDER BY name
");

$stmt->execute();

$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Handle Reassign Agent
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Handle Reassign Agent
|--------------------------------------------------------------------------
*/

if (isset($_POST['reassign_agent'])) {

    $newAgentId = (int)($_POST['agent_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if ($newAgentId <= 0) {
        die('Please select an agent.');
    }

    if ($newAgentId == $consultation['agent_id']) {
        die('Please choose a different agent.');
    }

    if ($reason === '') {
        die('Please enter a reason.');
    }

    try {

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Get Administrator
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$_SESSION['user']]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new Exception('Administrator not found.');
        }

        $adminId = $admin['id'];

        /*
        |--------------------------------------------------------------------------
        | Save Reassignment History
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO consultation_agent_reassignments
            (
                request_id,
                booking_id,
                old_agent_id,
                new_agent_id,
                reassigned_by,
                reason
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $consultation['id'],
            $consultation['booking_id'],
            $consultation['agent_id'],
            $newAgentId,
            $adminId,
            $reason
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Consultation Booking
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE consultation_bookings
            SET agent_id = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newAgentId,
            $consultation['booking_id']
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Request
        |--------------------------------------------------------------------------
        */

        $adminInstruction = trim($_POST['admin_instruction'] ?? '');

        if ($adminInstruction === '') {
            $adminInstruction = '__RESCHEDULE_ALLOWED__';
}

       $stmt = $pdo->prepare("
    UPDATE requests
    SET
        agent_id = ?,
        workflow_stage = 'Needs Admin Review',

        status = 'Pending',
        job_status = 'Pending',

        admin_instruction = ?,

        completed_at = NULL,
        completion_notes = NULL,
        incomplete_reason = NULL

    WHERE id = ?
");

       $stmt->execute([
    $newAgentId,
    $adminInstruction,
    $consultation['id']
]);

        $pdo->commit();

        header('Location: ?page=requests&success=agent-reassigned');
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();

        die($e->getMessage());

    }

}

/*
|--------------------------------------------------------------------------
| Handle Initial Agent Assignment
|--------------------------------------------------------------------------
*/

if (isset($_POST['assign_agent'])) {

    $agentId = (int)($_POST['agent_id'] ?? 0);

    if ($agentId <= 0) {
        die('Please select an agent.');
    }

    $stmt = $pdo->prepare("
        UPDATE requests
        SET agent_id = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $agentId,
        $consultation['id']
    ]);

    header('Location: ?page=requests&success=agent-assigned');
    exit;
}

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="row mb-4">

        <div class="col-md-8">

            <div class="card-header bg-primary text-white">

                <?= $isReassignment
                    ? 'Reassign Consultation Agent'
                    : 'Assign Consultation Agent'; ?>

            </div>

            <p class="text-muted">

                Request #<?= $consultation['id'] ?>

            </p>

        </div>

    </div>

</div>

<!-- Customer + Service -->


<div class="row mb-4">


    <div class="col-md-6">


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



    <div class="col-md-6">


        <div class="card shadow-sm h-100">


            <div class="card-header">

                Service Information

            </div>


            <div class="card-body">


                <p>
                    <strong>Service:</strong>
                    <?= htmlspecialchars($consultation['service_name']) ?>
                </p>


                <p class="mb-0">
                    <strong>Quoted Price:</strong>
                    AED <?= number_format($consultation['quoted_price'],2) ?>
                </p>


            </div>

        </div>


    </div>


</div>



<?php if ($isReassignment): ?>

<!-- Current Appointment -->


<div class="card shadow-sm mb-4">


    <div class="card-header bg-secondary text-white">

        Current Appointment

    </div>


    <div class="card-body bg-light">


        <div class="row mb-4">


            <div class="col-md-4">

                <strong>Date</strong><br>

                <?= formatDate($consultation['slot_date']) ?>

            </div>



            <div class="col-md-4">

                <strong>Time</strong><br>

                <?= formatTime($consultation['slot_time']) ?>

            </div>



            <div class="col-md-4">

                <strong>Assigned Agent</strong><br>

                <?= htmlspecialchars($consultation['agent_name']) ?>

            </div>


        </div>



        <div class="row">


            <div class="col-md-6">

                <strong>Meeting Method</strong><br>

                <?= !empty($consultation['consultation_method'])

                    ? htmlspecialchars($consultation['consultation_method'])

                    : 'Not Assigned';

                ?>

            </div>



            <div class="col-md-6">

                <strong>Meeting Link</strong><br>


                <?php if (
                        !empty($consultation['meeting_link'])
                        &&
                        shouldShowMeetingLink(
                            $consultation['slot_date'],
                            $consultation['slot_time']
                        )
                    ): ?>


                    <a href="<?= htmlspecialchars($consultation['meeting_link']) ?>"
                       target="_blank"
                       class="btn btn-outline-primary btn-sm mt-2">

                        Join Meeting

                    </a>


                <?php else: ?>


                    <span class="text-muted">

                        Not Available

                    </span>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>

<!-- Consultation Review -->

<div class="card shadow-sm mb-4 border-danger">

    <div class="card-header bg-danger text-white">

        Consultation Review

    </div>


    <div class="card-body">


        <p>

            This consultation requires a new appointment because it could not be completed.

        </p>


        <strong>Reason</strong>


        <div class="border rounded bg-light p-3 mt-2">

            <?= htmlspecialchars($consultation['incomplete_reason']) ?>

        </div>


    </div>

</div>



<!-- Agent Notes -->


<div class="card shadow-sm mb-4">


    <div class="card-header bg-warning">

        Agent Notes

    </div>


    <div class="card-body">


        <?php if (!empty($consultation['completion_notes'])): ?>


            <div class="border rounded bg-light p-3">

                <?= nl2br(htmlspecialchars($consultation['completion_notes'])) ?>

            </div>


        <?php else: ?>


            <span class="text-muted">

                No notes were provided by the assigned agent.

            </span>


        <?php endif; ?>


    </div>


</div>

<?php endif; ?>

<?php if ($isReassignment): ?>

<div class="alert alert-info shadow-sm mb-4">

    <h6 class="mb-2">

        Current Assignment

    </h6>

    <strong>

        <?= htmlspecialchars($consultation['agent_name']) ?>

    </strong>

    <hr>

    <h6 class="mb-0 text-primary">

        ↓ Reassign To

    </h6>

</div>

<?php endif; ?>

<form method="POST">

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            Reassign Consultation Agent

        </div>

        <div class="card-body">

            <div class="mb-3">

                <label class="form-label">

                    <?= $isReassignment
                        ? 'Select New Agent'
                        : 'Select Agent'; ?>

                </label>

                <select
                    class="form-select"
                    name="agent_id"
                    required>

                    <option value="">

                        -- Select Agent --

                    </option>

                    <?php foreach ($agents as $agent): ?>

                        <?php if ($agent['id'] != $consultation['agent_id']): ?>

                            <option value="<?= $agent['id'] ?>">

                                <?= htmlspecialchars($agent['name']) ?>

                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </select>

            </div>

            <?php if ($isReassignment): ?>

                <div class="mb-4">

                    <label class="form-label">

                        Reason for Reassignment

                    </label>

                    <textarea
                        class="form-control"
                        name="reason"
                        rows="4"
                        placeholder="Enter the reason for assigning another agent..."
                        required></textarea>

                </div>

            <?php endif; ?>

            <?php if ($isReassignment): ?>

<div class="mb-4">

    <label class="form-label fw-bold">

        Administrator Instructions (Optional)

    </label>

    <textarea
        name="admin_instruction"
        class="form-control"
        rows="6"
        placeholder="Example: Your consultation has been assigned to another consultant. Please choose a new appointment that suits your availability."></textarea>

    <div class="form-text">

        These instructions will be displayed to the customer before they choose a new consultation date and time.

    </div>

</div>

<?php endif; ?>

            <?php if ($isReassignment): ?>

                <a href="?page=needs-admin-review"
                class="btn btn-secondary">

                    Cancel

                </a>

            <?php endif; ?>

            <button
                type="submit"
                name="<?= $isReassignment ? 'reassign_agent' : 'assign_agent'; ?>"
                class="btn btn-primary">

                <?= $isReassignment
                    ? 'Reassign Agent'
                    : 'Assign Agent'; ?>

            </button>

        </div>

    </div>

</form>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>