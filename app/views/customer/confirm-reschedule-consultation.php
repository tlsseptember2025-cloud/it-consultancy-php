<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$customerId = (int) $_SESSION['customer']['id'];

$requestId = (int) ($_GET['request_id'] ?? 0);
$newSlotId = (int) ($_GET['slot_id'] ?? 0);

verifyCustomerRequest($pdo, $requestId);

// Load current booking
$stmt = $pdo->prepare("
    SELECT
        r.consultation_reschedules,
        r.workflow_stage,
        r.job_status,
        r.admin_instruction,
        cb.slot_id,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link
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

// Unlimited reschedules when approved by the administrator
$isAdminReschedule = (
    $current['workflow_stage'] === 'Needs Admin Review'
    &&
    (
        $current['admin_instruction'] === '__RESCHEDULE_ALLOWED__'
        ||
        (
            $current['admin_instruction'] !== null
            &&
            trim($current['admin_instruction']) !== ''
        )
    )
);

// Normal customer reschedule: only one allowed
if (
    !$isAdminReschedule
    &&
    (int)$current['consultation_reschedules'] >= 1
) {
    die('You have already used your consultation reschedule.');
}

// Must be more than 24 hours before,
// unless an administrator has already approved the reschedule.

$currentDateTime = strtotime(
    $current['slot_date'] . ' ' . $current['slot_time']
);

if (
    $current['admin_instruction'] !== '__RESCHEDULE_ALLOWED__'
    &&
    time() >= ($currentDateTime - (24 * 60 * 60))
) {

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

/*
|--------------------------------------------------------------------------
| Customer Requested Reschedule
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The customer has selected a new slot, but the administrator
| must approve it before the booking is changed.
|
*/


/*
|--------------------------------------------------------------------------
| Verify New Slot
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        is_booked,
        slot_date,
        slot_time
    FROM consultation_slots
    WHERE id = ?
");

$stmt->execute([
    $newSlotId
]);

$newSlot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$newSlot) {

    die('Invalid consultation slot.');
}

if ((int)$newSlot['is_booked'] === 1) {

    die('Sorry, this slot is no longer available.');
}


/*
|--------------------------------------------------------------------------
| Store Pending Reschedule
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        pending_reschedule_slot_id = ?,
        pending_reschedule_reason = NULL,
        pending_reschedule_requested_at = NOW(),
        workflow_stage = 'Awaiting Reschedule Approval',
        job_status = 'Pending'
    WHERE
        id = ?
        AND customer_id = ?
");

$stmt->execute([
    $newSlotId,
    $requestId,
    $customerId
]);


/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $requestId,
    'CONSULTATION_RESCHEDULE_REQUESTED',
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Reschedule Requested',
    'The customer selected a new consultation date and time. The request is awaiting administrator approval.',
    true
);


/*
|--------------------------------------------------------------------------
| Return to Customer Requests
|--------------------------------------------------------------------------
*/

$_SESSION['success'] =
    'Your new consultation time has been submitted and is awaiting administrator approval.';

header('Location: ?page=customer-requests');
exit;