<?php

require_once dirname(__DIR__) . '/helpers/invoice.php';
require_once dirname(__DIR__) . '/helpers/email.php';

if (!isset($_SESSION['user'])) {

    header('Location: ?page=login');
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Service Completed',
        status = 'Completed',
        completed_at = NOW()
    WHERE id = ?
");

$stmt->execute([$id]);

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

sendServiceCompletedEmail(
    $request['email'],
    $request['customer_name'],
    $request['service_title'],
    $invoicePath
);

header('Location: ?page=requests');
exit;