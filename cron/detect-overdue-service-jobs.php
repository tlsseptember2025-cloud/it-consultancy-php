<?php

/*
|--------------------------------------------------------------------------
| Detect Overdue Service Jobs
|--------------------------------------------------------------------------
|
| A service job is overdue when:
|
|   The agent started the service
|   AND
|   The scheduled one-hour service session has expired
|   AND
|   the job is still In Progress.
|
| It must NOT be marked as Missed Service.
| It must be sent to administrator review.
|
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require_once CONFIG_PATH . '/settings.php';
require_once CONFIG_PATH . '/database.php';

require_once APP_PATH . '/helpers/RequestEventHelper.php';

date_default_timezone_set('Asia/Dubai');

echo "Starting overdue service detection...\n";


/*
|--------------------------------------------------------------------------
| Current UAE Time
|--------------------------------------------------------------------------
*/

$now = new DateTime(
    'now',
    new DateTimeZone('Asia/Dubai')
);

$nowString = $now->format('Y-m-d H:i:s');

echo "Current UAE time: {$nowString}\n";


/*
|--------------------------------------------------------------------------
| Find In Progress service jobs whose one-hour session expired
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        r.id,
        r.customer_id,
        r.workflow_stage,
        r.job_status,

        c.name AS customer_name,

        s.title AS service_name,

        ss.service_date,
        ss.service_time

    FROM requests r

    INNER JOIN service_bookings sb
        ON sb.request_id = r.id

    INNER JOIN service_slots ss
        ON ss.id = sb.slot_id

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    WHERE
        r.job_status = 'In Progress'

        AND TIMESTAMP(ss.service_date, ss.service_time)
            <= DATE_SUB(?, INTERVAL 1 HOUR)

        AND r.workflow_stage <> 'Needs Admin Review'

    ORDER BY
        ss.service_date,
        ss.service_time
");

$stmt->execute([
    $nowString
]);

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($jobs) . " overdue service job(s).\n";


/*
|--------------------------------------------------------------------------
| Process each overdue service job
|--------------------------------------------------------------------------
*/

foreach ($jobs as $job) {

    $requestId = (int) $job['id'];

    echo "Processing Request #{$requestId}...\n";


    /*
    |--------------------------------------------------------------------------
    | Move to Admin Review
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests

        SET
            workflow_stage = 'Needs Admin Review',
            job_status = 'Needs Admin Review',
            review_type = 'service_overdue'

        WHERE
            id = ?

            AND job_status = 'In Progress'

            AND workflow_stage <> 'Needs Admin Review'
    ");

    $update->execute([
        $requestId
    ]);


    if ($update->rowCount() !== 1) {

        echo
            "Request #{$requestId} was already processed or changed.\n";

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Record Audit Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'SERVICE_OVERDUE',
        RequestEventHelper::TYPE_SERVICE,
        'Service Overdue',
        'The service was started by the assigned agent but remained In Progress after the scheduled one-hour service session expired. The service has been sent to administrator review.',
        RequestEventHelper::SOURCE_SYSTEM,
        null,
        true
    );


    echo
        "Request #{$requestId} marked as Needs Admin Review.\n";
}


echo "Overdue service detection completed.\n";