<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid service request.');
}

/*
|--------------------------------------------------------------------------
| Select Database
|--------------------------------------------------------------------------
| Main Admin:
|   - Uses the Main System database ($pdo)
|
| Demo Admin:
|   - Uses the Demo database ($demoPdo)
|   - Is restricted to the current Demo tenant
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);

$approvePdo = $pdo;
$demoTenantId = null;

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvePdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}

/*
|--------------------------------------------------------------------------
| Approve Service Schedule
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Scheduled'
        WHERE id = ?
          AND customer_id IN (
              SELECT id
              FROM customers
              WHERE demo_tenant_id = ?
                AND is_demo_account = 1
          )
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    // Original Main Admin update preserved.
    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Scheduled'
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);
}

if ($stmt->rowCount() !== 1) {
    die('The service schedule could not be approved.');
}

/*
|--------------------------------------------------------------------------
| Record Service Scheduled Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $approvePdo,
    $id,
    RequestEventHelper::EVENT_SERVICE_SCHEDULED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Scheduled',
    'The administrator approved and confirmed the service appointment.',
    true
);

/*
|--------------------------------------------------------------------------
| Load Customer & Service Details
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $approvePdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            ss.service_date,
            ss.service_time

        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id

        JOIN services s
            ON s.id = r.service_id

        LEFT JOIN service_bookings sb
            ON sb.request_id = r.id

        LEFT JOIN service_slots ss
            ON ss.id = sb.slot_id

        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    // Original Main Admin lookup preserved.
    $stmt = $approvePdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            ss.service_date,
            ss.service_time

        FROM requests r

        JOIN customers c
            ON c.id = r.customer_id

        JOIN services s
            ON s.id = r.service_id

        LEFT JOIN service_bookings sb
            ON sb.request_id = r.id

        LEFT JOIN service_slots ss
            ON ss.id = sb.slot_id

        WHERE r.id = ?
    ");

    $stmt->execute([
        $id
    ]);
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Service request details could not be loaded.');
}

/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

if (!empty($request['email'])) {

    sendEmail(
        $request['email'],
        'Service Booking Confirmed',
        "
        <h2>Hello " . htmlspecialchars(
            $request['name'],
            ENT_QUOTES,
            'UTF-8'
        ) . ",</h2>

        <p>
            Your service booking has been confirmed and approved.
        </p>

        <p>
            <strong>Service:</strong>
            " . htmlspecialchars(
                $request['service_title'],
                ENT_QUOTES,
                'UTF-8'
            ) . "
        </p>

        <p>
            <strong>Date:</strong>
            " . (
                !empty($request['service_date'])
                    ? date('M d, Y', strtotime($request['service_date']))
                    : 'Pending'
            ) . "
        </p>

        <p>
            <strong>Time:</strong>
            " . (
                !empty($request['service_time'])
                    ? date('h:i A', strtotime($request['service_time']))
                    : 'Pending'
            ) . "
        </p>

        <p>
            We look forward to assisting you at the scheduled time.
        </p>

        <p>
            Kind regards,<br>
            IT Consultancy Team
        </p>
        "
    );
}

/*
|--------------------------------------------------------------------------
| Create Customer Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $approvePdo,
    'customer',
    $request['customer_id'],
    'Service Scheduled',
    'Your service has been scheduled and confirmed.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;
