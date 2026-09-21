<?php

/*
|--------------------------------------------------------------------------
| DEMO SETUP
|--------------------------------------------------------------------------
| Company Demo Admin only.
|
| The Main / Dev Admin creates:
|   1. Demo Tenant
|   2. Company Demo Admin
|
| The Company Demo Admin completes this page by entering:
|   1. Customer Email
|   2. Agent 1 Email
|   3. Agent 2 Email
|
| This page creates the three Demo accounts and sends each account
| its own temporary credentials.
|--------------------------------------------------------------------------
*/


require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/demo-database.php';
require_once APP_PATH . '/helpers/email.php';


/*
|--------------------------------------------------------------------------
| Require Company Demo Admin
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_user'])) {
    header('Location: ?page=demo-login');
    exit;
}

$demoAdminId = (int)($_SESSION['demo_user']['id'] ?? 0);

if ($demoAdminId <= 0) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Demo Admin
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        u.id,
        u.username,
        u.email,
        u.password,
        u.demo_tenant_id,
        u.is_demo_account,
        u.is_super_admin,
        u.force_password_change,
        t.company_name,
        t.company_domain,
        t.registered_email,
        t.demo_request_id,
        t.started_at,
        t.expires_at,
        t.status AS tenant_status
    FROM users u
    INNER JOIN demo_tenants t
        ON t.id = u.demo_tenant_id
    WHERE u.id = ?
      AND u.is_demo_account = 1
      AND u.is_super_admin = 0
      AND u.demo_tenant_id IS NOT NULL
    LIMIT 1
");

$stmt->execute([$demoAdminId]);

$demoAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demoAdmin) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}

$tenantId = (int)$demoAdmin['demo_tenant_id'];

if (
    $demoAdmin['tenant_status'] !== 'Active' ||
    (
        $demoAdmin['expires_at'] !== null &&
        strtotime($demoAdmin['expires_at']) <= time()
    )
) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Demo Admin must finish password change first
|--------------------------------------------------------------------------
*/

if ((int)$demoAdmin['force_password_change'] === 1) {
    header('Location: ?page=demo-change-password');
    exit;
}


/*
|--------------------------------------------------------------------------
| Build Agreed Usernames
|--------------------------------------------------------------------------
|
| loopsautomation.com
|        ↓
| loopsautomation
|
| Final usernames:
|   loopsautomation_customer
|   loopsautomation_agent1
|   loopsautomation_agent2
|--------------------------------------------------------------------------
*/

$domainWithoutWww = preg_replace(
    '/^www\./i',
    '',
    strtolower(trim($demoAdmin['company_domain']))
);

$domainParts = explode('.', $domainWithoutWww);

if (count($domainParts) < 2) {
    die('The Demo company domain is invalid.');
}

array_pop($domainParts);

$usernameBase = implode('_', $domainParts);

$usernameBase = preg_replace(
    '/[^a-z0-9_]+/',
    '_',
    $usernameBase
);

$usernameBase = trim($usernameBase, '_');

if ($usernameBase === '') {
    die('Unable to generate Demo usernames from the company domain.');
}

$customerUsername = $usernameBase . '_customer';
$agent1Username   = $usernameBase . '_agent1';
$agent2Username   = $usernameBase . '_agent2';


