<?php

require_once APP_PATH . '/helpers/email.php';
require_once APP_PATH . '/helpers/VerificationEmailHelper.php';
require_once APP_PATH . '/helpers/contact_history_helper.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$requestId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

/*
|--------------------------------------------------------------------------
| Load Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
        c.phone,
        s.title AS service_name
    FROM requests r
    INNER JOIN customers c
        ON c.id = r.customer_id
    INNER JOIN services s
        ON s.id = r.service_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Load Consultation Booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        cb.id AS booking_id,
        cb.agent_id,
        a.name AS agent_name,
        cs.slot_date,
        cs.slot_time,
        cs.consultation_method,
        cs.meeting_link
    FROM consultation_bookings cb
    INNER JOIN agents a
        ON a.id = cb.agent_id
    LEFT JOIN consultation_slots cs
        ON cs.id = cb.slot_id
    WHERE cb.request_id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die('Consultation booking not found.');
}

$consultation = array_merge($consultation, $booking);
/*
|--------------------------------------------------------------------------
| Customer Contact Workflow
|--------------------------------------------------------------------------
*/

$contactAttempts = (int)($consultation['contact_attempts'] ?? 0);

$canRetryContact =
    $contactAttempts < MAX_CONTACT_ATTEMPTS;

$maximumAttemptsReached =
    $contactAttempts >= MAX_CONTACT_ATTEMPTS;

/*
|--------------------------------------------------------------------------
| Load View
|--------------------------------------------------------------------------
*/

if (isset($_POST['approve_contact'])) {

    $adminInstruction = trim($_POST['admin_instruction'] ?? '');

    if ($adminInstruction === '') {
        die('Please enter instructions for the assigned agent.');
    }

    /*
    |--------------------------------------------------------------------------
    | Update Request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE requests
        SET
            admin_instruction = ?,
            workflow_stage = ?
        WHERE id = ?
    ");

    $stmt->execute([
    $adminInstruction,
    'Customer Contact',
    $consultation['id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Get Logged-in Administrator
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['user']]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        die('Administrator not found.');
    }

    $adminId = $admin['id'];

    addContactHistory(
    $pdo,
    $consultation['id'],
    null,
    $adminId,
    'admin',
    'contact_retry_approved',
    $adminInstruction
    );

    /*
    |--------------------------------------------------------------------------
    | Save Approval History
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO consultation_customer_contact_approvals
        (
            request_id,
            booking_id,
            agent_id,
            approved_by,
            admin_instruction
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $consultation['id'],
        $consultation['booking_id'],
        $consultation['agent_id'],
        $adminId,
        $adminInstruction
    ]);

    /*
    |--------------------------------------------------------------------------
    | Log Request Event
    |--------------------------------------------------------------------------
    */

    RequestEventHelper::add(

    $pdo,

    $consultation['id'],

    RequestEventHelper::EVENT_CONTACT_ATTEMPT_APPROVED,

    RequestEventHelper::TYPE_CONTACT,

    'Administrator Approved Customer Contact',

    'The administrator approved another customer contact attempt.',

    RequestEventHelper::SOURCE_ADMINISTRATOR,

    $adminId

    );

    header('Location: ?page=requests&success=customer-contact-approved');
    exit;
}

if (isset($_POST['send_contact_email'])) {

    $stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user']]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Administrator not found.');
}

$adminId = $admin['id'];


    $stmt = $pdo->prepare("
    UPDATE requests
    SET
        verification_email_count = 1,
        first_verification_email_at = NOW(),
        customer_response_deadline = DATE_ADD(NOW(), INTERVAL 90 DAY),
        workflow_stage = ?,
        job_status = ?
    WHERE id = ?
    ");

    $stmt->execute([
        'Awaiting Customer Response',
        'Pending',
        $consultation['id']
    ]);

    $subject = 'Action Required: We Could Not Reach You Regarding Your Consultation';

$body = '

<h2>Customer Contact Verification</h2>

<p>Dear <strong>' . htmlspecialchars($consultation['customer_name']) . '</strong>,</p>

<p>
We recently attempted to contact you regarding your scheduled consultation with
<strong>WAHBIB Consultancy</strong>, but unfortunately we were unable to reach you by telephone.
</p>

<h3>Consultation Details</h3>

<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; width:100%;">

<tr>
    <td><strong>Request ID</strong></td>
    <td>#' . $consultation['id'] . '</td>
</tr>

<tr>
    <td><strong>Service</strong></td>
    <td>' . htmlspecialchars($consultation['service_name']) . '</td>
</tr>

<tr>
    <td><strong>Consultation Date</strong></td>
    <td>' . date('F j, Y', strtotime($consultation['slot_date'])) . '</td>
</tr>

<tr>
    <td><strong>Consultation Time</strong></td>
    <td>' . date('g:i A', strtotime($consultation['slot_time'])) . '</td>
</tr>

<tr>
    <td><strong>Consultation Method</strong></td>
    <td>' . htmlspecialchars($consultation['consultation_method']) . '</td>
</tr>

</table>

<br>

<p>
To continue processing your consultation request, please reply to this email or contact us as soon as possible.
</p>

<p>
If you are no longer interested in this consultation, please let us know so we can update your request accordingly.
</p>

<hr>

<p>
<strong>WAHBIB Consultancy</strong><br>
Professional IT Consultancy & Digital Solutions<br><br>

Email: info@wahbibconsultancy.com<br>
Website: https://wahbibconsultancy.com
</p>

';

$emailSent = sendEmail(
    $consultation['email'],
    $subject,
    $body
);

if (!$emailSent) {
    die('Verification email could not be sent.');
}

addContactHistory(
    $pdo,
    $consultation['id'],
    null,
    $adminId,
    'admin',
    'verification_email_1_sent',
    'Administrator initiated the first contact verification email.'
);

    header('Location: ?page=requests&success=verification-email-stage1');
    exit;

}

require VIEW_PATH . '/admin/admin-contact-customer.php';
#ok