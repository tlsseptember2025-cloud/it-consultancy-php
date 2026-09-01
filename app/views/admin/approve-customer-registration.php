<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/email.php';


/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$customerId = (int) ($_GET['id'] ?? 0);

if ($customerId <= 0) {

    $_SESSION['error'] = 'Invalid customer registration.';
    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        registration_status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$customer) {

    $_SESSION['error'] = 'Customer registration not found.';
    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Registration Is Awaiting Approval
|--------------------------------------------------------------------------
*/

if (
    $customer['registration_status']
    !== 'Pending Admin Approval'
) {

    $_SESSION['error'] =
        'This customer registration is not awaiting approval.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Approve Customer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE customers

    SET
        registration_status = 'Approved',
        approved_at = NOW(),
        rejection_reason = NULL,
        rejected_at = NULL

    WHERE
        id = ?
        AND registration_status = 'Pending Admin Approval'
");

$stmt->execute([
    $customerId
]);


if ($stmt->rowCount() !== 1) {

    $_SESSION['error'] =
        'Unable to approve the customer registration.';

    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Send Welcome Email
|--------------------------------------------------------------------------
*/

$subject = 'Welcome to ' . COMPANY_NAME;

$body = "
    <h2>Welcome to " . COMPANY_NAME . "</h2>

    <p>
        Hello " . htmlspecialchars($customer['name']) . ",
    </p>

    <p>
        Your customer registration has been approved.
    </p>

    <p>
        You can now log in to your customer portal using the
        email address and password you created during registration.
    </p>

    <p>
        <a
            href='" . APP_URL . "/index.php?page=public-login'
            style='
                display:inline-block;
                padding:12px 22px;
                background:#0d6efd;
                color:#ffffff;
                text-decoration:none;
                border-radius:6px;
                font-weight:600;
            '>
            Login to Customer Portal
        </a>
    </p>

    <p>
        We look forward to assisting you with your IT requirements.
    </p>

    <br>

    <p>
        Kind regards,<br>
        <strong>" . COMPANY_NAME . "</strong>
    </p>
";


$emailSent = sendEmail(
    $customer['email'],
    $subject,
    $body
);


/*
|--------------------------------------------------------------------------
| Admin Result
|--------------------------------------------------------------------------
*/

if ($emailSent) {

    $_SESSION['success'] =
        'Customer registration approved successfully. Welcome email sent.';

} else {

    $_SESSION['success'] =
        'Customer registration approved successfully, but the welcome email could not be sent.';
}


header('Location: ?page=customers');
exit;