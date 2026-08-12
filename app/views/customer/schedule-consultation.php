<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['customer'])) {
    header('Location: ?page=public-login');
    exit;
}

$requestId = (int)($_GET['request_id'] ?? 0);

$customerId = $_SESSION['customer']['id'];


$stmt = $pdo->prepare("
    SELECT
        id,
        agent_id
    FROM requests
    WHERE id = ?
      AND customer_id = ?
");


$stmt->execute([
    $requestId,
    $customerId
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    $_SESSION['error'] = 'You are not authorized to access this request.';
    header('Location: ?page=customer-requests');
    exit;

}

$assignedAgentId = $request['agent_id'];
$selectedDate = $_GET['date'] ?? '';

/*
|--------------------------------------------------------------------------
| Get Available Dates (48+ hours only)
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT DISTINCT slot_date
    FROM consultation_slots
    WHERE agent_id = ?
      AND is_booked = 0
      AND TIMESTAMP(slot_date, slot_time) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
    ORDER BY slot_date
");

$stmt->execute([
    $assignedAgentId
]);

$availableDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Get Available Times For Selected Date
|--------------------------------------------------------------------------
*/

$slots = [];

if (!empty($selectedDate)) {

    $stmt = $pdo->prepare("
    SELECT
        cs.id,
        cs.slot_date,
        cs.slot_time
    FROM consultation_slots cs
    WHERE cs.agent_id = ?
      AND cs.slot_date = ?
      AND cs.is_booked = 0

      AND NOT EXISTS (
          SELECT 1
          FROM consultation_bookings cb
          INNER JOIN consultation_slots booked_slot
              ON booked_slot.id = cb.slot_id
          WHERE booked_slot.agent_id = ?
            AND booked_slot.slot_date = cs.slot_date
            AND ABS(
                TIME_TO_SEC(
                    TIMEDIFF(booked_slot.slot_time, cs.slot_time)
                )
            ) <= 1800
      )

    ORDER BY cs.slot_time
");

$stmt->execute([
    $assignedAgentId,
    $selectedDate,
    $assignedAgentId
]);

$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

require dirname(__DIR__) . '/layouts/header-customer.php';


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings
    WHERE request_id = ?
");

$stmt->execute([$requestId]);

$alreadyBooked = $stmt->fetchColumn() > 0;

if ($alreadyBooked) {

    echo '
    <div class="alert alert-info">
        You have already scheduled your consultation for this request.
    </div>
    ';

    require dirname(__DIR__) . '/layouts/footer.php';
    exit;
}


?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Schedule Consultation
        </h2>

        <form method="GET" class="mb-4">

            <input
                type="hidden"
                name="page"
                value="schedule-consultation">

            <input
                type="hidden"
                name="request_id"
                value="<?= $requestId ?>">

            <label class="form-label">
                Select Consultation Date
            </label>

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
                        <?= $selectedDate === $date['slot_date'] ? 'selected' : '' ?>>

                        <?= date('M d, Y', strtotime($date['slot_date'])) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

        <?php if (!empty($selectedDate)): ?>

            <?php if (count($slots) > 0): ?>

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>Time</th>
                            <th width="180">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($slots as $slot): ?>

                            <tr>

                                <td>

                                    <?= formatTime($slot['slot_time']) ?>

                                </td>

                                <td>

                                    <a
                                        href="?page=confirm-consultation&request_id=<?= $requestId ?>&slot_id=<?= $slot['id'] ?>"
                                        class="btn btn-success btn-sm">

                                        Book

                                    </a>

                                    <a
                                        href="?page=customer-requests"
                                        class="btn btn-secondary btn-sm">

                                        Cancel

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="alert alert-warning">

                    No consultation slots are available for the selected date.

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>