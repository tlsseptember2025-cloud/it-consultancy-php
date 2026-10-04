<?php

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';

if (
    !is_string($submittedCsrfToken) ||
    !hash_equals($csrfToken, $submittedCsrfToken)
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


/*
|--------------------------------------------------------------------------
| Determine Admin Type / Database
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();
    require_once CONFIG_PATH . '/demo-database.php';

    $consultationPdo = $demoPdo;

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_user']);

        header('Location: ?page=demo-login');
        exit;
    }

} elseif (isset($_SESSION['user'])) {

    requireAdminLogin();

    if (isset($_SESSION['demo_super_admin'])) {
        header('Location: ?page=demo-super-admin');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $consultationPdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Helpers
|--------------------------------------------------------------------------
*/

require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/google-meet.php';
require_once HELPER_PATH . '/zoom.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';



/*
|--------------------------------------------------------------------------
| Request ID
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid consultation request.');
}


/*
|--------------------------------------------------------------------------
| Load Customer & Consultation Details
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $consultationPdo->prepare("
        SELECT
            r.id AS request_id,
            r.workflow_stage,
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            cb.id AS booking_id,
            cs.id AS slot_id,
            cs.slot_date,
            cs.slot_time,
            cs.consultation_method,
            cs.meeting_link
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        LEFT JOIN consultation_bookings cb
            ON cb.request_id = r.id
        LEFT JOIN consultation_slots cs
            ON cs.id = cb.slot_id
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $consultationPdo->prepare("
        SELECT
            r.id AS request_id,
            r.workflow_stage,
            c.id AS customer_id,
            c.name,
            c.email,
            s.title AS service_title,
            cb.id AS booking_id,
            cs.id AS slot_id,
            cs.slot_date,
            cs.slot_time,
            cs.consultation_method,
            cs.meeting_link
        FROM requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        LEFT JOIN consultation_bookings cb
            ON cb.request_id = r.id
        LEFT JOIN consultation_slots cs
            ON cs.id = cb.slot_id
        WHERE r.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Consultation request not found or access denied.');
}


/*
|--------------------------------------------------------------------------
| Validate Consultation Booking
|--------------------------------------------------------------------------
*/

if (empty($request['slot_id'])) {
    die('No consultation slot is associated with this request.');
}

$consultationMethod = trim(
    (string) ($request['consultation_method'] ?? '')
);

$meetingLink = trim(
    (string) ($request['meeting_link'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Create Google Meet Only When Needed
|--------------------------------------------------------------------------
|
| Important:
| - A Google Meet is created only when the consultation is confirmed.
| - If a meeting_link already exists, it is reused.
| - This prevents duplicate Meet spaces.
|
*/

if (
    strcasecmp($consultationMethod, 'Google Meet') === 0 &&
    $meetingLink === ''
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Load Stored Google Refresh Token
        |--------------------------------------------------------------------------
        */

        $oauthStmt = $consultationPdo->prepare("
            SELECT refresh_token
            FROM google_meet_oauth
            WHERE provider = 'google_meet'
            LIMIT 1
        ");

        $oauthStmt->execute();

        $oauth = $oauthStmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$oauth ||
            empty($oauth['refresh_token'])
        ) {
            throw new RuntimeException(
                'Google Meet is not connected. Please connect the Google Meet account first.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Refresh Google Access Token
        |--------------------------------------------------------------------------
        */

        $accessToken = refreshGoogleMeetAccessToken(
            (string) $oauth['refresh_token']
        );


        /*
        |--------------------------------------------------------------------------
        | Create Google Meet Space
        |--------------------------------------------------------------------------
        */

        $meetingLink = createGoogleMeetSpace(
            $accessToken
        );


        /*
        |--------------------------------------------------------------------------
        | Save Meeting Link
        |--------------------------------------------------------------------------
        */

        $updateMeeting = $consultationPdo->prepare("
            UPDATE consultation_slots
            SET meeting_link = ?
            WHERE id = ?
            LIMIT 1
        ");

        $updateMeeting->execute([
            $meetingLink,
            (int) $request['slot_id']
        ]);

    } catch (Throwable $e) {

        error_log(
            'Google Meet creation failed for request ' .
            $id .
            ': ' .
            $e->getMessage()
        );

        die(
            'The consultation could not be confirmed because the Google Meet could not be created. ' .
            'Please try again or contact the administrator.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Create Zoom Meeting Only When Needed
|--------------------------------------------------------------------------
|
| Important:
| - A Zoom meeting is created only when the consultation is confirmed.
| - If a meeting_link already exists, it is reused.
| - This prevents duplicate Zoom meetings.
|
*/

if (
    strcasecmp($consultationMethod, 'Zoom') === 0 &&
    $meetingLink === ''
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Zoom Access Token
        |--------------------------------------------------------------------------
        */

        $zoomAccessToken = getZoomAccessToken();


        /*
        |--------------------------------------------------------------------------
        | Create Zoom Meeting
        |--------------------------------------------------------------------------
        */

        $zoomStartDateTime =
            $request['slot_date'] . ' ' .
            $request['slot_time'];

        $zoomMeeting = createZoomMeeting(
            $zoomAccessToken,
            'IT Consultancy - ' . $request['service_title'],
            $zoomStartDateTime,
            60
        );


        /*
        |--------------------------------------------------------------------------
        | Get Zoom Join URL
        |--------------------------------------------------------------------------
        */

        $meetingLink = trim(
            (string) ($zoomMeeting['join_url'] ?? '')
        );

        if ($meetingLink === '') {
            throw new RuntimeException(
                'Zoom did not return a meeting join URL.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Save Meeting Link
        |--------------------------------------------------------------------------
        */

        $updateMeeting = $consultationPdo->prepare("
            UPDATE consultation_slots
            SET meeting_link = ?
            WHERE id = ?
            LIMIT 1
        ");

        $updateMeeting->execute([
            $meetingLink,
            (int) $request['slot_id']
        ]);

    } catch (Throwable $e) {

        error_log(
            'Zoom meeting creation failed for request ' .
            $id .
            ': ' .
            $e->getMessage()
        );

        die(
            'The consultation could not be confirmed because the Zoom meeting could not be created. ' .
            'Please try again or contact the administrator.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Confirm Consultation
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $consultationPdo->prepare("
        UPDATE requests r
        INNER JOIN customers c
            ON c.id = r.customer_id
        INNER JOIN services s
            ON s.id = r.service_id
        SET r.workflow_stage = 'Consultation Confirmed'
        WHERE r.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
          AND s.demo_tenant_id = ?
          AND s.is_demo_account = 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $consultationPdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Confirmed'
        WHERE id = ?
    ");

    $stmt->execute([$id]);
}

if ($stmt->rowCount() === 0) {
    die('Consultation request could not be confirmed.');
}


/*
|--------------------------------------------------------------------------
| Record Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::add(
    $consultationPdo,
    $id,
    'CONSULTATION_CONFIRMED',
    RequestEventHelper::TYPE_SYSTEM,
    'Consultation Confirmed',
    'The consultation appointment was confirmed by the administrator.',
    RequestEventHelper::SOURCE_ADMINISTRATOR,
    null
);


/*
|--------------------------------------------------------------------------
| Send Confirmation Email
|--------------------------------------------------------------------------
*/

sendEmail(
    $request['email'],
    'Consultation Confirmed',
    "
    <h2>Hello " . htmlspecialchars(
        $request['name'],
        ENT_QUOTES,
        'UTF-8'
    ) . ",</h2>

    <p>
        Your consultation has been confirmed.
    </p>

    <p>
        <strong>Service:</strong><br>
        " . htmlspecialchars(
            $request['service_title'],
            ENT_QUOTES,
            'UTF-8'
        ) . "
    </p>

    <p>
        <strong>Date:</strong><br>
        " . formatDate($request['slot_date']) . "
    </p>

    <p>
        <strong>Time:</strong><br>
        " . formatTime($request['slot_time']) . "
    </p>

    <p>
        <strong>Method:</strong><br>
        " . htmlspecialchars(
            $consultationMethod,
            ENT_QUOTES,
            'UTF-8'
        ) . "
    </p>

    <hr style='border:none;border-top:1px solid #dddddd;margin:20px 0;'>

    <h3 style='margin:0 0 12px 0;font-size:20px;'>
        🔒 Secure Meeting Access
    </h3>

    <p>
        Your secure
        <strong>" . htmlspecialchars(
            $consultationMethod,
            ENT_QUOTES,
            'UTF-8'
        ) . "</strong>
        meeting link will become available
        <strong>10 minutes before your scheduled consultation.</strong>
    </p>

    <p>
        Please log in to your customer portal and select
        <strong>Join Meeting</strong>
        when it becomes available.
    </p>

    <hr style='border:none;border-top:1px solid #dddddd;margin:30px 0;'>

    <p>
        You can manage your consultation, view updates,
        and access your meeting from your customer portal.
    </p>

    <p>
        <a
            href='" . APP_URL . "/?page=public-login'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '>
            Customer Portal
        </a>
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);


/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $consultationPdo,
    'customer',
    $request['customer_id'],
    'Consultation Confirmed',
    'Your consultation has been confirmed.',
    '?page=customer-requests'
);


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;