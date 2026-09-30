<?php

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

    $_SESSION['error'] =
        'Invalid service request or slot.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Current Service Booking
|--------------------------------------------------------------------------
|
| Demo:
| - Customer belongs to active Demo tenant.
| - Service belongs to active Demo tenant.
| - Assigned Agent belongs to active Demo tenant.
| - Service booking belongs to this request/customer/agent.
|
*/

if ($isDemoCustomer) {

    $stmt = $db->prepare("
        SELECT
            r.service_reschedules,
            r.workflow_stage,
            r.service_rejected_by,
            r.service_rejection_reason,
            r.agent_id,

            sb.slot_id,
            sb.agent_id AS booking_agent_id,

            ss.service_date,
            ss.service_time

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

        INNER JOIN service_bookings sb
            ON sb.request_id = r.id
           AND sb.agent_id = r.agent_id

        INNER JOIN service_slots ss
            ON ss.id = sb.slot_id

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
            r.service_reschedules,
            r.workflow_stage,
            r.service_rejected_by,
            r.service_rejection_reason,
            r.agent_id,

            sb.slot_id,
            sb.agent_id AS booking_agent_id,

            ss.service_date,
            ss.service_time

        FROM requests r

        INNER JOIN service_bookings sb
            ON sb.request_id = r.id
           AND sb.agent_id = r.agent_id

        INNER JOIN service_slots ss
            ON ss.id = sb.slot_id

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

    $_SESSION['error'] =
        'Invalid service booking.';

    header('Location: ?page=customer-requests');
    exit;
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

    $_SESSION['error'] =
        'Invalid service booking assignment.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Reschedule Limit
|--------------------------------------------------------------------------
|
| Allow another appointment selection when:
| - the service was reassigned to another agent, or
| - the administrator rejected the customer's requested reschedule.
|
*/

if (
    (int) $current['service_reschedules'] >= 1
    &&
    empty($current['service_rejected_by'])
    &&
    empty($current['service_rejection_reason'])
) {

    $_SESSION['error'] =
        'You have already used your one allowed service reschedule.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| 24-Hour Rule
|--------------------------------------------------------------------------
*/

$currentDateTime = strtotime(
    $current['service_date']
    . ' '
    . $current['service_time']
);

if (
    $current['workflow_stage'] === 'Service Scheduled'
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

    $_SESSION['error'] =
        'Services can only be rescheduled more than 24 hours before the scheduled time.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent Selecting The Same Slot
|--------------------------------------------------------------------------
*/

if (
    $newSlotId === (int) $current['slot_id']
) {

    $_SESSION['error'] =
        'Please select a different service slot.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify New Slot
|--------------------------------------------------------------------------
|
| service_slots are stored in the selected database.
| Demo therefore uses the Demo database automatically.
|
*/

$stmt = $db->prepare("
    SELECT
        id,
        is_booked,
        service_date,
        service_time
    FROM service_slots
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $newSlotId
]);

$newSlot = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$newSlot) {

    $_SESSION['error'] =
        'Invalid service slot.';

    header('Location: ?page=customer-requests');
    exit;
}


if ((int) $newSlot['is_booked'] === 1) {

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
| Administrator approval is required first.
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

        INNER JOIN service_bookings sb
            ON sb.request_id = r.id
           AND sb.agent_id = r.agent_id

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
| Verify Update
|--------------------------------------------------------------------------
*/

if ($stmt->rowCount() !== 1) {

    $_SESSION['error'] =
        'The service reschedule request could not be saved.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
|
| This is a REQUESTED reschedule.
| The actual service booking is changed only after Admin approval.
|
*/

RequestEventHelper::addCurrentUser(
    $db,
    $requestId,
    'SERVICE_RESCHEDULE_REQUESTED',
    RequestEventHelper::TYPE_SERVICE,
    'Service Reschedule Requested',
    'The customer selected a new service date and time. The request is awaiting administrator approval.',
    true
);


/*
|--------------------------------------------------------------------------
| Return To Customer Requests
|--------------------------------------------------------------------------
*/

$_SESSION['success'] =
    'Your new service time has been submitted and is awaiting administrator approval.';

header('Location: ?page=customer-requests');
exit;