/*
|--------------------------------------------------------------------------
| Check Existing Setup
|--------------------------------------------------------------------------
|
| A completed setup has all three accounts with passwords and emails.
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
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

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
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

$agent1 = $stmt->fetch(PDO::FETCH_ASSOC);


$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
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

$agent2 = $stmt->fetch(PDO::FETCH_ASSOC);


$setupComplete =
    $customer &&
    $agent1 &&
    $agent2 &&
    !empty($customer['email']) &&
    !empty($customer['password']) &&
    !empty($agent1['email']) &&
    !empty($agent1['password']) &&
    !empty($agent2['email']) &&
    !empty($agent2['password']);


if ($setupComplete) {
    header('Location: ?page=dashboard');
    exit;
}


$error = '';

$customerEmail = '';
$agent1Email = '';
$agent2Email = '';


/*
|--------------------------------------------------------------------------
| Handle Demo Setup
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerEmail = trim($_POST['customer_email'] ?? '');
    $agent1Email   = trim($_POST['agent1_email'] ?? '');
    $agent2Email   = trim($_POST['agent2_email'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validate Customer
    |--------------------------------------------------------------------------
    */

    if (
        $customerEmail === '' ||
        !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Please enter a valid Customer email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Agent 1
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        (
            $agent1Email === '' ||
            !filter_var($agent1Email, FILTER_VALIDATE_EMAIL)
        )
    ) {
        $error = 'Please enter a valid Agent 1 email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Agent 2
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        (
            $agent2Email === '' ||
            !filter_var($agent2Email, FILTER_VALIDATE_EMAIL)
        )
    ) {
        $error = 'Please enter a valid Agent 2 email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Emails
    |--------------------------------------------------------------------------
    */

    $emails = [
        strtolower($customerEmail),
        strtolower($agent1Email),
        strtolower($agent2Email)
    ];

    if (
        $error === '' &&
        count(array_unique($emails)) !== 3
    ) {
        $error =
            'Customer, Agent 1, and Agent 2 must use '
            . 'three different email addresses.';
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Temporary Passwords
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $customerPassword = bin2hex(random_bytes(8));
        $agent1Password   = bin2hex(random_bytes(8));
        $agent2Password   = bin2hex(random_bytes(8));

        $customerPasswordHash = password_hash(
            $customerPassword,
            PASSWORD_DEFAULT
        );

        $agent1PasswordHash = password_hash(
            $agent1Password,
            PASSWORD_DEFAULT
        );

        $agent2PasswordHash = password_hash(
            $agent2Password,
            PASSWORD_DEFAULT
        );


        try {

            $demoPdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Create or Update Customer
            |--------------------------------------------------------------------------
            */

            if (!$customer) {

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
                    (?, ?, ?, NULL, ?, ?, ?, 1, 1)
                ");

                $stmt->execute([
                    $customerUsername,
                    'Demo Customer',
                    $customerEmail,
                    $demoAdmin['company_name'],
                    $customerPasswordHash,
                    $tenantId
                ]);

                $customerId = (int)$demoPdo->lastInsertId();

            } else {

                $customerId = (int)$customer['id'];

                $stmt = $demoPdo->prepare("
                    UPDATE customers
                    SET
                        email = ?,
                        password = ?,
                        force_password_change = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                ");

                $stmt->execute([
                    $customerEmail,
                    $customerPasswordHash,
                    $customerId,
                    $tenantId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Create or Update Agent 1
            |--------------------------------------------------------------------------
            */

            if (!$agent1) {

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
                    (?, ?, ?, ?, ?, 1, 1, ?, 'Active', 1)
                ");

                $stmt->execute([
                    $agent1Username,
                    'Demo Agent 1',
                    $agent1Email,
                    $agent1PasswordHash,
                    $tenantId,
                    'IT Consultant'
                ]);

                $agent1Id = (int)$demoPdo->lastInsertId();

            } else {

                $agent1Id = (int)$agent1['id'];

                $stmt = $demoPdo->prepare("
                    UPDATE agents
                    SET
                        email = ?,
                        password = ?,
                        force_password_change = 1,
                        status = 'Active',
                        active = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                ");

                $stmt->execute([
                    $agent1Email,
                    $agent1PasswordHash,
                    $agent1Id,
                    $tenantId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Create or Update Agent 2
            |--------------------------------------------------------------------------
            */

            if (!$agent2) {

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
                    (?, ?, ?, ?, ?, 1, 1, ?, 'Active', 1)
                ");

                $stmt->execute([
                    $agent2Username,
                    'Demo Agent 2',
                    $agent2Email,
                    $agent2PasswordHash,
                    $tenantId,
                    'IT Consultant'
                ]);

                $agent2Id = (int)$demoPdo->lastInsertId();

            } else {

                $agent2Id = (int)$agent2['id'];

                $stmt = $demoPdo->prepare("
                    UPDATE agents
                    SET
                        email = ?,
                        password = ?,
                        force_password_change = 1,
                        status = 'Active',
                        active = 1
                    WHERE id = ?
                      AND demo_tenant_id = ?
                      AND is_demo_account = 1
                ");

                $stmt->execute([
                    $agent2Email,
                    $agent2PasswordHash,
                    $agent2Id,
                    $tenantId
                ]);
            }


            $demoPdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Finalize Main Demo Request
            |--------------------------------------------------------------------------
            |
            | Demo Setup is the point at which all three Demo accounts have
            | actually been configured. The Demo entitlement is recorded now.
            |--------------------------------------------------------------------------
            */

            $requestId = (int)$demoAdmin['demo_request_id'];

            if ($requestId > 0) {

                try {

                    $historyStmt = $pdo->prepare("
                        INSERT INTO demo_domain_history
                        (
                            company_domain
                        )
                        SELECT ?
                        WHERE NOT EXISTS
                        (
                            SELECT 1
                            FROM demo_domain_history
                            WHERE company_domain = ?
                        )
                    ");

                    $historyStmt->execute([
                        $demoAdmin['company_domain'],
                        $demoAdmin['company_domain']
                    ]);


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

                } catch (PDOException $e) {

                    error_log(
                        'Demo finalization failed for request #'
                        . $requestId
                        . ': '
                        . $e->getMessage()
                    );

                    /*
                    | The Demo accounts were successfully created.
                    | Do not destroy them because Main DB finalization
                    | failed. Report the issue to the Admin instead.
                    */

                    $error =
                        'The three Demo accounts were created, but the '
                        . 'Main Demo record could not be finalized. '
                        . 'Please contact the administrator.';

                }
            }


            /*
            |--------------------------------------------------------------------------
            | Send Customer Credentials
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $demoAppUrl = '';

                $envFile = dirname(__DIR__, 2) . '/.env';

                if (is_file($envFile)) {

                    $env = parse_ini_file($envFile);

                    if (is_array($env)) {
                        $demoAppUrl =
                            trim($env['DEMO_APP_URL'] ?? '');
                    }
                }

                if ($demoAppUrl === '') {
                    $demoAppUrl =
                        trim((string)getenv('DEMO_APP_URL'));
                }

                if ($demoAppUrl === '') {
                    $demoAppUrl = rtrim(APP_URL, '/');
                }

                $loginLink =
                    rtrim($demoAppUrl, '/')
                    . '/?page=demo-login';


                $safeCustomerUsername = htmlspecialchars(
                    $customerUsername,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeCustomerPassword = htmlspecialchars(
                    $customerPassword,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent1Username = htmlspecialchars(
                    $agent1Username,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent1Password = htmlspecialchars(
                    $agent1Password,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent2Username = htmlspecialchars(
                    $agent2Username,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent2Password = htmlspecialchars(
                    $agent2Password,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeLoginLink = htmlspecialchars(
                    $loginLink,
                    ENT_QUOTES,
                    'UTF-8'
                );


                $customerEmailBody = "
                    <h2>Demo Customer Account</h2>

                    <p>
                        Your Demo Customer account has been created.
                    </p>

                    <p>
                        <strong>Username:</strong>
                        {$safeCustomerUsername}
                    </p>

                    <p>
                        <strong>Temporary Password:</strong>
                        <code>{$safeCustomerPassword}</code>
                    </p>

                    <p>
                        <strong>Demo Login:</strong>
                        <a href=\"{$safeLoginLink}\">
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        You will be required to change your temporary
                        password when you first sign in.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";


                $agent1EmailBody = "
                    <h2>Demo Agent 1 Account</h2>

                    <p>
                        Your Demo Agent 1 account has been created.
                    </p>

                    <p>
                        <strong>Username:</strong>
                        {$safeAgent1Username}
                    </p>

                    <p>
                        <strong>Temporary Password:</strong>
                        <code>{$safeAgent1Password}</code>
                    </p>

                    <p>
                        <strong>Demo Login:</strong>
                        <a href=\"{$safeLoginLink}\">
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        You will be required to change your temporary
                        password when you first sign in.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";


                $agent2EmailBody = "
                    <h2>Demo Agent 2 Account</h2>

                    <p>
                        Your Demo Agent 2 account has been created.
                    </p>

                    <p>
                        <strong>Username:</strong>
                        {$safeAgent2Username}
                    </p>

                    <p>
                        <strong>Temporary Password:</strong>
                        <code>{$safeAgent2Password}</code>
                    </p>

                    <p>
                        <strong>Demo Login:</strong>
                        <a href=\"{$safeLoginLink}\">
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        You will be required to change your temporary
                        password when you first sign in.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>
                ";


                $customerSent = sendEmail(
                    $customerEmail,
                    'Your Demo Customer Account',
                    $customerEmailBody
                );

                $agent1Sent = sendEmail(
                    $agent1Email,
                    'Your Demo Agent 1 Account',
                    $agent1EmailBody
                );

                $agent2Sent = sendEmail(
                    $agent2Email,
                    'Your Demo Agent 2 Account',
                    $agent2EmailBody
                );


                if (
                    !$customerSent ||
                    !$agent1Sent ||
                    !$agent2Sent
                ) {
                    $error =
                        'The Demo accounts were created, but one or more '
                        . 'credential emails could not be sent. '
                        . 'Please check the mail configuration.';
                }
            }


            if ($error === '') {

                header('Location: ?page=dashboard&demo_setup=complete');
                exit;
            }

        } catch (PDOException $e) {

            if ($demoPdo->inTransaction()) {
                $demoPdo->rollBack();
            }

            error_log(
                'Demo Setup failed for tenant #'
                . $tenantId
                . ': '
                . $e->getMessage()
            );

            $error =
                'Demo Setup could not be completed. '
                . 'Please check the Demo database.';
        }
    }
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

    <title>Demo Setup</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body class="bg-light">


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Demo Setup
                    </h4>

                </div>


                <div class="card-body">

                    <div class="alert alert-info">

                        <strong>
                            Company Demo Admin Setup
                        </strong>

                        <br><br>

                        You are the Company Demo Admin.

                        <br><br>

                        Complete the setup by entering a separate
                        email address for each Demo role.

                    </div>


                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>


                    <form
                        method="POST"
                        action="?page=demo-setup"
                        autocomplete="off"
                    >


                        <div class="mb-3">

                            <label
                                for="customer_email"
                                class="form-label"
                            >
                                Demo Customer Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="customer_email"
                                name="customer_email"
                                value="<?= htmlspecialchars($customerEmail) ?>"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                for="agent1_email"
                                class="form-label"
                            >
                                Demo Agent 1 Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="agent1_email"
                                name="agent1_email"
                                value="<?= htmlspecialchars($agent1Email) ?>"
                                required
                            >

                        </div>


                        <div class="mb-4">

                            <label
                                for="agent2_email"
                                class="form-label"
                            >
                                Demo Agent 2 Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="agent2_email"
                                name="agent2_email"
                                value="<?= htmlspecialchars($agent2Email) ?>"
                                required
                            >

                        </div>


                        <div class="alert alert-warning">

                            <strong>
                                Important
                            </strong>

                            <br><br>

                            Each email address must belong to the
                            person who will use that Demo account.

                            <br><br>

                            Separate temporary passwords will be
                            generated and sent to the three email
                            addresses.

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Complete Demo Setup
                        </button>


                    </form>


                    <div class="text-center mt-3">

                        <a href="?page=dashboard">
                            Back to Demo Dashboard
                        </a>

                    </div>


                </div>

            </div>

        </div>

    </div>

</div>


</body>

</html>
