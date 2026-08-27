<?php

define('BASE_PATH', dirname(__DIR__));

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/views');

require_once CONFIG_PATH . '/settings.php';
require_once CONFIG_PATH . '/database.php';

require_once APP_PATH . '/helpers/RequestEventHelper.php';

echo "Starting missed consultation check...\n";


/*
|--------------------------------------------------------------------------
| Find Confirmed Consultations That Have Passed
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.customer_id,
        r.agent_id,
        r.workflow_stage,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method

    FROM requests r

    INNER JOIN consultation_bookings cb
        ON cb.request_id = r.id

    INNER JOIN consultation_slots cs
        ON cs.id = cb.slot_id

    WHERE
        r.workflow_stage = 'Consultation Confirmed'
        AND DATE_ADD(
    TIMESTAMP(cs.slot_date, cs.slot_time),
    INTERVAL 1 HOUR
) < NOW()
");

$stmt->execute();

$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($consultations) . " overdue consultation(s).\n";


/*
|--------------------------------------------------------------------------
| Move To Administrator Decision
|--------------------------------------------------------------------------
*/

foreach ($consultations as $consultation) {

    echo "Processing Request #" . $consultation['id'] . "...\n";

    $stmt = $pdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Decision Required'
        WHERE id = ?
    ");

    $stmt->execute([
        $consultation['id']
    ]);


    RequestEventHelper::add(
        $pdo,
        $consultation['id'],
        'CONSULTATION_DECISION_REQUIRED',
        RequestEventHelper::TYPE_SYSTEM,
        'Consultation Decision Required',
        'The scheduled consultation time has passed and requires an administrator decision.',
        RequestEventHelper::SOURCE_SYSTEM,
        null,
        true
    );

    echo "Request #" . $consultation['id']
        . " moved to Consultation Decision Required.\n";
}

echo "Missed consultation check completed.\n";