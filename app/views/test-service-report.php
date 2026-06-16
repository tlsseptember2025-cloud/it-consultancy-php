<?php

require_once dirname(__DIR__) . '/helpers/service_report.php';

$requestId = 16; // Change to an existing completed request

$stmt = $pdo->prepare("
    SELECT
        requests.*,
        customers.name AS customer_name,
        customers.email,
        services.title AS service_title
    FROM requests

    JOIN customers
        ON customers.id = requests.customer_id

    JOIN services
        ON services.id = requests.service_id

    WHERE requests.id = ?
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Request not found.');
}

$reportDir = dirname(__DIR__, 2) . '/storage/reports';

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}

$outputPath = $reportDir . '/SERVICE-REPORT-TEST.pdf';

generateServiceReportPdf(
    $request,
    $outputPath
);

echo "Report generated successfully:<br><br>";
echo $outputPath;