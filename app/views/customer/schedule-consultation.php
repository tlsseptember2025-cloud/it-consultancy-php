<?php

if (!isset($_SESSION['customer'])) {
    header('Location: ?page=public-login');
    exit;
}

$requestId = (int)($_GET['request_id'] ?? 0);

$customerId = $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT id
    FROM requests
    WHERE id = ?
      AND customer_id = ?
");

$stmt->execute([
    $requestId,
    $customerId
]);

if (!$stmt->fetch()) {

    $_SESSION['error'] = 'You are not authorized to access this request.';
header('Location: ?page=customer-requests');
exit;

}

$selectedDate = $_GET['date'] ?? '';

/*
|--------------------------------------------------------------------------
| Get Available Dates (48+ hours only)
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
| Get Available Times For Selected Date
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

                                    <?= date(
                                        'h:i A',
                                        strtotime($slot['slot_time'])
                                    ) ?>

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