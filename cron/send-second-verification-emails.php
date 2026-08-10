<?php

define('BASE_PATH', dirname(__DIR__));

define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/views');

require_once CONFIG_PATH . '/settings.php';
require_once CONFIG_PATH . '/database.php';

require_once APP_PATH . '/helpers/email.php';
require_once APP_PATH . '/helpers/contact_history_helper.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

echo "Starting second verification email process...\n";

$stmt = $pdo->prepare("
    SELECT
        r.*,
        c.name AS customer_name,
        c.email,
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
        r.workflow_stage = 'Awaiting Customer Response'
        AND r.verification_email_count = 1
        AND r.second_verification_email_at IS NULL
        AND r.first_verification_email_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)
");

$stmt->execute();

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($requests) . " request(s).\n";

foreach ($requests as $consultation) {

    echo "Processing Request #" . $consultation['id'] . "...\n";

    $subject = 'Second Reminder: Please Contact Us Regarding Your Consultation';

    $body = '

    <h2>Second Contact Verification Reminder</h2>

    <p>Dear <strong>' . htmlspecialchars($consultation['customer_name']) . '</strong>,</p>

    <p>
    This is a second reminder regarding your consultation request with
    <strong>WAHBIB Consultancy</strong>.
    </p>

    <p>
    We previously attempted to contact you but have not yet received a response.
    Please contact us or reply to this email as soon as possible.
    </p>

    ';

    $emailSent = sendEmail(
        $consultation['email'],
        $subject,
        $body
    );

    if (!$emailSent) {

        echo "Failed sending Request #" . $consultation['id'] . "\n";

        continue;
    }

    echo "Email sent successfully.\n";

    $stmt = $pdo->prepare("
    UPDATE requests
    SET
        verification_email_count = 2,
        second_verification_email_at = NOW()
    WHERE id = ?
");

$stmt->execute([
    $consultation['id']
]);

addContactHistory(
    $pdo,
    $consultation['id'],
    null,
    null,
    'system',
    'verification_email_2_sent',
    'Automatic second verification email sent.'
);

RequestEventHelper::add(

    $pdo,

    $consultation['id'],

    'VERIFICATION_EMAIL_2_SENT',

    RequestEventHelper::TYPE_CONTACT,

    'Second Verification Email Sent',

    'The system automatically sent the second verification email.',

    RequestEventHelper::SOURCE_SYSTEM,

    null,
    true

);

echo "Database updated.\n";

}