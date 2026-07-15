<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
    exit;
}

require_once HELPER_PATH . '/security.php';

$requestId = $_GET['request_id'] ?? 0;

verifyCustomerRequest($pdo, $requestId);

$selectedDate = $_GET['date'] ?? '';

$dateStmt = $pdo->query("
    SELECT DISTINCT service_date
    FROM service_slots
    WHERE is_booked = 0
      AND TIMESTAMP(service_date, service_time) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
    ORDER BY service_date
");

$availableDates = $dateStmt->fetchAll(PDO::FETCH_ASSOC);

$slots = [];

if (!empty($selectedDate)) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM service_slots
        WHERE is_booked = 0
          AND service_date = ?
          AND TIMESTAMP(service_date, service_time) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
        ORDER BY service_time
    ");

    $stmt->execute([$selectedDate]);

    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

      <h2 class="mb-4">
    Reschedule Service
</h2>

        <form method="GET" class="mb-3">

            <input
                type="hidden"
                name="page"
                value="reschedule-service">

            <input
                type="hidden"
                name="request_id"
                value="<?= $requestId ?>">

            <label class="form-label">
                Select Service Date
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
                        value="<?= $date['service_date'] ?>"
                        <?= $selectedDate === $date['service_date'] ? 'selected' : '' ?>>

                        <?= date('M d, Y', strtotime($date['service_date'])) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

        <?php if (!empty($selectedDate)): ?>

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>Time</th>
                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php $displayedSlots = []; ?>

                    <?php foreach ($slots as $slot): ?>


                        <?php

                        $key = $slot['service_date'] . '_' . $slot['service_time'];

                        if (isset($displayedSlots[$key])) {
                            continue;
                        }

                        $displayedSlots[$key] = true;

                        ?>

                        <tr>

                            <td>

                                <?= date(
                                    'h:i A',
                                    strtotime($slot['service_time'])
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="?page=confirm-reschedule-service&request_id=<?= $requestId ?>&slot_id=<?= $slot['id'] ?>"
                                    class="btn btn-success btn-sm">

                                    Book

                                </a>

                                <a
                                    href="?page=customer-requests"
                                    class="btn btn-secondary ms-2">

                                    Cancel

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
