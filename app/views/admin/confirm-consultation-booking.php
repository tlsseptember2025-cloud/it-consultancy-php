<?php
// CSRF protection for this state-changing GET action.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $consultationPdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}
    require_once CONFIG_PATH . '/database.php';

    $consultationPdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/meeting.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

/*
|--------------------------------------------------------------------------
| Confirm Consultation
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $consultationPdo->prepare("
        UPDATE requests r
        INNER JOIN customers c ON c.id = r.customer_id
        INNER JOIN services s ON s.id = r.service_id
        SET r.workflow_stage = 'Consultation Confirmed'
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
    ");

    $stmt->execute([$id, $demoTenantId, $demoTenantId]);

} else {

    $stmt = $consultationPdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Confirmed'
        WHERE id = ?
    ");

    $stmt->execute([$id]);
}

if ($stmt->rowCount() === 0) {
    die('Consultation request not found or access denied.');
}

/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::add(
    $consultationPdo,
    $id,
    'CONSULTATION_CONFIRMED',
    RequestEventHelper::TYPE_SYSTEM,
    'Consultation Confirmed',
    'The consultation appointment was confirmed by the administrator.',
    RequestEventHelper::SOURCE_ADMINISTRATOR,
    null
);

/*
|--------------------------------------------------------------------------
| Load Customer & Consultation Details
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $consultationPdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            cs.slot_date,
            cs.slot_time,
            cs.consultation_method
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        INNER JOIN services s ON s.id = r.service_id
        LEFT JOIN consultation_bookings cb ON cb.request_id = r.id
        LEFT JOIN consultation_slots cs ON cs.id = cb.slot_id
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
    ");

    $stmt->execute([$id, $demoTenantId, $demoTenantId]);

} else {

    $stmt = $consultationPdo->prepare("
        SELECT
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            cs.slot_date,
            cs.slot_time,
            cs.consultation_method
        FROM requests r
        INNER JOIN customers c ON c.id = r.customer_id
        INNER JOIN services s ON s.id = r.service_id
        LEFT JOIN consultation_bookings cb ON cb.request_id = r.id
        LEFT JOIN consultation_slots cs ON cs.id = cb.slot_id
        WHERE r.id = ?
    ");

    $stmt->execute([$id]);
}

$request = $stmt->fetch();

if (!$request) {
    die('Consultation request not found or access denied.');
}

/*
|--------------------------------------------------------------------------
| Generate Meeting Link
|--------------------------------------------------------------------------
*/

$meetingLink = getMeetingLink(
    $request['consultation_method'],
    $request['slot_time']
);

/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

sendEmail(
    $request['email'],
    'Consultation Confirmed',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your consultation has been confirmed.
    </p>

    <p>
        <strong>Service:</strong><br>
        {$request['service_title']}
    </p>

    <p>
        <strong>Date:</strong><br>
        " . formatDate($request['slot_date']) . "
    </p>

    <p>
        <strong>Time:</strong><br>
        " . formatTime($request['slot_time']) . "
    </p>

    <p>
        <strong>Method:</strong><br>
        {$request['consultation_method']}
    </p>

    <hr style='border:none;border-top:1px solid #dddddd;margin:20px 0;'>

<h3 style='margin:0 0 12px 0;font-size:20px;'>

    🔒 Secure Meeting Access

</h3>

<p>

    Your secure
    <strong>{$request['consultation_method']}</strong>
    meeting link will become available
    <strong>10 minutes before your scheduled consultation.</strong>

</p>

<p>

Please log in to your customer portal
and select
<strong>Join Meeting</strong>
when it becomes available.

</p>

<hr style='border:none;border-top:1px solid #dddddd;margin:30px 0;'>


    <p>
    You can manage your consultation, view updates, and access your meeting from your customer portal.
    </p>

    <p>
        <a
            href='" . APP_URL . "/?page=public-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '>

            Customer Portal

        </a>
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);

/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $consultationPdo,
    'customer',
    $request['customer_id'],
    'Consultation Confirmed',
    'Your consultation has been confirmed.',
    '?page=customer-requests'
);

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;