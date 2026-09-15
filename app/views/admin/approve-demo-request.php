<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/email.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid Demo request.');
}

/*
|--------------------------------------------------------------------------
| Load Demo Request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        company_name,
        company_domain,
        status
    FROM demo_requests
    WHERE id = ?
");

$stmt->execute([$id]);

$request = $stmt->fetch();

if (!$request) {
    die('Demo request not found.');
}

/*
|--------------------------------------------------------------------------
| Only Confirmed Requests Can Be Approved
|--------------------------------------------------------------------------
*/

if ($request['status'] !== 'Confirmed') {
    die('This Demo request cannot be approved in its current status.');
}

/*
|--------------------------------------------------------------------------
| Approve Request
|--------------------------------------------------------------------------
*/

$approvedAt = date('Y-m-d H:i:s');

$approvedBy =
    $_SESSION['user']['email']
    ?? $_SESSION['user']
    ?? 'Admin';

$customerConfirmationToken =
    bin2hex(random_bytes(32));

$customerConfirmationExpiresAt =
    date('Y-m-d H:i:s', time() + 15 * 60);

$stmt = $pdo->prepare("
    UPDATE demo_requests
    SET
        status = 'Approved',
        approved_at = ?,
        approved_by = ?,
        confirmation_token = ?,
        confirmation_expires_at = ?
    WHERE id = ?
      AND status = 'Confirmed'
");

$stmt->execute([
    $approvedAt,
    $approvedBy,
    $customerConfirmationToken,
    $customerConfirmationExpiresAt,
    $id
]);

if ($stmt->rowCount() !== 1) {
    die('The Demo request could not be approved.');
}

/*
|--------------------------------------------------------------------------
| Customer Confirmation Email
|--------------------------------------------------------------------------
*/

$confirmationLink =
    rtrim(APP_URL, '/')
    . '/index.php?page=confirm-demo-customer&token='
    . urlencode($customerConfirmationToken);

$subject = 'Demo Request Approved - Confirm Your Request';

$body = "
    <h2>Demo Request Approved</h2>

    <p>
        Hello " . htmlspecialchars($request['full_name']) . ",
    </p>

    <p>
        Your Demo request for
        <strong>" . htmlspecialchars($request['company_name'] ?? '') . "</strong>
        has been approved.
    </p>

    <p>
        Before we create your temporary Demo environment,
        please confirm that you are the person who requested this Demo.
    </p>

    <p style='margin:30px 0;'>
        <a
            href='" . htmlspecialchars($confirmationLink) . "'
            style='
                display:inline-block;
                padding:12px 20px;
                background:#198754;
                color:#ffffff;
                text-decoration:none;
                border-radius:5px;
            '>
            Confirm I Am the Requester
        </a>
    </p>

    <p>
        This confirmation link is valid for
        <strong>15 minutes</strong>.
    </p>

    <p>
        If you did not request this Demo, you can safely ignore this email.
    </p>

    <p>
        Kind regards,<br>
        <strong>" . COMPANY_NAME . "</strong>
    </p>
";

if (!sendEmail($request['email'], $subject, $body)) {

    /*
     * Roll back the approval if the customer confirmation
     * email could not be sent.
     */

    $stmt = $pdo->prepare("
        UPDATE demo_requests
        SET
            status = 'Confirmed',
            approved_at = NULL,
            approved_by = NULL,
            confirmation_token = NULL,
            confirmation_expires_at = NULL
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    die(
        'The Demo request could not be approved because '
        . 'the customer confirmation email could not be sent.'
    );
}

/*
|--------------------------------------------------------------------------
| Return to Demo Request
|--------------------------------------------------------------------------
*/

header(
    'Location: ?page=view-demo-request&id='
    . $id
);

exit;