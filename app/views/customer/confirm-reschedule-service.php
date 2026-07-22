<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';


$customerId = (int) $_SESSION['customer']['id'];

$requestId = (int) ($_GET['request_id'] ?? 0);
$newSlotId = (int) ($_GET['slot_id'] ?? 0);

verifyCustomerRequest($pdo, $requestId);

// Load current booking
$stmt = $pdo->prepare("
    SELECT
        r.service_reschedules,
        sb.slot_id,
        ss.service_date,
        ss.service_time
    FROM requests r
    JOIN service_bookings sb
        ON sb.request_id = r.id
    JOIN service_slots ss
        ON ss.id = sb.slot_id
    WHERE r.id = ?
      AND r.customer_id = ?
");

$stmt->execute([$requestId, $customerId]);

$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {

    $_SESSION['error'] = 'Invalid service booking.';
    header('Location: ?page=customer-requests');
    exit;

}

// Only one reschedule allowed
if ((int) $current['service_reschedules'] >= 1) {

    $_SESSION['error'] =
        'You have already used your one allowed service reschedule.';

    header('Location: ?page=customer-requests');
    exit;

}

// Must be more than 24 hours before
$currentDateTime = strtotime(
    $current['service_date'] . ' ' .
    $current['service_time']
);

if (
    time() >=
    ($currentDateTime - (24 * 60 * 60))
) {

    $_SESSION['error'] =
        'Services can only be rescheduled more than 24 hours before the scheduled time.';

    header('Location: ?page=customer-requests');
    exit;

}

// Prevent selecting the same slot
if ($newSlotId == $current['slot_id']) {

    $_SESSION['error'] =
        'Please select a different service slot.';

    header('Location: ?page=customer-requests');
    exit;

}

// Check new slot availability
$stmt = $pdo->prepare("
    SELECT is_booked
    FROM service_slots
    WHERE id = ?
");

$stmt->execute([$newSlotId]);

if ($stmt->fetchColumn()) {

    $_SESSION['error'] =
        'Sorry, this service slot is no longer available.';

    header('Location: ?page=customer-requests');
    exit;

}

// Release old slot
$stmt = $pdo->prepare("
    UPDATE service_slots
    SET is_booked = 0
    WHERE id = ?
");

$stmt->execute([$current['slot_id']]);

// Book new slot
$stmt = $pdo->prepare("
    UPDATE service_slots
    SET is_booked = 1
    WHERE id = ?
");

$stmt->execute([$newSlotId]);

// Update booking
$stmt = $pdo->prepare("
    UPDATE service_bookings
    SET slot_id = ?
    WHERE request_id = ?
");

$stmt->execute([
    $newSlotId,
    $requestId
]);

// Increment counter and reset workflow
$stmt = $pdo->prepare("
    UPDATE requests
    SET
        service_reschedules = service_reschedules + 1,
        workflow_stage = 'Service Scheduled',
        service_rejection_reason = NULL,
        service_rejected_at = NULL,
        service_rejected_by = NULL
    WHERE id = ?
");

$stmt->execute([$requestId]);

$_SESSION['success'] =
    'Your service has been successfully rescheduled.';

header('Location: ?page=customer-requests');
exit;