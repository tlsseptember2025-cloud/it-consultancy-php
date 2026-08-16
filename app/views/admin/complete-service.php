<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/invoice.php';
require_once CONFIG_PATH . '/retention.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/service_report.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = $_GET['id'] ?? 0;

$completionNotes = trim($_POST['completion_notes'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notes = trim($_POST['completion_notes']);

    $stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = ?,
        status = 'Completed',
        job_status = 'Completed',
        completed_at = NOW(),
        completion_notes = ?
    WHERE id = ?
");

    $stmt->execute([
    WORKFLOW_STAGE_CLOSED,
    $notes,
    $id
]);

/*
|--------------------------------------------------------------------------
| Record Service Completed Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $pdo,
    (int) $id,
    RequestEventHelper::EVENT_SERVICE_COMPLETED,
    RequestEventHelper::TYPE_SERVICE,
    'Service Completed',
    'The service has been completed successfully.',
    true
);

}

$stmt = $pdo->prepare("
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

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
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

$request = $stmt->fetch();

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
    $pdo,
    'customer',
    $request['customer_id'],
    'Service Completed',
    'Your service has been completed successfully. Your invoice and service report are now available.',
    '?page=customer-requests'
);

header('Location: ?page=requests');
exit;