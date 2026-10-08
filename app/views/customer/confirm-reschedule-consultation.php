<?php
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/security.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $customerId = (int) (
        $_SESSION['demo_customer']['id'] ?? 0
    );

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if (
        $customerId <= 0 ||
        $demoTenantId <= 0
    ) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Demo Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        SELECT
            id,
            demo_tenant_id,
            is_demo_account
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

    $customer = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$customer) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;

    $customerId = (int) (
        $_SESSION['customer']['id'] ?? 0
    );

    if ($customerId <= 0) {

        unset($_SESSION['customer']);

        header('Location: ?page=public-login');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Request Parameters
|--------------------------------------------------------------------------
*/

$requestId = (int) (
    $_GET['request_id'] ?? 0
);

$newSlotId = (int) (
    $_GET['slot_id'] ?? 0
);

if (
    $requestId <= 0 ||
    $newSlotId <= 0
) {

    die('Invalid consultation request or slot.');
}


/*
|--------------------------------------------------------------------------
| Verify Request Ownership
|--------------------------------------------------------------------------
*/

if (!$isDemoCustomer) {

    verifyCustomerRequest(
        $db,
        $requestId
    );
}


/*
|--------------------------------------------------------------------------
| Load Current Consultation Booking
|--------------------------------------------------------------------------
|
| For Demo:
| - Customer must belong to the active Demo tenant.
| - Service must belong to the active Demo tenant.
| - Assigned Agent must belong to the active Demo tenant.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.consultation_reschedules,
            r.workflow_stage,
            r.job_status,
            r.admin_instruction,
            r.agent_id,

            cb.slot_id,
            cb.agent_id AS booking_agent_id,

            cs.slot_date,
            cs.slot_time,
            cs.consultation_method,
            cs.meeting_link

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?
           

        INNER JOIN agents a
            ON a.id = r.agent_id
           AND a.demo_tenant_id = ?
           AND a.is_demo_account = 1

        INNER JOIN consultation_bookings cb
            ON cb.request_id = r.id

        INNER JOIN consultation_slots cs
            ON cs.id = cb.slot_id

        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $demoTenantId,
        $demoTenantId,
        $demoTenantId,
        $requestId,
        $customerId
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            r.consultation_reschedules,
            r.workflow_stage,
            r.job_status,
            r.admin_instruction,
            r.agent_id,

            cb.slot_id,
            cb.agent_id AS booking_agent_id,

            cs.slot_date,
            cs.slot_time,
            cs.consultation_method,
            cs.meeting_link

        FROM requests r

        INNER JOIN consultation_bookings cb
            ON cb.request_id = r.id

        INNER JOIN consultation_slots cs
            ON cs.id = cb.slot_id

        WHERE r.id = ?
          AND r.customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}

$current = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$current) {

    die('Invalid request.');
}


$assignedAgentId = (int) (
    $current['agent_id'] ?? 0
);

$bookingAgentId = (int) (
    $current['booking_agent_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Verify Booking Agent Matches Request Agent
|--------------------------------------------------------------------------
*/

if (
    $assignedAgentId <= 0 ||
    $bookingAgentId <= 0 ||
    $assignedAgentId !== $bookingAgentId
) {

    die('Invalid consultation booking.');
}


/*
|--------------------------------------------------------------------------
| Unlimited Reschedules When Approved By Administrator
|--------------------------------------------------------------------------
*/

$isAdminReschedule = (
    $current['workflow_stage']
        === 'Awaiting Customer Reschedule'
    ||
    $current['admin_instruction']
        === '__RESCHEDULE_ALLOWED__'
);


/*
|--------------------------------------------------------------------------
| Normal Customer Reschedule:
| Only One Allowed
|--------------------------------------------------------------------------
*/

if (
    !$isAdminReschedule
    &&
    (int) $current['consultation_reschedules'] >= 1
) {

    die(
        'You have already used your consultation reschedule.'
    );
}


/*
|--------------------------------------------------------------------------
| Must Be More Than 24 Hours Before
|--------------------------------------------------------------------------
|
| Unless an administrator has already approved
| the reschedule.
|
*/

$currentDateTime = strtotime(
    $current['slot_date']
    . ' '
    . $current['slot_time']
);

if (
    !$isAdminReschedule
    &&
    (
        $currentDateTime === false
        ||
        time() >= (
            $currentDateTime
            - (24 * 60 * 60)
        )
    )
) {

    die(
        'Consultations can only be rescheduled more than 24 hours in advance.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Selecting The Same Slot
|--------------------------------------------------------------------------
*/

if ($newSlotId === (int) $current['slot_id']) {

    die(
        'Please select a different consultation slot.'
    );
}


/*
|--------------------------------------------------------------------------
| Verify New Slot
|--------------------------------------------------------------------------
|
| consultation_slots does not contain Demo tenant fields.
| Therefore the slot is isolated through its assigned Agent.
|
*/

$stmt = $db->prepare("
    SELECT
        id,
        agent_id,
        is_booked,
        slot_date,
        slot_time
    FROM consultation_slots
    WHERE id = ?
      AND agent_id = ?
    LIMIT 1
");

$stmt->execute([
    $newSlotId,
    $assignedAgentId
]);

$newSlot = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$newSlot) {

    die(
        'Invalid consultation slot.'
    );
}


if ((int) $newSlot['is_booked'] === 1) {

    die(
        'Sorry, this slot is no longer available.'
    );
}


/*
|--------------------------------------------------------------------------
| Store Pending Reschedule
|--------------------------------------------------------------------------
|
| The customer selects a new slot.
| The administrator must approve it before
| the booking itself is changed.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        UPDATE requests r

        INNER JOIN customers c
            ON c.id = r.customer_id
           AND c.demo_tenant_id = ?
           AND c.is_demo_account = 1

        INNER JOIN services s
            ON s.id = r.service_id
           AND s.demo_tenant_id = ?
           

        INNER JOIN agents a
            ON a.id = r.agent_id
           AND a.demo_tenant_id = ?
           AND a.is_demo_account = 1

        SET
            r.pending_reschedule_slot_id = ?,
            r.pending_reschedule_reason = NULL,
            r.pending_reschedule_requested_at = NOW(),
            r.workflow_stage = 'Awaiting Reschedule Approval',
            r.job_status = 'Pending'

        WHERE r.id = ?
          AND r.customer_id = ?
          AND r.agent_id = ?
    ");

    $stmt->execute([
        $demoTenantId,
        $demoTenantId,
        $demoTenantId,
        $newSlotId,
        $requestId,
        $customerId,
        $assignedAgentId
    ]);

} else {

    $stmt = $db->prepare("
        UPDATE requests
        SET
            pending_reschedule_slot_id = ?,
            pending_reschedule_reason = NULL,
            pending_reschedule_requested_at = NOW(),
            workflow_stage = 'Awaiting Reschedule Approval',
            job_status = 'Pending'
        WHERE id = ?
          AND customer_id = ?
          AND agent_id = ?
    ");

    $stmt->execute([
        $newSlotId,
        $requestId,
        $customerId,
        $assignedAgentId
    ]);
}


/*
|--------------------------------------------------------------------------
| Verify Request Was Actually Updated
|--------------------------------------------------------------------------
*/

if ($stmt->rowCount() !== 1) {

    die(
        'The consultation reschedule request could not be saved.'
    );
}


/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $db,
    $requestId,
    'CONSULTATION_RESCHEDULE_REQUESTED',
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Reschedule Requested',
    'The customer selected a new consultation date and time. The request is awaiting administrator approval.',
    true
);


/*
|--------------------------------------------------------------------------
| Return To Customer Requests
|--------------------------------------------------------------------------
*/

$_SESSION['success'] =
    'Your new consultation time has been submitted and is awaiting administrator approval.';

header(
    'Location: ?page=customer-requests'
);

exit;