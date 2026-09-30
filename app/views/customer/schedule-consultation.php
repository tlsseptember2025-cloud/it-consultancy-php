<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';


/*
|--------------------------------------------------------------------------
| Determine Customer Environment
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);
$isMainCustomer = isset($_SESSION['customer']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainCustomer && !$isDemoCustomer) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerPdo = $demoPdo;

    $customerId = (int) $_SESSION['demo_customer']['id'];

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Demo Customer
    |--------------------------------------------------------------------------
    */

    $customerCheckStmt = $customerPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
          AND demo_tenant_id = ?
        LIMIT 1
    ");

    $customerCheckStmt->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$customerCheckStmt->fetch(PDO::FETCH_ASSOC)) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int) ($_GET['request_id'] ?? 0);

if ($requestId <= 0) {

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Request + Assigned Agent
|--------------------------------------------------------------------------
|
| Demo:
| - authenticated Demo customer
| - Demo tenant
| - Demo service
| - assigned Demo Agent
|
| Normal:
| - authenticated customer
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT
            r.id,
            r.agent_id
        FROM requests r
        JOIN customers c
            ON c.id = r.customer_id
        JOIN services s
            ON s.id = r.service_id
        JOIN agents a
            ON a.id = r.agent_id
        WHERE r.id = ?
          AND r.customer_id = ?
          AND c.is_demo_account = 1
          AND c.demo_tenant_id = ?
          AND s.is_demo_account = 1
          AND s.demo_tenant_id = c.demo_tenant_id
          AND a.is_demo_account = 1
          AND a.demo_tenant_id = c.demo_tenant_id
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerPdo->prepare("
        SELECT
            id,
            agent_id
        FROM requests
        WHERE id = ?
          AND customer_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $requestId,
        $customerId
    ]);
}


$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    $_SESSION['error'] =
        'You are not authorized to access this request.';

    header('Location: ?page=customer-requests');
    exit;
}


$assignedAgentId = (int) $request['agent_id'];

if ($assignedAgentId <= 0) {

    $_SESSION['error'] =
        'No consultation agent is currently assigned to this request.';

    header('Location: ?page=customer-requests');
    exit;
}


$selectedDate = $_GET['date'] ?? '';


/*
|--------------------------------------------------------------------------
| Check Existing Consultation Booking
|--------------------------------------------------------------------------
|
| Do this before displaying available slots.
|
*/

$bookingStmt = $customerPdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings cb
    INNER JOIN requests r
        ON r.id = cb.request_id
    WHERE cb.request_id = ?
      AND r.customer_id = ?
");

$bookingStmt->execute([
    $requestId,
    $customerId
]);

$alreadyBooked = (int) $bookingStmt->fetchColumn() > 0;


/*
|--------------------------------------------------------------------------
| Available Dates
|--------------------------------------------------------------------------
|
| consultation_slots has no Demo tenant columns.
|
| Demo isolation is therefore enforced through the assigned Demo Agent.
|
*/

