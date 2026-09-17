<?php

/*
|--------------------------------------------------------------------------
| Create Demo
|--------------------------------------------------------------------------
| Step 1.11
|
| Creates:
| - Demo tenant (already created in Step 1.9)
| - Company Demo Admin (already created in Step 1.10)
| - Demo Customer
| - Demo Agent
|
| Usernames are based on the company domain:
| - loops_admin
| - loops_customer
| - loops_agent
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Administrator Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connections
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/demo-database.php';


/*
|--------------------------------------------------------------------------
| Get Demo Request ID
|--------------------------------------------------------------------------
*/

$requestId = (int)($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid Demo request ID.');
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
        status,
        customer_confirmed_at,
        demo_created_at
    FROM demo_requests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die('Demo request not found.');
}


/*
|--------------------------------------------------------------------------
| Verify Request Status
|--------------------------------------------------------------------------
*/

if ($request['status'] !== 'Customer Confirmed') {
    die(
        'This Demo request cannot be provisioned. '
        . 'Current status: '
        . htmlspecialchars($request['status'])
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Re-Provisioning
|--------------------------------------------------------------------------
*/

if (!empty($request['demo_created_at'])) {
    die('This Demo request has already been provisioned.');
}


/*
|--------------------------------------------------------------------------
| Validate Request Information
|--------------------------------------------------------------------------
*/

$companyDomain  = trim($request['company_domain'] ?? '');
$companyName    = trim($request['company_name'] ?? '');
$registeredEmail = trim($request['email'] ?? '');
$customerName   = trim($request['full_name'] ?? '');

if ($companyDomain === '') {
    die('The Demo request does not contain a company domain.');
}

if ($registeredEmail === '') {
    die('The Demo request does not contain a registered email address.');
}

if ($customerName === '') {
    $customerName = 'Demo Customer';
}

if ($companyName === '') {
    $companyName = 'Demo Company';
}


/*
|--------------------------------------------------------------------------
| Find Existing Tenant
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        company_name,
        company_domain,
        registered_email,
        demo_request_id,
        started_at,
        expires_at,
        status
    FROM demo_tenants
    WHERE demo_request_id = ?
    LIMIT 1
");

$stmt->execute([$requestId]);

$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die(
        'Demo tenant not found. '
        . 'Complete Step 1.9 before creating Demo accounts.'
    );
}

$tenantId = (int)$tenant['id'];


/*
|--------------------------------------------------------------------------
| Verify Tenant Domain
|--------------------------------------------------------------------------
*/

if ($tenant['company_domain'] !== $companyDomain) {
    die('Demo tenant domain does not match the Demo request.');
}


/*
|--------------------------------------------------------------------------
| Generate Stable Usernames
|--------------------------------------------------------------------------
|
| Example:
| loopsautomation.com
|
| becomes:
| loops_admin
| loops_customer
| loops_agent
|
|--------------------------------------------------------------------------
*/

$domainWithoutWww = preg_replace(
    '/^www\./i',
    '',
    strtolower($companyDomain)
);

$domainBase = preg_replace(
    '/[^a-z0-9]+/',
    '_',
    $domainWithoutWww
);

$domainBase = trim($domainBase, '_');

if ($domainBase === '') {
    die('Unable to generate Demo usernames from the company domain.');
}


/*
|--------------------------------------------------------------------------
| Generate Stable Demo Usernames
|--------------------------------------------------------------------------
|
| The username is based on the company domain,
| without the final domain extension.
|
| Examples:
|
| loopsautomation.com
| → loopsautomation
| → loopsautomation_admin
| → loopsautomation_customer
| → loopsautomation_agent
|
| acme.com
| → acme
| → acme_admin
| → acme_customer
| → acme_agent
|
|--------------------------------------------------------------------------
*/

$domainWithoutWww = preg_replace(
    '/^www\./i',
    '',
    strtolower($companyDomain)
);

$domainParts = explode('.', $domainWithoutWww);

if (count($domainParts) < 2) {
    die('The company domain is not valid.');
}

/*
|--------------------------------------------------------------------------
| Remove the final domain extension
|--------------------------------------------------------------------------
*/

array_pop($domainParts);

$usernameBase = implode('_', $domainParts);

/*
|--------------------------------------------------------------------------
| Sanitize username base
|--------------------------------------------------------------------------
*/

$usernameBase = preg_replace(
    '/[^a-z0-9_]+/',
    '_',
    $usernameBase
);

$usernameBase = trim($usernameBase, '_');

if ($usernameBase === '') {
    die('Unable to generate Demo usernames from the company domain.');
}


/*
|--------------------------------------------------------------------------
| Final Demo Usernames
|--------------------------------------------------------------------------
*/

$adminUsername    = $usernameBase . '_admin';
$customerUsername = $usernameBase . '_customer';
$agentUsername    = $usernameBase . '_agent';



/*
|--------------------------------------------------------------------------
| Check Existing Company Demo Admin
|--------------------------------------------------------------------------
|
| Admin was created in Step 1.10.
| We reuse it and do not create another Admin.
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        email,
        demo_tenant_id,
        is_demo_account,
        is_super_admin
    FROM users
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND is_super_admin = 0
    LIMIT 1
");

$stmt->execute([$tenantId]);

$existingAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingAdmin) {
    die(
        'Company Demo Admin was not found. '
        . 'Complete Step 1.10 before creating Customer and Agent.'
    );
}


/*
|--------------------------------------------------------------------------
| Verify Existing Admin Username
|--------------------------------------------------------------------------
*/

if ($existingAdmin['username'] !== $adminUsername) {
    die(
        'The existing Company Demo Admin username does not match '
        . 'the agreed username convention. Expected: '
        . htmlspecialchars($adminUsername)
        . ' | Found: '
        . htmlspecialchars($existingAdmin['username'] ?? '')
    );
}

$adminId = (int)$existingAdmin['id'];


/*
|--------------------------------------------------------------------------
| Check Existing Demo Customer
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        email,
        demo_tenant_id,
        is_demo_account,
        force_password_change
    FROM customers
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND username = ?
    LIMIT 1
");

$stmt->execute([
    $tenantId,
    $customerUsername
]);

$existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Check Existing Demo Agent
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        email,
        demo_tenant_id,
        is_demo_account,
        force_password_change,
        status,
        active
    FROM agents
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND username = ?
    LIMIT 1
");

$stmt->execute([
    $tenantId,
    $agentUsername
]);

$existingAgent = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Prevent Duplicate Provisioning
|--------------------------------------------------------------------------
*/

if ($existingCustomer) {
    die(
        'Demo Customer already exists for this tenant. '
        . 'Username: '
        . htmlspecialchars($existingCustomer['username'])
    );
}

if ($existingAgent) {
    die(
        'Demo Agent already exists for this tenant. '
        . 'Username: '
        . htmlspecialchars($existingAgent['username'])
    );
}


/*
|--------------------------------------------------------------------------
| Check Username Uniqueness
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT id
    FROM customers
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$customerUsername]);

if ($stmt->fetch()) {
    die(
        'The Demo Customer username is already in use: '
        . htmlspecialchars($customerUsername)
    );
}


$stmt = $demoPdo->prepare("
    SELECT id
    FROM agents
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$agentUsername]);

if ($stmt->fetch()) {
    die(
        'The Demo Agent username is already in use: '
        . htmlspecialchars($agentUsername)
    );
}

/*
|--------------------------------------------------------------------------
| Create Demo Customer + Demo Agent
|--------------------------------------------------------------------------
*/

try {

    $demoPdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Create Demo Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $demoPdo->prepare("
    INSERT INTO customers
    (
        username,
        name,
        email,
        phone,
        company,
        password,
        demo_tenant_id,
        is_demo_account,
        force_password_change
    )
    VALUES
(?, ?, NULL, NULL, ?, NULL, ?, 1, 0)
");

$stmt->execute([
    $customerUsername,
    $customerName,
    $companyName,
    $tenantId
]);

    $customerId = (int)$demoPdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Create Demo Agent
    |--------------------------------------------------------------------------
    */

    $stmt = $demoPdo->prepare("
    INSERT INTO agents
    (
        username,
        name,
        email,
        password,
        demo_tenant_id,
        is_demo_account,
        force_password_change,
        position,
        status,
        active
    )
    VALUES(?, ?, NULL, NULL, ?, 1, 0, ?, 'Active', 1)");

$stmt->execute([
    $agentUsername,
    'Demo Agent',
    $tenantId,
    'IT Consultant'
]);

    $agentId = (int)$demoPdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $demoPdo->commit();

} catch (PDOException $e) {

    if ($demoPdo->inTransaction()) {
        $demoPdo->rollBack();
    }

    die(
        'The Demo Customer and Demo Agent could not be created. '
        . 'Please check the Demo database.'
    );
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Demo Accounts Created</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body class="bg-light">


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Demo Accounts Created
                    </h4>

                </div>


                <div class="card-body">


                    <div class="alert alert-success">

                        <strong>
                            Demo Customer and Demo Agent were created successfully.
                        </strong>

                    </div>


                    <table class="table table-bordered">

                        <tr>
                            <th width="40%">
                                Tenant ID
                            </th>

                            <td>
                                <?= $tenantId ?>
                            </td>
                        </tr>


                        <tr>
                            <th>
                                Company Demo Admin
                            </th>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($adminUsername) ?>
                                </strong>
                            </td>
                        </tr>


                        <tr>
                            <th>
                                Admin ID
                            </th>

                            <td>
                                <?= $adminId ?>
                            </td>
                        </tr>


                        <tr>
                            <th>
                                Demo Customer
                            </th>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($customerUsername) ?>
                                </strong>

                                <br>

                                ID:
                                <?= $customerId ?>
                            </td>
                        </tr>


                        <tr>
                            <th>
                                Demo Agent
                            </th>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($agentUsername) ?>
                                </strong>

                                <br>

                                ID:
                                <?= $agentId ?>
                            </td>
                        </tr>

                    </table>


                    <div class="alert alert-info">

                        <strong>Demo Customer and Demo Agent setup is pending.</strong>

                        <div class="small mt-2">
                            The Company Demo Admin will provide the Customer and Agent
                            email addresses during Demo Setup.
                        </div>

                    </div>


                    <div class="alert alert-info">

                        <strong>
                            Step 1.11 complete.
                        </strong>

                        <br>

                        The Demo Tenant now has all three Demo accounts:

                        <br><br>

                        <code><?= htmlspecialchars($adminUsername) ?></code><br>
                        <code><?= htmlspecialchars($customerUsername) ?></code><br>
                        <code><?= htmlspecialchars($agentUsername) ?></code>

                    </div>


                    <div class="text-center mt-4">

                        <a
                            href="?page=view-demo-request&id=<?= (int)$request['id'] ?>"
                            class="btn btn-secondary"
                        >
                            Back to Demo Request
                        </a>

                    </div>


                </div>

            </div>

        </div>

    </div>

</div>


</body>

</html>