<?php

require_once APP_PATH . '/helpers/meeting.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;

}

require_once CONFIG_PATH . '/database.php';


$requestId = (int)($_GET['id'] ?? 0);

if (isset($_POST['confirm_reschedule'])) {

    $slotStmt = $pdo->prepare("
    SELECT
        slot_time
    FROM consultation_slots
    WHERE id = ?
");

$slotStmt->execute([
    $_POST['slot_id']
]);

$slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

if (!$slot) {

    die('Selected consultation slot was not found.');

}

$meetingLink = getMeetingLink(

    $_POST['consultation_method'],

    $slot['slot_time']

);

echo $meetingLink;

exit;

}


// Load consultation

$stmt = $pdo->prepare("

    SELECT

        r.*,

        c.name AS customer_name,
        c.email,
        c.phone,

        a.name AS agent_name,

        s.title AS service_name,

        cs.id AS slot_id,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link

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

    WHERE r.id = ?

    LIMIT 1

");


$stmt->execute([$requestId]);


$consultation = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$consultation) {

    die('Consultation not found.');

}


// Load agents

$agentStmt = $pdo->query("

    SELECT id, name

    FROM agents

    ORDER BY name

");


$agents = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedDate = $_GET['date'] ?? '';


/*
|--------------------------------------------------------------------------
| Available Consultation Dates
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT DISTINCT slot_date
    FROM consultation_slots
    WHERE is_booked = 0
      AND TIMESTAMP(slot_date, slot_time) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
    ORDER BY slot_date
");

$availableDates = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Available Time Slots
|--------------------------------------------------------------------------
*/

$slots = [];

if (!empty($selectedDate)) {

    $stmt = $pdo->prepare("
        SELECT
            MIN(id) AS id,
            slot_date,
            slot_time
        FROM consultation_slots
        WHERE slot_date = ?
          AND is_booked = 0
        GROUP BY slot_date, slot_time
        ORDER BY slot_time
    ");

    $stmt->execute([$selectedDate]);

    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

require VIEW_PATH . '/layouts/header-admin.php';

?>


<div class="container py-4">


<!-- Header -->

<div class="row mb-4">

    <div class="col-md-8">

        <h2>

            Manage Consultation Reschedule

        </h2>

        <p class="text-muted">

            Request #<?= $consultation['id'] ?>

        </p>

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





<!-- Current Appointment -->


<div class="card shadow-sm mb-4">


    <div class="card-header bg-secondary text-white">

        Current Appointment

    </div>


    <div class="card-body bg-light">


        <div class="row mb-4">


            <div class="col-md-4">

                <strong>Date</strong><br>

                <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

            </div>



            <div class="col-md-4">

                <strong>Time</strong><br>

                <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

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


                <?php if (!empty($consultation['meeting_link'])): ?>


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


<div class="alert alert-info shadow-sm mb-4">

    <h6 class="mb-2">

        Current Appointment

    </h6>

    <strong>

        <?= date('d M Y', strtotime($consultation['slot_date'])) ?>

        &nbsp;&bull;&nbsp;

        <?= date('h:i A', strtotime($consultation['slot_time'])) ?>

    </strong>

    <hr>

    <h6 class="mb-0 text-success">

        ↓ Rescheduling To

    </h6>

</div>

<form method="POST">

    <input
        type="hidden"
        name="request_id"
        value="<?= $consultation['id'] ?>">


<!-- New Appointment -->

<div class="card shadow-sm mb-4">


    <div class="card-header bg-success text-white">

        Reschedule Consultation

    </div>


    <div class="card-body">


        <p class="text-muted">

            Choose a new consultation schedule.

        </p>



        <div class="row">


            <div class="col-md-6 mb-3">


                <label class="form-label fw-bold">

                    Step 1 – Select Consultation Date

                </label>


                <form method="GET">

    <input
        type="hidden"
        name="page"
        value="admin-reschedule-consultation">

    <input
        type="hidden"
        name="id"
        value="<?= $consultation['id'] ?>">

    <select
        name="date"
        class="form-select"
        onchange="this.form.submit()">

        <option value="">

            -- Choose a Date --

        </option>

        <?php foreach ($availableDates as $date): ?>

            <option
                value="<?= $date['slot_date'] ?>"
                <?= $selectedDate == $date['slot_date'] ? 'selected' : '' ?>>

                <?= date('d M Y', strtotime($date['slot_date'])) ?>

            </option>

        <?php endforeach; ?>

    </select>


            </div>




           <div class="col-md-6 mb-3">

    <label class="form-label fw-bold">

        Step 2 – Select Available Time Slot

    </label>

    <?php if (empty($selectedDate)): ?>

        <div class="alert alert-light border mb-0">

            Select a consultation date first.

        </div>

    <?php elseif (empty($slots)): ?>

        <div class="alert alert-warning mb-0">

            No available consultation slots for this date.

        </div>

    <?php else: ?>

        <select
            class="form-select"
            name="slot_id">

            <option value="">

                -- Select Available Time --

            </option>

            <?php foreach ($slots as $slot): ?>

                <option value="<?= $slot['id'] ?>">

                    <?= date('h:i A', strtotime($slot['slot_time'])) ?>

                </option>

            <?php endforeach; ?>

        </select>

    <?php endif; ?>

</div>


        </div>




        <div class="row">


            <div class="col-md-6 mb-3">


                <label class="form-label">

                    Step 3 – Assign Agent

                </label>


                <select

                    class="form-select"

                    name="agent_id">


                    <?php foreach ($agents as $agent): ?>


                        <option

                            value="<?= $agent['id'] ?>"

                            <?= $agent['id'] == $consultation['agent_id'] ? 'selected' : '' ?>>


                            <?= htmlspecialchars($agent['name']) ?>


                        </option>


                    <?php endforeach; ?>


                </select>


            </div>




            <div class="col-md-6 mb-3">


                <label class="form-label">

                    Step 4 – Meeting Method

                </label>


                <select

                    class="form-select"

                    name="consultation_method">


                    <option value="">

                        Select Meeting Method

                    </option>


                    <option value="Google Meet">

                        Google Meet

                    </option>


                    <option value="Microsoft Teams">

                        Microsoft Teams

                    </option>


                    <option value="Zoom">

                        Zoom

                    </option>


                </select>


            </div>

            <div class="mb-3">

    <label class="form-label">

        Step 5 – Reschedule Notes

    </label>

    <textarea
        class="form-control"
        name="admin_notes"
        rows="4"
        placeholder="Internal notes about this reschedule..."></textarea>

</div>

<div class="text-end">

    <a href="?page=needs-admin-review"
       class="btn btn-secondary">

        Cancel

    </a>

    <button
    type="submit"
    name="confirm_reschedule"
    class="btn btn-success">

    Confirm Reschedule

</button>

</form>

</div>


        </div>


    </div>


</div>


<?php require VIEW_PATH . '/layouts/footer.php'; ?>