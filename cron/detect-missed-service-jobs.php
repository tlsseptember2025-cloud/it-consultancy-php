<?php

/*
|--------------------------------------------------------------------------
| Detect Missed Service Jobs
|--------------------------------------------------------------------------
|
| A service job is considered missed when:
|
|   Scheduled time + 1 hour has passed
|   AND the agent has not started the service.
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

echo "Starting missed service detection...\n";

$now = new DateTime(
    'now',
    new DateTimeZone('Asia/Dubai')
);

$nowString = $now->format('Y-m-d H:i:s');

echo "Current UAE time: {$nowString}\n";


/*
|--------------------------------------------------------------------------
| Find expired Pending service jobs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.customer_id,
        r.workflow_stage,
        r.job_status,

        c.name AS customer_name,
        c.email AS customer_email,

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
        r.job_status = 'Pending'

        AND TIMESTAMP(ss.service_date, ss.service_time)
            <= DATE_SUB(?, INTERVAL 1 HOUR)

    ORDER BY
        ss.service_date,
        ss.service_time
");

$stmt->execute([
    $nowString
]);

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($jobs) . " missed service job(s).\n";


/*
|--------------------------------------------------------------------------
| Process each missed service job
|--------------------------------------------------------------------------
*/

foreach ($jobs as $job) {

    $requestId = (int) $job['id'];

    echo "Processing Request #{$requestId}...\n";


    /*
    |--------------------------------------------------------------------------
    | Update request
    |--------------------------------------------------------------------------
    */

$update = $pdo->prepare("
    UPDATE requests

    SET
        workflow_stage = 'Needs Admin Review',
        job_status = 'Pending',
        review_type = 'service_missed'

    WHERE
        id = ?

        AND job_status = 'Pending'
        AND workflow_stage = 'Service Scheduled'
");
    $update->execute([
        $requestId
    ]);


    if ($update->rowCount() !== 1) {

        echo "Request #{$requestId} was already processed or changed.\n";

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Record audit event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'SERVICE_MISSED',
        RequestEventHelper::TYPE_SERVICE,
        'Service Missed',
        'The service was automatically sent for administrator review because the scheduled one-hour start window expired without the service being started.',
        RequestEventHelper::SOURCE_SYSTEM,
        null,
        true
    );


    echo "Request #{$requestId} marked as Missed Service.\n";
}


echo "Missed service detection completed.\n";