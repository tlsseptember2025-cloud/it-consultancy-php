<?php

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once APP_PATH . '/helpers/retention_export_helper.php';


$requestId = (int) ($_GET['id'] ?? 0);


if ($requestId <= 0) {
    die('Invalid request.');
}


/*
|--------------------------------------------------------------------------
| Load Archived Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        c.company,
        s.title AS service_title,
        a.name AS agent_name

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    LEFT JOIN agents a
        ON a.id = r.agent_id

    WHERE r.id = ?
      AND r.workflow_stage = 'Archived'

    LIMIT 1
");


$stmt->execute([
    $requestId
]);


$request = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$request) {
    die('Archived request not found.');
}


/*
|--------------------------------------------------------------------------
| Verify Export Eligibility
|--------------------------------------------------------------------------
*/

if (
    !canExportRetentionRequest(
        $request
    )
) {
    die(
        'This request is not currently eligible '
        . 'for retention export.'
    );
}


/*
|--------------------------------------------------------------------------
| Load Request Timeline
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM request_events
    WHERE request_id = ?
    ORDER BY created_at ASC, id ASC
");


$stmt->execute([
    $requestId
]);


$events = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Create Export Directory
|--------------------------------------------------------------------------
*/

$exportDirectory =
    dirname(__DIR__, 3)
    . '/storage/retention_exports';


if (!is_dir($exportDirectory)) {

    if (
        !mkdir(
            $exportDirectory,
            0775,
            true
        )
    ) {
        die(
            'Unable to create retention export directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Generate Export Filename
|--------------------------------------------------------------------------
*/

$filename =
    'Request-'
    . $requestId
    . '-Retention-Export-'
    . date('Ymd-His')
    . '.pdf';


$outputPath =
    $exportDirectory
    . '/'
    . $filename;


/*
|--------------------------------------------------------------------------
| Generate PDF
|--------------------------------------------------------------------------
*/

try {

    generateRetentionExportPdf(
        $request,
        $events,
        $outputPath
    );

} catch (Throwable $e) {

    die(
        'Retention export failed: '
        . htmlspecialchars(
            $e->getMessage()
        )
    );
}


/*
|--------------------------------------------------------------------------
| Verify File
|--------------------------------------------------------------------------
*/

if (
    !file_exists($outputPath)
    || filesize($outputPath) <= 0
) {
    die(
        'Retention export failed: '
        . 'the PDF file was not created.'
    );
}


/*
|--------------------------------------------------------------------------
| Record Export Event
|--------------------------------------------------------------------------
*/

try {

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'RETENTION_EXPORTED',
        RequestEventHelper::TYPE_SYSTEM,
        'Retention Export Created',
        'Administrator created a permanent retention export PDF: '
        . $filename,
        RequestEventHelper::SOURCE_ADMINISTRATOR,
        null
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Important
    |--------------------------------------------------------------------------
    |
    | The PDF exists, but the audit event failed.
    | Do not pretend the audit was successful.
    |
    */

    die(
        'Retention export was created, but the audit event '
        . 'could not be recorded. Please contact the administrator.'
    );
}

/*
|--------------------------------------------------------------------------
| Return to Retention Review
|--------------------------------------------------------------------------
*/

header(
    'Location: ?page=review-retention&id='
    . $requestId
    . '&success=retention-exported'
);

exit;