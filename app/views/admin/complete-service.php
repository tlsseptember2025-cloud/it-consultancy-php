<?php
// CSRF protection for all state-changing POST requests.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $servicePdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}
    require_once CONFIG_PATH . '/database.php';

    $servicePdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/invoice.php';
require_once CONFIG_PATH . '/retention.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/service_report.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid request.');
}

$completionNotes = trim($_POST['completion_notes'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ?page=complete-service-form&id=' . $id);
    exit;
}

$notes = trim($_POST['completion_notes'] ?? '');

if ($notes === '') {
    die('Completion notes are required.');
}

if ($isDemoAdmin) {

        $stmt = $servicePdo->prepare("
            UPDATE requests r
            INNER JOIN customers c
                ON c.id = r.customer_id
            INNER JOIN services s
                ON s.id = r.service_id
            SET
                r.workflow_stage = ?,
                r.status = 'Completed',
                r.job_status = 'Completed',
                r.completed_at = NOW(),
                r.completion_notes = ?
            WHERE r.id = ?
              AND c.demo_tenant_id = ?
              AND c.is_demo_account = 1
              AND s.demo_tenant_id = ?
              AND r.workflow_stage <> 'Closed'
        ");

        $stmt->execute([
            WORKFLOW_STAGE_CLOSED,
            $notes,
            $id,
            $demoTenantId,
            $demoTenantId
        ]);

    } else {

        $stmt = $servicePdo->prepare("
            UPDATE requests
            SET
                workflow_stage = ?,
                status = 'Completed',
                job_status = 'Completed',
                completed_at = NOW(),
                completion_notes = ?
            WHERE id = ?
              AND workflow_stage <> 'Closed'
        ");

        $stmt->execute([
            WORKFLOW_STAGE_CLOSED,
            $notes,
            $id
        ]);
    }

    if ($stmt->rowCount() !== 1) {
        die('Service request not found, already completed, or access denied.');
    }

/*
|--------------------------------------------------------------------------
| Record Service Completed Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $servicePdo,
    (int) $id,
    RequestEventHelper::EVENT_SERVICE_COMPLETED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Completed',
    'The service has been completed successfully.',
    true
);

if ($isDemoAdmin) {

    $stmt = $servicePdo->prepare("
        SELECT
            r.id,
            r.quoted_price,
            r.completed_at,
            r.completion_notes,

            c.id AS customer_id,
            c.name AS customer_name,
            c.email,

            s.title AS service_title,

            sb.id AS service_booking_id,

            p.payment_date

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        LEFT JOIN service_bookings sb
            ON sb.request_id = r.id

        LEFT JOIN payments p
            ON p.request_id = r.id

        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?

        ORDER BY p.payment_date DESC

        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $servicePdo->prepare("
        SELECT
            r.id,
            r.quoted_price,
            r.completed_at,
            r.completion_notes,

            c.id AS customer_id,
            c.name AS customer_name,
            c.email,

            s.title AS service_title,

            sb.id AS service_booking_id,

            p.payment_date

        FROM requests r

        INNER JOIN customers c
            ON c.id = r.customer_id

        INNER JOIN services s
            ON s.id = r.service_id

        LEFT JOIN service_bookings sb
            ON sb.request_id = r.id

        LEFT JOIN payments p
            ON p.request_id = r.id

        WHERE r.id = ?

        ORDER BY p.payment_date DESC

        LIMIT 1
    ");

    $stmt->execute([$id]);
}

$request = $stmt->fetch();

if (!$request) {
    die('Service request not found or access denied.');
}

if (!is_dir(dirname(__DIR__, 2) . '/storage/invoices')) {

    mkdir(
        dirname(__DIR__, 2) . '/storage/invoices',
        0777,
        true
    );

}

$invoicePath =
    dirname(__DIR__, 2)
    . '/storage/invoices/INV-'
    . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
    . '.pdf';



generateInvoicePdf(
    $request,
    $invoicePath
);

$reportDir = dirname(__DIR__, 2) . '/storage/reports';

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}

$reportPath = $reportDir . '/SERVICE-REPORT-' .
    str_pad($request['id'], 6, '0', STR_PAD_LEFT) .
    '.pdf';

generateServiceReportPdf(
    $request,
    $reportPath
);

sendServiceCompletedEmail(
    $request['email'],
    $request['customer_name'],
    $request['service_title'],
    $invoicePath,
    $reportPath,
    (int) $request['id'],
    (int) $request['service_booking_id']
);

createNotification(
    $servicePdo,
    'customer',
    $request['customer_id'],
    'Service Completed',
    'Your service has been completed successfully. Your invoice and service report are now available.',
    '?page=customer-requests'
);

header('Location: ?page=requests');
exit;