if ($isDemoCustomer) {

    $stmt = $customerPdo->prepare("
        SELECT DISTINCT
            cs.slot_date
        FROM consultation_slots cs
        JOIN agents a
            ON a.id = cs.agent_id
        WHERE cs.agent_id = ?
          AND cs.is_booked = 0
          AND TIMESTAMP(
                cs.slot_date,
                cs.slot_time
              ) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
          AND a.is_demo_account = 1
          AND a.demo_tenant_id = ?
        ORDER BY cs.slot_date
    ");

    $stmt->execute([
        $assignedAgentId,
        $demoTenantId
    ]);

} else {

    $stmt = $customerPdo->prepare("
        SELECT DISTINCT
            slot_date
        FROM consultation_slots
        WHERE agent_id = ?
          AND is_booked = 0
          AND TIMESTAMP(
                slot_date,
                slot_time
              ) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
        ORDER BY slot_date
    ");

    $stmt->execute([
        $assignedAgentId
    ]);
}


$availableDates = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Available Times For Selected Date
|--------------------------------------------------------------------------
*/

$slots = [];

if (!empty($selectedDate) && !$alreadyBooked) {

    if ($isDemoCustomer) {

        $stmt = $customerPdo->prepare("
            SELECT
                cs.id,
                cs.slot_date,
                cs.slot_time
            FROM consultation_slots cs
            JOIN agents a
                ON a.id = cs.agent_id
            WHERE cs.agent_id = ?
              AND cs.slot_date = ?
              AND cs.is_booked = 0
              AND TIMESTAMP(
                    cs.slot_date,
                    cs.slot_time
                  ) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)
              AND a.is_demo_account = 1
              AND a.demo_tenant_id = ?

              AND NOT EXISTS (
                  SELECT 1
                  FROM consultation_bookings cb
                  INNER JOIN consultation_slots booked_slot
                      ON booked_slot.id = cb.slot_id
                  WHERE booked_slot.agent_id = ?
                    AND booked_slot.slot_date = cs.slot_date
                    AND ABS(
                        TIME_TO_SEC(
                            TIMEDIFF(
                                booked_slot.slot_time,
                                cs.slot_time
                            )
                        )
                    ) <= 1800
              )

            ORDER BY cs.slot_time
        ");

        $stmt->execute([
            $assignedAgentId,
            $selectedDate,
            $demoTenantId,
            $assignedAgentId
        ]);

    } else {

        $stmt = $customerPdo->prepare("
            SELECT
                cs.id,
                cs.slot_date,
                cs.slot_time
            FROM consultation_slots cs
            WHERE cs.agent_id = ?
              AND cs.slot_date = ?
              AND cs.is_booked = 0
              AND TIMESTAMP(
                    cs.slot_date,
                    cs.slot_time
                  ) >= DATE_ADD(NOW(), INTERVAL 48 HOUR)

              AND NOT EXISTS (
                  SELECT 1
                  FROM consultation_bookings cb
                  INNER JOIN consultation_slots booked_slot
                      ON booked_slot.id = cb.slot_id
                  WHERE booked_slot.agent_id = ?
                    AND booked_slot.slot_date = cs.slot_date
                    AND ABS(
                        TIME_TO_SEC(
                            TIMEDIFF(
                                booked_slot.slot_time,
                                cs.slot_time
                            )
                        )
                    ) <= 1800
              )

            ORDER BY cs.slot_time
        ");

        $stmt->execute([
            $assignedAgentId,
            $selectedDate,
            $assignedAgentId
        ]);
    }

    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


require dirname(__DIR__) . '/layouts/header-customer.php';


if ($alreadyBooked) {

    echo '
    <div class="alert alert-info">
        You have already scheduled your consultation for this request.
    </div>
    ';

    require dirname(__DIR__) . '/layouts/footer.php';
    exit;
}

?>


<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Schedule Consultation
        </h2>


        <form method="GET" class="mb-4">

            <input
                type="hidden"
                name="page"
                value="schedule-consultation">

            <input
                type="hidden"
                name="request_id"
                value="<?= $requestId ?>">

            <label class="form-label">
                Select Consultation Date
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
                        value="<?= htmlspecialchars(
                            $date['slot_date'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $selectedDate === $date['slot_date']
                            ? 'selected'
                            : '' ?>>

                        <?= date(
                            'M d, Y',
                            strtotime($date['slot_date'])
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>


        <?php if (!empty($selectedDate)): ?>

            <?php if (count($slots) > 0): ?>

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>Time</th>

                            <th width="180">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($slots as $slot): ?>

                            <tr>

                                <td>

                                    <?= formatTime(
                                        $slot['slot_time']
                                    ) ?>

                                </td>

                                <td>

                                    <a
                                        href="?page=confirm-consultation&request_id=<?= $requestId ?>&slot_id=<?= (int) $slot['id'] ?>"
                                        class="btn btn-success btn-sm">

                                        Book

                                    </a>

                                    <a
                                        href="?page=customer-requests"
                                        class="btn btn-secondary btn-sm">

                                        Cancel

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="alert alert-warning">

                    No consultation slots are available for the selected date.

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>