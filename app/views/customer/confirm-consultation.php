<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/security.php';

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Request & Slot
|--------------------------------------------------------------------------
*/

$requestId = (int)($_GET['request_id'] ?? 0);
$slotId    = (int)($_GET['slot_id'] ?? 0);

$customerId = (int)$_SESSION['customer']['id'];

verifyCustomerRequest($pdo, $requestId);


/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.customer_id,
        r.agent_id,
        c.name AS customer_name,
        c.email AS customer_email,
        s.title AS service_name
    FROM requests r

    INNER JOIN customers c
        ON c.id = r.customer_id

    LEFT JOIN services s
        ON s.id = r.service_id

    WHERE r.id = ?
      AND r.customer_id = ?
    LIMIT 1
");

$stmt->execute([
    $requestId,
    $customerId
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {

    $_SESSION['error'] =
        'You are not authorized to access this request.';

    header('Location: ?page=customer-requests');
    exit;
}


$assignedAgentId = (int)$request['agent_id'];


/*
|--------------------------------------------------------------------------
| Make Sure Request Has An Agent
|--------------------------------------------------------------------------
*/

if ($assignedAgentId <= 0) {

    $_SESSION['error'] =
        'This request has not been assigned to an agent yet.';

    header('Location: ?page=customer-requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Check If Already Booked
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM consultation_bookings
    WHERE request_id = ?
");

$stmt->execute([
    $requestId
]);

$alreadyBooked = (int)$stmt->fetchColumn() > 0;


if ($alreadyBooked) {

    require dirname(__DIR__) . '/layouts/header-customer.php';
    ?>

    <div class="alert alert-info">
        You have already scheduled your consultation for this request.
    </div>

    <?php
    require dirname(__DIR__) . '/layouts/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Selected Consultation Slot
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        agent_id,
        slot_date,
        slot_time,
        is_booked
    FROM consultation_slots
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $slotId
]);

$slot = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$slot) {

    die('Consultation slot not found.');
}


/*
|--------------------------------------------------------------------------
| Verify Slot Belongs To Assigned Agent
|--------------------------------------------------------------------------
*/

if ((int)$slot['agent_id'] !== $assignedAgentId) {

    die('Invalid consultation slot.');
}


/*
|--------------------------------------------------------------------------
| Verify Slot Is Still Available
|--------------------------------------------------------------------------
*/

if ((int)$slot['is_booked'] === 1) {

    $error =
        'Sorry, this consultation slot is no longer available.';

} else {

    $consultationDateTime = strtotime(
        $slot['slot_date'] . ' ' . $slot['slot_time']
    );

    /*
    |--------------------------------------------------------------------------
    | Enforce 48-Hour Rule
    |--------------------------------------------------------------------------
    */

    if (
        $consultationDateTime === false ||
        $consultationDateTime < strtotime('+48 hours')
    ) {

        header(
            'Location: ?page=schedule-consultation&request_id='
            . $requestId
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Load Agent
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email
    FROM agents
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $assignedAgentId
]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$agent) {

    die('Assigned agent not found.');
}


/*
|--------------------------------------------------------------------------
| Load Mail Configuration
|--------------------------------------------------------------------------
*/

$mailConfig = require dirname(__DIR__, 3) . '/config/mail_config.php';

$adminEmail = $mailConfig['admin_email'] ?? '';


/*
|--------------------------------------------------------------------------
| Process Booking
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $consultationMethod =
        trim($_POST['consultation_method'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validate Meeting Method
    |--------------------------------------------------------------------------
    */

    $allowedMethods = [
        'Google Meet',
        'Zoom'
    ];

    if (!in_array($consultationMethod, $allowedMethods, true)) {

        $error =
            'Please select a valid meeting method.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Re-check Slot Availability
        |--------------------------------------------------------------------------
        */

        $checkStmt = $pdo->prepare("
            SELECT
                is_booked
            FROM consultation_slots
            WHERE id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $slotId
        ]);

        $isBooked = $checkStmt->fetchColumn();


        if ($isBooked === false) {

            $error =
                'Consultation slot not found.';

        } elseif ((int)$isBooked === 1) {

            $error =
                'Sorry, this consultation slot is no longer available.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Create Booking
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO consultation_bookings
                (
                    request_id,
                    slot_id,
                    agent_id
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $requestId,
                $slotId,
                $assignedAgentId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Mark Slot As Booked
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE consultation_slots
                SET
                    is_booked = 1,
                    consultation_method = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $consultationMethod,
                $slotId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Update Request Workflow
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE requests
                SET workflow_stage = 'Consultation Scheduled'
                WHERE id = ?
            ");

            $stmt->execute([
                $requestId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Record Request Event
            |--------------------------------------------------------------------------
            */

            RequestEventHelper::addCurrentUser(
                $pdo,
                $requestId,
                RequestEventHelper::EVENT_CONSULTATION_SCHEDULED,
                RequestEventHelper::TYPE_CONSULTATION,
                'Consultation Scheduled',
                'The customer scheduled a consultation appointment.',
                true
            );


            /*
            |--------------------------------------------------------------------------
            | Consultation Details
            |--------------------------------------------------------------------------
            */

            $date = date(
                'M d, Y',
                strtotime($slot['slot_date'])
            );

            $time = date(
                'h:i A',
                strtotime($slot['slot_time'])
            );


            $customerName = htmlspecialchars(
                $request['customer_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );

            $customerEmail = $request['customer_email'] ?? '';

            $serviceName = htmlspecialchars(
                $request['service_name'] ?? 'Service Request',
                ENT_QUOTES,
                'UTF-8'
            );

            $agentName = htmlspecialchars(
                $agent['name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );

            $method = htmlspecialchars(
                $consultationMethod,
                ENT_QUOTES,
                'UTF-8'
            );


            /*
            |--------------------------------------------------------------------------
            | Customer Email
            |--------------------------------------------------------------------------
            */

            if (!empty($customerEmail)) {

                $customerBody = "
                    <h2>Hello {$customerName},</h2>

                    <p>
                        Your consultation has been scheduled successfully.
                    </p>

                    <hr>

                    <p>
                        <strong>Request:</strong>
                        #{$requestId}
                    </p>

                    <p>
                        <strong>Service:</strong>
                        {$serviceName}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        {$date}
                    </p>

                    <p>
                        <strong>Time:</strong>
                        {$time}
                    </p>

                    <p>
                        <strong>Meeting Method:</strong>
                        {$method}
                    </p>

                    <hr>

                    <p>
                        We will notify you once your consultation
                        has been confirmed by our team.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";

                $emailSent = sendEmail(
                    $customerEmail,
                    'Consultation Scheduled - Request #' . $requestId,
                    $customerBody
                );

                if (!$emailSent) {

                    error_log(
                        'Consultation scheduled email FAILED for customer: '
                        . $customerEmail
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Agent Notification
            |--------------------------------------------------------------------------
            */

            createNotification(
                $pdo,
                'agent',
                (int)$agent['id'],
                'Consultation Scheduled',
                'Customer '
                    . ($request['customer_name'] ?? 'Customer')
                    . ' scheduled Request #'
                    . $requestId
                    . ' for '
                    . $date
                    . ' at '
                    . $time
                    . '.',
                '?page=agent-dashboard'
            );


            /*
            |--------------------------------------------------------------------------
            | Agent Email
            |--------------------------------------------------------------------------
            */

            if (!empty($agent['email'])) {

                $agentBody = "
                    <h2>Hello {$agentName},</h2>

                    <p>
                        A customer has scheduled a consultation
                        for a service request assigned to you.
                    </p>

                    <hr>

                    <p>
                        <strong>Request:</strong>
                        #{$requestId}
                    </p>

                    <p>
                        <strong>Customer:</strong>
                        {$customerName}
                    </p>

                    <p>
                        <strong>Service:</strong>
                        {$serviceName}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        {$date}
                    </p>

                    <p>
                        <strong>Time:</strong>
                        {$time}
                    </p>

                    <p>
                        <strong>Meeting Method:</strong>
                        {$method}
                    </p>

                    <hr>

                    <p>
                        Please log in to your Agent Portal
                        to review the scheduled consultation.
                    </p>

                    <p>
                        <a
                            href='" . APP_URL . "/?page=agent-dashboard'
                            style='
                                background:#0d6efd;
                                color:#ffffff;
                                padding:12px 22px;
                                text-decoration:none;
                                border-radius:6px;
                                display:inline-block;
                                font-weight:600;
                            '>
                            Open Agent Portal
                        </a>
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";

                $emailSent = sendEmail(
                    $agent['email'],
                    'Consultation Scheduled - Request #' . $requestId,
                    $agentBody
                );

                if (!$emailSent) {

                    error_log(
                        'Consultation scheduled email FAILED for agent: '
                        . $agent['email']
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Admin Notification
            |--------------------------------------------------------------------------
            */

            createNotification(
                $pdo,
                'admin',
                null,
                'Consultation Scheduled',
                'Customer '
                    . ($request['customer_name'] ?? 'Customer')
                    . ' scheduled Request #'
                    . $requestId
                    . ' for '
                    . $date
                    . ' at '
                    . $time
                    . '.',
                '?page=requests'
            );


            /*
            |--------------------------------------------------------------------------
            | Admin Email
            |--------------------------------------------------------------------------
            */

            if (!empty($adminEmail)) {

                $adminBody = "
                    <h2>Consultation Scheduled</h2>

                    <p>
                        A customer has scheduled a consultation.
                    </p>

                    <hr>

                    <p>
                        <strong>Request:</strong>
                        #{$requestId}
                    </p>

                    <p>
                        <strong>Customer:</strong>
                        {$customerName}
                    </p>

                    <p>
                        <strong>Customer Email:</strong>
                        " . htmlspecialchars(
                            $customerEmail,
                            ENT_QUOTES,
                            'UTF-8'
                        ) . "
                    </p>

                    <p>
                        <strong>Service:</strong>
                        {$serviceName}
                    </p>

                    <p>
                        <strong>Assigned Agent:</strong>
                        {$agentName}
                    </p>

                    <p>
                        <strong>Date:</strong>
                        {$date}
                    </p>

                    <p>
                        <strong>Time:</strong>
                        {$time}
                    </p>

                    <p>
                        <strong>Meeting Method:</strong>
                        {$method}
                    </p>

                    <hr>

                    <p>
                        The consultation is now scheduled
                        and is awaiting administrator confirmation.
                    </p>

                    <p>
                        <a
                            href='" . APP_URL . "/?page=requests'
                            style='
                                background:#0d6efd;
                                color:#ffffff;
                                padding:12px 22px;
                                text-decoration:none;
                                border-radius:6px;
                                display:inline-block;
                                font-weight:600;
                            '>
                            Open Admin Requests
                        </a>
                    </p>
                ";

                $emailSent = sendEmail(
                    $adminEmail,
                    'Consultation Scheduled - Request #' . $requestId,
                    $adminBody
                );

                if (!$emailSent) {

                    error_log(
                        'Consultation scheduled email FAILED for admin: '
                        . $adminEmail
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Booking Complete
            |--------------------------------------------------------------------------
            */

            header('Location: ?page=customer-requests');
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Customer Page
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<div class="card shadow-sm">

    <div class="card-body">

        <h2 class="mb-4">
            Confirm Consultation
        </h2>


        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="mb-4">

            <p>
                <strong>Service:</strong>
                <?= htmlspecialchars(
                    $request['service_name'] ?? 'Service Request'
                ) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= formatDate($slot['slot_date']) ?>
            </p>

            <p>
                <strong>Time:</strong>
                <?= formatTime($slot['slot_time']) ?>
            </p>

            <p>
                <strong>Assigned Agent:</strong>
                <?= htmlspecialchars($agent['name']) ?>
            </p>

        </div>


        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    <strong>Meeting Method</strong>
                </label>


                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="radio"
                        name="consultation_method"
                        id="google_meet"
                        value="Google Meet"
                        <?= (
                            ($_POST['consultation_method'] ?? 'Google Meet')
                            === 'Google Meet'
                        ) ? 'checked' : '' ?>
                        required>

                    <label
                        class="form-check-label"
                        for="google_meet">

                        Google Meet

                    </label>

                </div>


                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="radio"
                        name="consultation_method"
                        id="zoom"
                        value="Zoom"
                        <?= (
                            ($_POST['consultation_method'] ?? '')
                            === 'Zoom'
                        ) ? 'checked' : '' ?>>

                    <label
                        class="form-check-label"
                        for="zoom">

                        Zoom

                    </label>

                </div>

            </div>


            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-success">

                    Confirm Booking

                </button>


                <a
                    href="?page=schedule-consultation&request_id=<?= $requestId ?>"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </div>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>