<?php

/*
|--------------------------------------------------------------------------
| Detect Overdue In-Progress Consultations
|--------------------------------------------------------------------------
|
| Rule:
|
|   Consultation was started
|   AND scheduled one-hour session has ended
|   AND consultation is still In Progress
|
| Result:
|
|   job_status      = Needs Admin Review
|   workflow_stage  = Needs Admin Review
|
| The consultation is removed from the agent's active consultation list
| and becomes an administrator investigation case.
|
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/views');

require_once CONFIG_PATH . '/settings.php';
require_once CONFIG_PATH . '/database.php';

require_once APP_PATH . '/helpers/RequestEventHelper.php';

date_default_timezone_set('Asia/Dubai');

echo "Starting overdue consultation detection...\n";

$now = new DateTime(
    'now',
    new DateTimeZone('Asia/Dubai')
);

$nowString = $now->format('Y-m-d H:i:s');

echo "Current UAE time: {$nowString}\n";


/*
|--------------------------------------------------------------------------
| Find In Progress consultations whose scheduled hour has ended
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

        cs.slot_date,
        cs.slot_time

    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    INNER JOIN services s
        ON s.id = r.service_id

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.job_status = 'In Progress'

        AND TIMESTAMP(cs.slot_date, cs.slot_time)
            <= DATE_SUB(?, INTERVAL 1 HOUR)

    ORDER BY
        cs.slot_date,
        cs.slot_time
");

$stmt->execute([
    $nowString
]);

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($consultations) . " overdue consultation(s).\n";


/*
|--------------------------------------------------------------------------
| Process each overdue consultation
|--------------------------------------------------------------------------
*/

foreach ($consultations as $consultation) {

    $requestId = (int) $consultation['id'];

    echo "Processing Request #{$requestId}...\n";


    /*
    |--------------------------------------------------------------------------
    | Move to Admin Review
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
    UPDATE requests

    SET
        job_status = 'Needs Admin Review',
        workflow_stage = 'Needs Admin Review',
        review_type = 'consultation_overdue'

    WHERE
        id = ?

        AND job_status = 'In Progress'
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
    | Record Audit Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'CONSULTATION_SESSION_EXPIRED',
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation Session Expired',
        'The consultation was started by the agent but remained In Progress after the scheduled one-hour session ended. The consultation was automatically closed and sent for administrator review.',
        RequestEventHelper::SOURCE_SYSTEM,
        null,
        true
    );

    echo "Request #{$requestId} moved to Needs Admin Review.\n";
}


echo "Overdue consultation detection completed.\n";