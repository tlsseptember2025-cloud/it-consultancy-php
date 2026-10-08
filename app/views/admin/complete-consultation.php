<?php
// CSRF protection for this state-changing GET action.
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$submittedCsrfToken = $_GET['csrf_token'] ?? '';
if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}


$isDemoAdmin = isset($_SESSION['demo_user']);

if ($isDemoAdmin) {

    requireDemoAdmin();

    require_once CONFIG_PATH . '/demo-database.php';

    $completePdo = $demoPdo;

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

    $completePdo = $pdo;

} else {

    header('Location: ?page=login');
    exit;
}

require_once HELPER_PATH . '/email.php';
require_once APP_PATH . '/helpers/RequestEventHelper.php';

$id = (int) ($_GET['id'] ?? 0);

if ($isDemoAdmin) {

    $requestStmt = $completePdo->prepare("
        SELECT
            requests.*,
            customers.name,
            customers.email
        FROM requests
        INNER JOIN customers
            ON customers.id = requests.customer_id
        INNER JOIN services
            ON services.id = requests.service_id
        WHERE requests.id = ?
          AND customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
          AND services.demo_tenant_id = ?
        LIMIT 1
    ");

    $requestStmt->execute([
        $id,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $requestStmt = $completePdo->prepare("
        SELECT
            requests.*,
            customers.name,
            customers.email
        FROM requests
        INNER JOIN customers
            ON customers.id = requests.customer_id
        WHERE requests.id = ?
        LIMIT 1
    ");

    $requestStmt->execute([$id]);
}

$request = $requestStmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header(
        'Location: ?page=' . (
            $isDemoAdmin ? 'dashboard' : 'requests'
        )
    );
    exit;
}

if ($isDemoAdmin) {

    $stmt = $completePdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Completed'
        WHERE id = ?
          AND customer_id IN (
              SELECT id
              FROM customers
              WHERE demo_tenant_id = ?
                AND is_demo_account = 1
          )
          AND service_id IN (
              SELECT id
              FROM services
              WHERE demo_tenant_id = ?
                AND is_demo_account = 1
          )
    ");

    $stmt->execute([
        $id,
        $demoTenantId,
        $demoTenantId
    ]);

} else {

    $stmt = $completePdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Completed'
        WHERE id = ?
    ");

    $stmt->execute([$id]);
}

/*
|--------------------------------------------------------------------------
| Record Consultation Completed Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $completePdo,
    $id,
    RequestEventHelper::EVENT_CONSULTATION_COMPLETED,
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Completed',
    'The consultation has been completed.',
    true
);

sendEmail(
    $request['email'],
    'Consultation Completed',
    "
    <h2>Hello {$request['name']},</h2>

    <p>
        Your consultation has been completed.
    </p>

    <p>
        We are now preparing your quotation/proposal.
    </p>

    <p>
        You will receive another email once it is ready.
    </p>

    <p>
        IT Consultancy Team
    </p>
    "
);

header('Location: ?page=requests');
exit;