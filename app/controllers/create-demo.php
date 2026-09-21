<?php

/*
|--------------------------------------------------------------------------
| Create Demo
|--------------------------------------------------------------------------
| Step 1.11
|
| Creates:
| - Demo Customer
| - Demo Agent 1
| - Demo Agent 2
|
| Company Demo Admin was already created in Step 1.10.
|
| Usernames are based on the company domain:
| - loopsautomation_admin
| - loopsautomation_customer
| - loopsautomation_agent1
| - loopsautomation_agent2
|
| Passwords and email addresses are NOT assigned here.
| They are assigned during Demo Setup.
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

$companyDomain   = trim($request['company_domain'] ?? '');
$companyName     = trim($request['company_name'] ?? '');
$registeredEmail = trim($request['email'] ?? '');
$customerName    = trim($request['full_name'] ?? '');

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
| Generate Stable Username Base
|--------------------------------------------------------------------------
|
| Example:
|
| loopsautomation.com
| → loopsautomation
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
| Remove Final Domain Extension
|--------------------------------------------------------------------------
*/

array_pop($domainParts);

$usernameBase = implode('_', $domainParts);


/*
|--------------------------------------------------------------------------
| Sanitize Username Base
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

$adminUsername     = $usernameBase . '_admin';
$customerUsername  = $usernameBase . '_customer';
$agent1Username    = $usernameBase . '_agent1';
$agent2Username    = $usernameBase . '_agent2';


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
        . 'Complete Step 1.10 before creating Customer and Agents.'
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
| Check Existing Demo Agent 1
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
    $agent1Username
]);

$existingAgent1 = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Check Existing Demo Agent 2
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
    $agent2Username
]);

$existingAgent2 = $stmt->fetch(PDO::FETCH_ASSOC);


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

if ($existingAgent1) {
    die(
        'Demo Agent 1 already exists for this tenant. '
        . 'Username: '
        . htmlspecialchars($existingAgent1['username'])
    );
}

if ($existingAgent2) {
    die(
        'Demo Agent 2 already exists for this tenant. '
        . 'Username: '
        . htmlspecialchars($existingAgent2['username'])
    );
}


/*
|--------------------------------------------------------------------------
| Check Customer Username Uniqueness
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


/*
|--------------------------------------------------------------------------
| Check Agent 1 Username Uniqueness
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT id
    FROM agents
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$agent1Username]);

if ($stmt->fetch()) {
    die(
        'The Demo Agent 1 username is already in use: '
        . htmlspecialchars($agent1Username)
    );
}


/*
|--------------------------------------------------------------------------
| Check Agent 2 Username Uniqueness
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT id
    FROM agents
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$agent2Username]);

if ($stmt->fetch()) {
    die(
        'The Demo Agent 2 username is already in use: '
        . htmlspecialchars($agent2Username)
    );
}


/*
|--------------------------------------------------------------------------
| Create Demo Customer + Demo Agent 1 + Demo Agent 2
|--------------------------------------------------------------------------
|
| Important:
|
| Passwords are intentionally NULL at this stage.
|
| Demo Setup will later:
| - collect Customer email
| - collect Agent 1 email
| - collect Agent 2 email
| - generate temporary passwords
| - hash the passwords
| - save the credentials
| - send each account its own credentials
|
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
        (?, ?, NULL, NULL, ?, NULL, ?, 1, 1)
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
    | Create Demo Agent 1
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
        VALUES
        (?, ?, NULL, NULL, ?, 1, 1, ?, 'Active', 1)
    ");

    $stmt->execute([
        $agent1Username,
        'Demo Agent 1',
        $tenantId,
        'IT Consultant'
    ]);

    $agent1Id = (int)$demoPdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Create Demo Agent 2
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
        VALUES
        (?, ?, NULL, NULL, ?, 1, 1, ?, 'Active', 1)
    ");

    $stmt->execute([
        $agent2Username,
        'Demo Agent 2',
        $tenantId,
        'IT Consultant'
    ]);

    $agent2Id = (int)$demoPdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $demoPdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Record Demo Domain History
    |--------------------------------------------------------------------------
    |
    | The domain is recorded only after the Demo tenant and all three
    | Demo account records have been created successfully.
    |
    | This prevents an incomplete/failed Demo creation from consuming
    | the company's Demo entitlement.
    |--------------------------------------------------------------------------
    */

    try {

        $historyStmt = $pdo->prepare("
            INSERT INTO demo_domain_history
            (
                company_domain
            )
            VALUES
            (?)
        ");

        $historyStmt->execute([
            $companyDomain
        ]);

    } catch (PDOException $e) {

        /*
        | The Demo itself has already been committed.
        | Log the history failure so it can be investigated without
        | hiding the successfully created Demo.
        */

        error_log(
            'Demo domain history insert failed for '
            . $companyDomain
            . ': '
            . $e->getMessage()
        );

        die(
            'The Demo was created, but the company domain could not '
            . 'be recorded in Demo history. Please contact the administrator.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Mark Demo Request as Created
    |--------------------------------------------------------------------------
    |
    | This is done only after:
    | - Demo tenant exists
    | - Company Demo Admin exists
    | - Demo Customer exists
    | - Demo Agent 1 exists
    | - Demo Agent 2 exists
    | - Demo domain history has been recorded
    |
    |--------------------------------------------------------------------------
    */

    try {

        $requestStmt = $pdo->prepare("
            UPDATE demo_requests
            SET
                status = 'Demo Created',
                demo_created_at = NOW()
            WHERE id = ?
              AND status = 'Customer Confirmed'
              AND demo_created_at IS NULL
        ");

        $requestStmt->execute([
            $requestId
        ]);

        if ($requestStmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The Demo request could not be marked as created.'
            );
        }

    } catch (PDOException $e) {

        error_log(
            'Demo request status update failed for request #'
            . $requestId
            . ': '
            . $e->getMessage()
        );

        die(
            'The Demo was created, but the Demo request could not '
            . 'be marked as created. Please contact the administrator.'
        );
    }

} catch (PDOException $e) {

    if ($demoPdo->inTransaction()) {
        $demoPdo->rollBack();
    }

    die(
        'The Demo Customer and Demo Agents could not be created. '
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
                            Demo Customer and both Demo Agents were created successfully.
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

                                <br>

                                ID:
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
                                Demo Agent 1
                            </th>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($agent1Username) ?>
                                </strong>

                                <br>

                                ID:
                                <?= $agent1Id ?>
                            </td>
                        </tr>


                        <tr>
                            <th>
                                Demo Agent 2
                            </th>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($agent2Username) ?>
                                </strong>

                                <br>

                                ID:
                                <?= $agent2Id ?>
                            </td>
                        </tr>

                    </table>


                    <div class="alert alert-info">

                        <h5>
                            Next Step
                        </h5>

                        <p class="mb-0">

                            The three account records have been created,
                            but their email addresses and passwords have
                            intentionally not been assigned yet.

                            <br><br>

                            The Demo Admin will complete the setup by
                            entering:

                            <br>

                            <strong>Customer Email</strong><br>
                            <strong>Agent 1 Email</strong><br>
                            <strong>Agent 2 Email</strong>

                            <br><br>

                            Demo Setup will then generate separate
                            temporary passwords and send each account
                            its own credentials.

                        </p>

                    </div>


                    <div class="alert alert-warning">

                        <strong>
                            Passwords are not displayed here.
                        </strong>

                        <br>

                        All three accounts currently have:

                        <br>

                        <code>force_password_change = 1</code>

                        <br><br>

                        Their passwords will be assigned during
                        Demo Setup.

                    </div>


                    <div class="alert alert-secondary">

                        <strong>
                            Step 1.11 complete.
                        </strong>

                        <br><br>

                        The Demo Tenant now has:

                        <br><br>

                        <code><?= htmlspecialchars($adminUsername) ?></code><br>
                        <code><?= htmlspecialchars($customerUsername) ?></code><br>
                        <code><?= htmlspecialchars($agent1Username) ?></code><br>
                        <code><?= htmlspecialchars($agent2Username) ?></code>

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