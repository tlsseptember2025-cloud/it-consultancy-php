<?php
// CSRF protection for this state-changing GET action.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';
require_once HELPER_PATH . '/notifications.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';


/*
|--------------------------------------------------------------------------
| Admin Context
|--------------------------------------------------------------------------
*/

requireAdminLogin();

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin');
    exit;
}


/*
|--------------------------------------------------------------------------
| Select Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $adminPdo = $demoPdo;

    $adminTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id'] ?? 0
    );

    if ($adminTenantId <= 0) {
        unset($_SESSION['demo_user']);
        header('Location: ?page=demo-login');
        exit;
    }

} else {

    require_once CONFIG_PATH . '/database.php';

    $adminPdo = $pdo;

    $adminTenantId = 0;
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
      AND (
          ? = 0
          OR (
              c.demo_tenant_id = ?
              AND c.is_demo_account = 1
              AND s.demo_tenant_id = ?
          )
      )
");


$stmt->execute([
    $id,
    $adminTenantId,
    $adminTenantId,
    $adminTenantId
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
    UPDATE payment_slips ps
    SET status = 'Rejected'
    WHERE ps.id = ?
      AND ps.status = 'Pending'
      AND (
          ? = 0
          OR EXISTS (
              SELECT 1
              FROM customers c
              WHERE c.id = ps.customer_id
                AND c.demo_tenant_id = ?
                AND c.is_demo_account = 1
          )
      )
");


$rejectStmt->execute([
    $id,
    $adminTenantId,
    $adminTenantId
]);


/*
|--------------------------------------------------------------------------
| Confirm Update
|--------------------------------------------------------------------------
*/

if (!$rejectStmt) {

    header('Location: ?page=requests');
    exit;
}


/*
|--------------------------------------------------------------------------
| Return Request to Proposal Accepted
|--------------------------------------------------------------------------
*/

$requestStmt = $adminPdo->prepare("
    UPDATE requests r
    SET
        workflow_stage = 'Proposal Accepted',
        status = 'Pending'
    WHERE r.id = ?
      AND r.workflow_stage <> 'Proposal Accepted'
      AND (
          ? = 0
          OR EXISTS (
              SELECT 1
              FROM customers c
              WHERE c.id = r.customer_id
                AND c.demo_tenant_id = ?
                AND c.is_demo_account = 1
          )
      )
");


$requestStmt->execute([
    (int) $data['request_id'],
    $adminTenantId,
    $adminTenantId
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