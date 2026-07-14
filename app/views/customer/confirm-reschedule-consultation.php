<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';

requireCustomerLogin();

$customerId = (int) $_SESSION['customer']['id'];

$requestId = (int) ($_GET['request_id'] ?? 0);
$newSlotId = (int) ($_GET['slot_id'] ?? 0);

verifyCustomerRequest($pdo, $requestId);

// Load current booking
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

$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    die('Invalid request.');
}

// Only one reschedule allowed
if ((int) $current['consultation_reschedules'] >= 1) {
    die('You have already used your consultation reschedule.');
}

// Must be more than 24 hours before
$currentDateTime = strtotime(
    $current['slot_date'] . ' ' . $current['slot_time']
);

if (time() >= ($currentDateTime - (24 * 60 * 60))) {
    die('Consultations can only be rescheduled more than 24 hours in advance.');
}

// Prevent selecting the same slot
if ($newSlotId == $current['slot_id']) {
    die('Please select a different consultation slot.');
}

// Check that the new slot is still available
$stmt = $pdo->prepare("
    SELECT is_booked
    FROM consultation_slots
    WHERE id = ?
");

$stmt->execute([$newSlotId]);

if ($stmt->fetchColumn()) {
    die('Sorry, this slot is no longer available.');
}

// Free the old slot
$stmt = $pdo->prepare("
    UPDATE consultation_slots
    SET is_booked = 0
    WHERE id = ?
");

$stmt->execute([$current['slot_id']]);

// Book the new slot
$stmt = $pdo->prepare("
    UPDATE consultation_slots
    SET is_booked = 1
    WHERE id = ?
");

$stmt->execute([$newSlotId]);

// Update existing booking
$stmt = $pdo->prepare("
    UPDATE consultation_bookings
    SET slot_id = ?
    WHERE request_id = ?
");

$stmt->execute([$newSlotId, $requestId]);

// Increment reschedule counter
$stmt = $pdo->prepare("
    UPDATE requests
    SET consultation_reschedules = consultation_reschedules + 1
    WHERE id = ?
");

$stmt->execute([$requestId]);

header('Location: ?page=customer-requests');
exit;