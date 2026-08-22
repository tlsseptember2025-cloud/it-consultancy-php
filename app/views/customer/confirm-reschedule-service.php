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
        r.service_reschedules,
        r.service_rejected_by,
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

// Only one normal customer-initiated reschedule allowed.
// Administrator-triggered reassignment/rescheduling is allowed.

if (
    (int) $current['service_reschedules'] >= 1
    && empty($current['service_rejected_by'])
) {

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

/*
|--------------------------------------------------------------------------
| Store Pending Service Reschedule
|--------------------------------------------------------------------------
|
| The customer has selected a new service slot.
| The appointment is NOT changed yet.
| The administrator must approve the requested slot first.
|
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
    'SERVICE_RESCHEDULE_REQUESTED',
    RequestEventHelper::TYPE_SERVICE,
    'Service Reschedule Requested',
    'The customer selected a new service date and time. The request is awaiting administrator approval.',
    true
);


/*
|--------------------------------------------------------------------------
| Return to Customer Requests
|--------------------------------------------------------------------------
*/

$_SESSION['success'] =
    'Your new service time has been submitted and is awaiting administrator approval.';

header('Location: ?page=customer-requests');
exit;

/*
|--------------------------------------------------------------------------
| Record Service Rescheduled Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    $requestId,
    RequestEventHelper::EVENT_SERVICE_RESCHEDULED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Rescheduled',
    'The customer successfully rescheduled the service appointment.',
    true
);

$_SESSION['success'] =
    'Your service has been successfully rescheduled.';

header('Location: ?page=customer-requests');
exit;