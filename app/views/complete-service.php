<?php

require_once dirname(__DIR__) . '/helpers/invoice.php';
require_once dirname(__DIR__) . '/helpers/email.php';
require_once dirname(__DIR__) . '/helpers/service_report.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$completionNotes = trim($_POST['completion_notes'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notes = trim($_POST['completion_notes']);

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            workflow_stage = 'Service Completed',
            status = 'Completed',
            completed_at = NOW(),
            completion_notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $notes,
        $id
    ]);

    // Keep the rest of your existing logic
    // (invoice generation, emails, redirects, etc.)

    $stmt->execute([
    $completionNotes,
    $id
    ]);
}

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.quoted_price,
        r.completed_at,
        c.name AS customer_name,
        c.email,
        s.title AS service_title,
        p.payment_date

    FROM requests r

    JOIN customers c
        ON c.id = r.customer_id

    JOIN services s
        ON s.id = r.service_id

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
    $reportPath
);

header('Location: ?page=requests');
exit;