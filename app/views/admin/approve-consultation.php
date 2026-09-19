<?php

require_once APP_PATH . '/helpers/RequestEventHelper.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';

requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid consultation request.');
}

/*
|--------------------------------------------------------------------------
| Select Database
|--------------------------------------------------------------------------
| Main Admin:
|   - Uses the existing Main System database ($pdo)
|   - Uses the existing Main Admin session
|
| Demo Admin:
|   - Uses the Demo database ($demoPdo)
|   - Is restricted to the current Demo tenant
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);

$approvePdo = $pdo;
$demoTenantId = null;

if ($isDemoAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $approvePdo = $demoPdo;
    $demoTenantId = (int) ($_SESSION['demo_user']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}

/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
| MAIN:
|   Uses the original Main System request/customer lookup.
|
| DEMO:
|   The request must belong to the current Demo tenant.
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $approvePdo->prepare("
        SELECT
            customers.name,
            customers.email
        FROM requests
        INNER JOIN customers
            ON customers.id = requests.customer_id
        WHERE requests.id = ?
          AND customers.demo_tenant_id = ?
          AND customers.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    // Original Main Admin lookup preserved.
    $stmt = $approvePdo->prepare("
        SELECT
            customers.name,
            customers.email
        FROM requests
        JOIN customers
            ON customers.id = requests.customer_id
        WHERE requests.id = ?
    ");

    $stmt->execute([
        $id
    ]);
}

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Consultation request not found.');
}

/*
|--------------------------------------------------------------------------
| Approve Consultation
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Confirmed'
        WHERE id = ?
          AND customer_id IN (
              SELECT id
              FROM customers
              WHERE demo_tenant_id = ?
                AND is_demo_account = 1
          )
    ");

    $stmt->execute([
        $id,
        $demoTenantId
    ]);

} else {

    // Original Main Admin update preserved.
    $stmt = $approvePdo->prepare("
        UPDATE requests
        SET workflow_stage = 'Consultation Confirmed'
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);
}

if ($stmt->rowCount() !== 1) {
    die('The consultation request could not be approved.');
}

/*
|--------------------------------------------------------------------------
| Audit Event
|--------------------------------------------------------------------------
*/

RequestEventHelper::addCurrentUser(
    $approvePdo,
    (int) $id,
    'CONSULTATION_REQUEST_APPROVED',
    RequestEventHelper::TYPE_CONSULTATION,
    'Consultation Request Approved',
    'The administrator approved the consultation request. The customer may now schedule the consultation.',
    true
);

/*
|--------------------------------------------------------------------------
| Notify Customer
|--------------------------------------------------------------------------
*/

if ($customer && !empty($customer['email'])) {

    sendEmail(
        $customer['email'],
        'Consultation Confirmed',
        "
        <h2>Hello " . htmlspecialchars(
            $customer['name'],
            ENT_QUOTES,
            'UTF-8'
        ) . ",</h2>

        <p>Your consultation request has been approved.</p>

        <p>Please log in and schedule your consultation.</p>

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
                '
            >
                Login Now
            </a>
        </p>

        <p>IT Consultancy Team</p>
        "
    );
}
