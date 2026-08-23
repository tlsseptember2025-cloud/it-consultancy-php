<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/security.php';

$requestId = $_GET['request_id'] ?? 0;

verifyCustomerRequest($pdo, $requestId);

$stmt = $pdo->prepare("
    SELECT
    workflow_stage,
    service_reschedules,
    service_rejection_reason,
    service_rejected_by
FROM requests
WHERE id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch();

if (!$request) {
    die('Request not found.');
}

if ($request['workflow_stage'] !== 'Service Rejected') {

    header('Location: ?page=customer-requests');
    exit;
}

/*
|--------------------------------------------------------------------------
| Customer Reschedule Limit
|--------------------------------------------------------------------------
|
| Do not apply the customer reschedule limit when the administrator
| reassigned the service and the customer must select a new slot.
|
*/

/*
|--------------------------------------------------------------------------
| Customer Reschedule Limit
|--------------------------------------------------------------------------
|
| Allow the customer to choose another appointment when:
|
| 1. The service was reassigned to another agent, or
| 2. The administrator rejected the customer's previously requested
|    reschedule appointment.
|
*/

if (
    empty($request['service_rejected_by'])
    && empty($request['service_rejection_reason'])
    && $request['service_reschedules'] >= 1
) {

    die('You have already used your service reschedule.');

}

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

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<?php if (!empty($request['service_rejected_by'])): ?>

    <div class="alert alert-warning">

        <h5 class="mb-2">
            Service Rescheduling Required
        </h5>

        <p class="mb-0">

            Your service has been reassigned to another agent
            and needs to be scheduled again. Please select a new
            date and time.

        </p>

    </div>

<?php else: ?>

    <div class="alert alert-warning">

        <h5 class="mb-2">
            Service Rescheduling Required
        </h5>

        <p class="mb-0">
            Your previous service appointment could not be completed.
            Please select a new date and time to reschedule your service.
        </p>

    </div>

<?php endif; ?>

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
