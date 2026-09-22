<?php

require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Determine Admin Type
|--------------------------------------------------------------------------
*/

$isDemoAdmin =
    isset($_SESSION['demo_user']) ||
    isset($_SESSION['demo_super_admin']);

$isMainAdmin = isset($_SESSION['user']);


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!$isMainAdmin && !$isDemoAdmin) {

    header("Location: ?page=login");
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $adminPdo = $demoPdo;

} else {

    require_once CONFIG_PATH . '/database.php';

    $adminPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Payment Slip ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($id <= 0) {

    header('Location: ?page=requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Payment Slip and Related Details
|--------------------------------------------------------------------------
*/

$stmt = $adminPdo->prepare("
    SELECT
        ps.id,
        ps.status AS slip_status,
        ps.request_id,
        c.id AS customer_id,
        c.name,
        c.email,
        s.title AS service_title
    FROM payment_slips ps
    JOIN customers c
        ON c.id = ps.customer_id
    JOIN requests r
        ON r.id = ps.request_id
    JOIN services s
        ON s.id = r.service_id
    WHERE ps.id = ?
");


$stmt->execute([
    $id
]);


$data = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$data) {

    die('Payment slip not found.');
}


/*
|--------------------------------------------------------------------------
| Verify Payment Slip is Still Pending
|--------------------------------------------------------------------------
*/

if (($data['slip_status'] ?? '') !== 'Pending') {

    header('Location: ?page=requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Demo Tenant Security
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin && isset($_SESSION['demo_user'])) {

    $adminTenantId =
        (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);


    $tenantStmt = $adminPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");


    $tenantStmt->execute([
        (int) $data['customer_id'],
        $adminTenantId
    ]);


    if (!$tenantStmt->fetch()) {

        die('Access denied.');
    }
}


/*
|--------------------------------------------------------------------------
| Reject Payment Slip
|--------------------------------------------------------------------------
*/

$rejectStmt = $adminPdo->prepare("
    UPDATE payment_slips
    SET status = 'Rejected'
    WHERE id = ?
      AND status = 'Pending'
");


$rejectStmt->execute([
    $id
]);


/*
|--------------------------------------------------------------------------
| Confirm Update
|--------------------------------------------------------------------------
*/

if ($rejectStmt->rowCount() !== 1) {

    header('Location: ?page=requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Return Request to Proposal Accepted
|--------------------------------------------------------------------------
*/

$requestStmt = $adminPdo->prepare("
    UPDATE requests
    SET
        workflow_stage = 'Proposal Accepted',
        status = 'Pending'
    WHERE id = ?
");


$requestStmt->execute([
    (int) $data['request_id']
]);


/*
|--------------------------------------------------------------------------
| Record Payment Rejected Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $adminPdo,
    (int) $data['request_id'],
    'PAYMENT_REJECTED',
    RequestEventHelper::TYPE_PAYMENT,
    'Payment Rejected',
    'The administrator rejected the customer payment receipt.',
    true
);


/*
|--------------------------------------------------------------------------
| Create Customer Notification
|--------------------------------------------------------------------------
*/

createNotification(
    $adminPdo,
    'customer',
    (int) $data['customer_id'],
    'Payment Rejected',
    'Your payment slip was rejected. Please upload a new payment receipt.',
    '?page=customer-requests'
);


/*
|--------------------------------------------------------------------------
| Send Customer Email
|--------------------------------------------------------------------------
*/

$loginUrl =
    APP_URL .
    '/?page=public-login';


sendEmail(
    $data['email'],
    'Payment Rejected',
    "
    <h2>Hello " .
    htmlspecialchars(
        $data['name'],
        ENT_QUOTES,
        'UTF-8'
    ) .
    ",</h2>

    <p>We reviewed the payment receipt you submitted for:</p>

    <p>
        <strong>Service:</strong>
        " .
        htmlspecialchars(
            $data['service_title'],
            ENT_QUOTES,
            'UTF-8'
        ) .
    "
    </p>

    <p>
        Unfortunately, we could not verify the payment.
    </p>

    <p>
        Please log in to your account and upload a new payment receipt.
    </p>

    <p>
        <a
            href='" . htmlspecialchars(
                $loginUrl,
                ENT_QUOTES,
                'UTF-8'
            ) . "'
            style='
                background:#0d6efd;
                color:white;
                padding:10px 20px;
                text-decoration:none;
                border-radius:5px;
                display:inline-block;
            '
        >
            Login Now
        </a>
    </p>

    <p>
        If you believe this is an error, please contact us.
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ?page=requests');
exit;