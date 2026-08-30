<?php

/*
|--------------------------------------------------------------------------
| Detect Missed Consultations
|--------------------------------------------------------------------------
|
| A consultation is considered missed when:
|
|   Scheduled time + 1 hour has passed
|   AND the agent has not started the consultation.
|
|--------------------------------------------------------------------------
*/

define('BASE_PATH', dirname(__DIR__));

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/views');

require_once CONFIG_PATH . '/settings.php';
require_once CONFIG_PATH . '/database.php';

require_once APP_PATH . '/helpers/email.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

date_default_timezone_set('Asia/Dubai');

echo "Starting missed consultation detection...\n";

/*
|--------------------------------------------------------------------------
| Current UAE Time
|--------------------------------------------------------------------------
*/

$now = new DateTime('now', new DateTimeZone('Asia/Dubai'));

$nowString = $now->format('Y-m-d H:i:s');

echo "Current UAE time: {$nowString}\n";

/*
|--------------------------------------------------------------------------
| Find consultations whose 1-hour window has expired
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
        cs.slot_time,
        cs.consultation_method

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
        r.workflow_stage = 'Consultation Confirmed'

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

echo "Found " . count($consultations) . " missed consultation(s).\n";

/*
|--------------------------------------------------------------------------
| Process each missed consultation
|--------------------------------------------------------------------------
*/

foreach ($consultations as $consultation) {

    $requestId = (int) $consultation['id'];

    echo "Processing Request #{$requestId}...\n";

    /*
    |--------------------------------------------------------------------------
    | Update workflow state
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE requests

        SET
            workflow_stage = 'Missed Consultation'

        WHERE
            id = ?
            AND workflow_stage = 'Consultation Confirmed'
    ");

    $update->execute([
        $requestId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Make sure this request was actually changed
    |--------------------------------------------------------------------------
    */

    if ($update->rowCount() !== 1) {

        echo "Request #{$requestId} was already processed or changed.\n";

        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Send apology email
    |--------------------------------------------------------------------------
    */

    if (!empty($consultation['customer_email'])) {

        $subject = 'We Apologize – Your Consultation Was Missed';

        $body = "

        <h2>Hello " . htmlspecialchars($consultation['customer_name']) . ",</h2>

        <p>
            We sincerely apologize that your scheduled consultation could not
            take place as planned.
        </p>

        <p>
            <strong>Service:</strong><br>
            " . htmlspecialchars($consultation['service_name']) . "
        </p>

        <p>
            <strong>Scheduled Date:</strong><br>
            " . htmlspecialchars($consultation['slot_date']) . "
        </p>

        <p>
            <strong>Scheduled Time:</strong><br>
            " . htmlspecialchars($consultation['slot_time']) . "
        </p>

        <p>
            We understand that your time is important to us.
            Please choose a new consultation date and time through your
            customer portal.
        </p>

        <p>
            <a
                href='" . APP_URL . "/?page=reschedule-consultation&request_id={$requestId}'
                style='
                    background:#0d6efd;
                    color:white;
                    padding:10px 20px;
                    text-decoration:none;
                    border-radius:5px;
                    display:inline-block;
                '
            >
                Choose a New Consultation Time
            </a>
        </p>

        <p>
            You can also log in to your customer portal to manage your
            consultation.
        </p>

        <p>
            We apologize for the inconvenience and appreciate your
            understanding.
        </p>

        <p>
            IT Consultancy Team
        </p>

        ";

        $emailSent = sendEmail(
            $consultation['customer_email'],
            $subject,
            $body
        );

        if (!$emailSent) {

            echo "WARNING: Email failed for Request #{$requestId}.\n";

        } else {

            echo "Apology email sent successfully.\n";

        }

    } else {

        echo "WARNING: No customer email for Request #{$requestId}.\n";

    }

    /*
    |--------------------------------------------------------------------------
    | Record System Audit Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(
        $pdo,
        $requestId,
        'CONSULTATION_MISSED',
        RequestEventHelper::TYPE_CONSULTATION,
        'Consultation Missed',
        'The consultation was automatically marked as missed because the scheduled one-hour consultation window expired without the consultation being started.',
        RequestEventHelper::SOURCE_SYSTEM,
        null,
        true
    );

    echo "Request #{$requestId} marked as Missed Consultation.\n";
}

echo "Missed consultation detection completed.\n";