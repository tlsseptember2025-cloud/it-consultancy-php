<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/security.php';
require_once HELPER_PATH . '/auth.php';

$customerId = (int) $_SESSION['customer']['id'];

$requestId = (int) ($_GET['request_id'] ?? 0);

verifyCustomerRequest($pdo, $requestId);

// Load request + current booking
$stmt = $pdo->prepare("
    SELECT
        r.consultation_reschedules,
        cb.slot_id,
        cs.slot_date,
        cs.slot_time
    FROM requests r
    JOIN consultation_bookings cb
        ON cb.request_id = r.id
    JOIN consultation_slots cs
        ON cs.id = cb.slot_id
    WHERE r.id = ?
      AND r.customer_id = ?
");

$stmt->execute([$requestId, $customerId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['error'] =
    'Invalid request!';

header('Location: ?page=customer-requests');
exit;
}

// Only one reschedule allowed
if ((int)$request['consultation_reschedules'] >= 1) {
    $_SESSION['error'] =
    'You have already used your one allowed consultation reschedule.';

header('Location: ?page=customer-requests');
exit;
}

// Must be more than 24 hours before
$currentConsultation = strtotime(
    $request['slot_date'] . ' ' . $request['slot_time']
);

if (time() >= ($currentConsultation - (24 * 60 * 60))) {
    $_SESSION['error'] =
    'Consultations can only be rescheduled more than 24 hours before the scheduled time.';

header('Location: ?page=customer-requests');
exit;
}

// Show available slots
$stmt = $pdo->query("
    SELECT *
    FROM consultation_slots
    WHERE is_booked = 0
      AND TIMESTAMP(slot_date, slot_time) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
    ORDER BY slot_date, slot_time
");

$slots = $stmt->fetchAll();

$availableDates = [];

foreach ($slots as $slot) {

    if ($slot['id'] == $request['slot_id']) {
        continue;
    }

    if (!isset($availableDates[$slot['slot_date']])) {

        $availableDates[$slot['slot_date']] = true;

    }
}

$selectedDate = $_GET['date'] ?? null;

if (!$selectedDate && !empty($availableDates)) {

    $selectedDate = array_key_first($availableDates);

}

#$selectedDate = $_GET['date'] ?? array_key_first($availableDates);

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="mb-1">

            Reschedule Consultation

        </h2>

        <p class="text-muted mb-0">

            Choose a new consultation appointment.

        </p>

    </div>

    <a
        href="?page=customer-requests"
        class="btn btn-outline-secondary">

        ← Back

    </a>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>Current Consultation</strong>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <p>

                    <strong>Date</strong><br>

                    <?= date('M d, Y', strtotime($request['slot_date'])) ?>

                </p>

            </div>

            <div class="col-md-6">

                <p>

                    <strong>Time</strong><br>

                    <?= date('h:i A', strtotime($request['slot_time'])) ?>

                </p>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>Reason for Rescheduling</strong>

    </div>

    <div class="card-body">

        <div class="alert alert-info mb-3">

            Please tell us why you would like to reschedule your consultation.

        </div>

        <textarea
            id="rescheduleReason"
            class="form-control"
            rows="4"
            maxlength="500"
            placeholder="Enter your reason (optional)..."></textarea>

        <small class="text-muted">

            Maximum 500 characters.

        </small>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <strong>

            Step 1 - Choose a Date

        </strong>

    </div>

    <div class="card-body">

        <div class="row">

            <?php foreach ($availableDates as $date => $dummy): ?>

                <div class="col-md-3 mb-3">

                    <a
    href="?page=reschedule-consultation&request_id=<?= $requestId ?>&date=<?= $date ?>"
    class="btn <?= $selectedDate == $date ? 'btn-primary' : 'btn-outline-primary' ?> w-100">

    <?= date('M d, Y', strtotime($date)) ?>

</a>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<div class="card shadow-sm">

    <div class="card-header">

        <strong>Step 2 - Choose a Time</strong>

    </div>

    <div class="card-body">

        <?php

$displayedSlots = [];

foreach ($slots as $slot):

    if ($slot['slot_date'] != $selectedDate) {
        continue;
    }

    if ($slot['id'] == $request['slot_id']) {
        continue;
    }

    $key = $slot['slot_date'] . '_' . $slot['slot_time'];

    if (isset($displayedSlots[$key])) {
        continue;
    }

    $displayedSlots[$key] = true;

?>

<div class="border rounded p-3 mb-3">

    <div class="row align-items-center">

        <div class="col-md-9">

    <h5 class="mb-0">

        <?= date('h:i A', strtotime($slot['slot_time'])) ?>

    </h5>

</div>

        <div class="col-md-3 text-end">

    <button
        type="button"
        class="btn btn-success select-slot"
        data-slot="<?= $slot['id'] ?>">

        Select Slot

    </button>

</div>

    </div>

</div>

<?php endforeach; ?>

    </div>

</div>

<script>

document.querySelectorAll('.select-slot').forEach(button => {

    button.addEventListener('click', function () {

        const reason = document.getElementById('rescheduleReason').value;

        const slotId = this.dataset.slot;

        window.location =
            '?page=confirm-reschedule-consultation'
            + '&request_id=<?= $requestId ?>'
            + '&slot_id=' + slotId
            + '&reason=' + encodeURIComponent(reason);

    });

});

</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>