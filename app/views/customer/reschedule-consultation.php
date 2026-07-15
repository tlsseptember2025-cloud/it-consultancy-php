<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=customer-login');
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

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<h2 class="mb-4">Reschedule Consultation</h2>

<table class="table table-bordered">

    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>

    <?php

$displayedSlots = [];

foreach ($slots as $slot):

    // Don't show the customer's current booking
if ($slot['id'] == $request['slot_id']) {
    continue;
}

    $key = $slot['slot_date'] . '_' . $slot['slot_time'];

    if (isset($displayedSlots[$key])) {
        continue;
    }

    $displayedSlots[$key] = true;

?>

        <tr>

            <td><?= date('M d, Y', strtotime($slot['slot_date'])) ?></td>

            <td><?= date('h:i A', strtotime($slot['slot_time'])) ?></td>

            <td>

                <a
                    href="?page=confirm-reschedule-consultation&request_id=<?= $requestId ?>&slot_id=<?= $slot['id'] ?>"
                    class="btn btn-success btn-sm">

                    Select

                </a>

            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>