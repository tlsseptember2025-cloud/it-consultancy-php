<?php

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
            r.service_id,
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
            service_id,
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
        'No service agent is currently assigned to this request.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Selected Date
|--------------------------------------------------------------------------
*/

$selectedDate = $_GET['date'] ?? '';


/*
|--------------------------------------------------------------------------
| Get Available Dates
|--------------------------------------------------------------------------
|
| service_slots has no Demo tenant columns.
|
| Therefore:
| - Normal: slots are restricted to the assigned Agent.
| - Demo: slots are restricted to the assigned Demo Agent.
|
*/

if ($isDemoCustomer) {

    $dateStmt = $customerPdo->prepare("
        SELECT DISTINCT
            ss.service_date
        FROM service_slots ss
        JOIN agents a
            ON a.id = ss.agent_id
        WHERE ss.agent_id = ?
          AND ss.is_booked = 0
          AND TIMESTAMP(
                ss.service_date,
                ss.service_time
              ) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
          AND a.is_demo_account = 1
          AND a.demo_tenant_id = ?
        ORDER BY ss.service_date
    ");

    $dateStmt->execute([
        $assignedAgentId,
        $demoTenantId
    ]);

} else {

    $dateStmt = $customerPdo->prepare("
        SELECT DISTINCT
            service_date
        FROM service_slots
        WHERE agent_id = ?
          AND is_booked = 0
          AND TIMESTAMP(
                service_date,
                service_time
              ) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
        ORDER BY service_date
    ");

    $dateStmt->execute([
        $assignedAgentId
    ]);
}


$availableDates = $dateStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get Available Times For Selected Date
|--------------------------------------------------------------------------
*/

$slots = [];

if (!empty($selectedDate)) {

    if ($isDemoCustomer) {

        $stmt = $customerPdo->prepare("
            SELECT
                ss.*
            FROM service_slots ss
            JOIN agents a
                ON a.id = ss.agent_id
            WHERE ss.agent_id = ?
              AND ss.is_booked = 0
              AND ss.service_date = ?
              AND TIMESTAMP(
                    ss.service_date,
                    ss.service_time
                  ) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
              AND a.is_demo_account = 1
              AND a.demo_tenant_id = ?
            ORDER BY ss.service_time
        ");

        $stmt->execute([
            $assignedAgentId,
            $selectedDate,
            $demoTenantId
        ]);

    } else {

        $stmt = $customerPdo->prepare("
            SELECT *
            FROM service_slots
            WHERE agent_id = ?
              AND is_booked = 0
              AND service_date = ?
              AND TIMESTAMP(
                    service_date,
                    service_time
                  ) >= DATE_ADD(NOW(), INTERVAL 72 HOUR)
            ORDER BY service_time
        ");

        $stmt->execute([
            $assignedAgentId,
            $selectedDate
        ]);
    }

    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Schedule Service
        </h2>

        <form method="GET" class="mb-3">

            <input
                type="hidden"
                name="page"
                value="schedule-service">

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
                        value="<?= htmlspecialchars(
                            $date['service_date'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $selectedDate === $date['service_date']
                            ? 'selected'
                            : '' ?>>

                        <?= date(
                            'M d, Y',
                            strtotime($date['service_date'])
                        ) ?>

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

                        $key =
                            $slot['service_date'] .
                            '_' .
                            $slot['service_time'];

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
                                    href="?page=confirm-service&request_id=<?= $requestId ?>&slot_id=<?= (int) $slot['id'] ?>"
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