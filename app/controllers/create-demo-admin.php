<?php

/*
|--------------------------------------------------------------------------
| Create Demo Admin
|--------------------------------------------------------------------------
| Step 1.10
|
| Main / Dev Admin creates ONLY:
| - Company Demo Admin
|
| Step 1.9 already created:
| - Demo tenant
|
| This step does NOT create:
| - Demo Customer
| - Demo Agent 1
| - Demo Agent 2
|
| After this step, the Company Demo Admin receives an email
| containing the temporary login credentials and instructions
| to complete Demo Setup.
|--------------------------------------------------------------------------
*/


if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}


require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/demo-database.php';
require_once APP_PATH . '/helpers/email.php';


$requestId = (int)($_GET['id'] ?? 0);

if ($requestId <= 0) {
    die('Invalid Demo request ID.');
}


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


if ($request['status'] !== 'Customer Confirmed') {
    die(
        'This Demo request cannot be provisioned. '
        . 'Current status: '
        . htmlspecialchars($request['status'])
    );
}


if (!empty($request['demo_created_at'])) {
    die('This Demo request has already been provisioned.');
}


$companyDomain = trim($request['company_domain'] ?? '');
$registeredEmail = trim($request['email'] ?? '');

if ($companyDomain === '') {
    die('The Demo request does not contain a company domain.');
}

if ($registeredEmail === '') {
    die('The Demo request does not contain a registered email address.');
}


/*
|--------------------------------------------------------------------------
| Find Tenant Created by Step 1.9
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
        . 'Complete Step 1.9 before creating the Demo Admin.'
    );
}

$tenantId = (int)$tenant['id'];


if ($tenant['company_domain'] !== $companyDomain) {
    die('Demo tenant domain does not match the Demo request.');
}


/*
|--------------------------------------------------------------------------
| Check Existing Demo Admin
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

if ($existingAdmin) {
    die(
        'A Company Demo Admin already exists for this tenant. '
        . 'Username: '
        . htmlspecialchars($existingAdmin['username'] ?? '')
    );
}


/*
|--------------------------------------------------------------------------
| Generate Demo Admin Username
|--------------------------------------------------------------------------
|
| Agreed convention:
|
| loopsautomation.com
|        ↓
| loopsautomation_admin
|
| The final domain extension is not included.
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

array_pop($domainParts);

$domainBase = implode('_', $domainParts);

$domainBase = preg_replace(
    '/[^a-z0-9_]+/',
    '_',
    $domainBase
);

$domainBase = trim($domainBase, '_');

if ($domainBase === '') {
    die('Unable to generate a Demo Admin username from the company domain.');
}

$adminUsername = $domainBase . '_admin';


/*
|--------------------------------------------------------------------------
| Check Username Uniqueness
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT id
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$adminUsername]);

if ($stmt->fetch()) {
    die(
        'The generated Demo Admin username is already in use: '
        . htmlspecialchars($adminUsername)
    );
}


/*
|--------------------------------------------------------------------------
| Generate Temporary Password
|--------------------------------------------------------------------------
*/

$temporaryPassword = bin2hex(random_bytes(8));

$passwordHash = password_hash(
    $temporaryPassword,
    PASSWORD_DEFAULT
);


/*
|--------------------------------------------------------------------------
| Create Demo Admin
|--------------------------------------------------------------------------
*/

try {

    $demoPdo->beginTransaction();

    $stmt = $demoPdo->prepare("
        INSERT INTO users
        (
            username,
            email,
            password,
            demo_tenant_id,
            is_demo_account,
            is_super_admin,
            force_password_change
        )
        VALUES
        (?, ?, ?, ?, 1, 0, 1)
    ");

    $stmt->execute([
        $adminUsername,
        $registeredEmail,
        $passwordHash,
        $tenantId
    ]);

    $adminId = (int)$demoPdo->lastInsertId();

    $demoPdo->commit();

} catch (PDOException $e) {

    if ($demoPdo->inTransaction()) {
        $demoPdo->rollBack();
    }

    error_log(
        'Demo Admin creation failed for request #'
        . $requestId
        . ': '
        . $e->getMessage()
    );

    die(
        'The Company Demo Admin could not be created. '
        . 'Please check the Demo database.'
    );
}


/*
|--------------------------------------------------------------------------
| Build Demo Login URL
|--------------------------------------------------------------------------
|
| DEMO_APP_URL is supplied by the environment configuration.
| We use it so the email points to the actual Demo portal rather
| than the Main / Dev application.
|--------------------------------------------------------------------------
*/

$demoAppUrl = '';

$envFile = dirname(__DIR__, 2) . '/.env';

if (is_file($envFile)) {

    $env = parse_ini_file($envFile);

    if (is_array($env)) {
        $demoAppUrl = trim($env['DEMO_APP_URL'] ?? '');
    }
}

if ($demoAppUrl === '') {
    $demoAppUrl = trim((string)getenv('DEMO_APP_URL'));
}

$demoLoginUrl = '';

if ($demoAppUrl === '') {
    $demoAppUrl = trim((string)getenv('APP_URL'));
}

if ($demoAppUrl === '' && defined('APP_URL')) {
    $demoAppUrl = APP_URL;
}

/*
|--------------------------------------------------------------------------
| Demo Login URL
|--------------------------------------------------------------------------
|
| The Demo Admin must always receive a direct link to the Demo portal.
| DEMO_APP_URL is preferred for each environment. The public Demo URL
| is the final fallback so the email never loses the login link.
|--------------------------------------------------------------------------
*/

if ($demoAppUrl === '') {
    $demoAppUrl = 'https://demo.wahbibconsultancy.com';
}

$demoLoginUrl =
    rtrim($demoAppUrl, '/')
    . '/?page=demo-login';


/*
|--------------------------------------------------------------------------
| Send Company Demo Admin Setup Email
|--------------------------------------------------------------------------
|
| The Demo Admin receives:
| - Username
| - Temporary password
| - Demo login link
| - Instruction to change the temporary password
| - Instruction to complete Demo Setup
| - The three emails required during Setup
|
| Email failure does NOT undo the successfully created Admin account.
|--------------------------------------------------------------------------
*/

$emailSent = false;
$emailError = '';

$safeName = htmlspecialchars(
    $request['full_name'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$safeCompany = htmlspecialchars(
    $request['company_name'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$safeUsername = htmlspecialchars(
    $adminUsername,
    ENT_QUOTES,
    'UTF-8'
);

$safePassword = htmlspecialchars(
    $temporaryPassword,
    ENT_QUOTES,
    'UTF-8'
);

$loginSection = '';

if ($demoLoginUrl !== '') {

    $safeLoginUrl = htmlspecialchars(
        $demoLoginUrl,
        ENT_QUOTES,
        'UTF-8'
    );

    $loginSection = "
        <p>
            <strong>Demo Login:</strong>
            <a href='{$safeLoginUrl}'>
                Open Demo Login
            </a>
        </p>
    ";
}

$emailSubject = 'Your Company Demo Admin Access';

$emailBody = "
    <h2>Hello {$safeName},</h2>

    <p>
        Your Company Demo has been prepared and your
        <strong>Company Demo Admin</strong> account has been created.
    </p>

    " . (
        $safeCompany !== ''
            ? "<p><strong>Company:</strong> {$safeCompany}</p>"
            : ''
    ) . "

    <p>
        Please use the temporary credentials below to access
        the Demo portal. The Company Demo Admin signs in using the
        registered email address.
    </p>

    <table
        cellpadding='8'
        cellspacing='0'
        border='1'
        style='
            border-collapse:collapse;
            width:100%;
            max-width:650px;
        '>

        <tr>
            <th align='left'>Demo Admin Email</th>
            <td><code>{$registeredEmail}</code></td>
        </tr>

        <tr>
            <th align='left'>Temporary Password</th>
            <td><code>{$safePassword}</code></td>
        </tr>

    </table>

    <br>

    {$loginSection}

    <h3>What you need to do</h3>

    <ol>
        <li>
            Sign in to the Demo portal using the registered email address and
            the temporary password.
        </li>

        <li>
            Change the temporary password when prompted.
        </li>

        <li>
            Open <strong>Demo Setup</strong>.
        </li>

        <li>
            Enter the email address for the
            <strong>Demo Customer</strong>.
        </li>

        <li>
            Enter the email address for
            <strong>Demo Agent 1</strong>.
        </li>

        <li>
            Enter the email address for
            <strong>Demo Agent 2</strong>.
        </li>

        <li>
            Complete Demo Setup.
        </li>
    </ol>

    <p>
        Demo Setup will generate separate temporary passwords
        and send each account its own credentials.
    </p>

    <p>
        The Main / Dev Administrator does not create these
        three accounts. Their setup is completed by you,
        the Company Demo Admin.
    </p>

    <p>
        Please keep your credentials secure.
    </p>

    <p>
        Kind Regards,<br>
        <strong>IT Consultancy Team</strong>
    </p>
";


try {

    $emailSent = sendEmail(
        $registeredEmail,
        $emailSubject,
        $emailBody
    );

    if (!$emailSent) {
        $emailError =
            'The Company Demo Admin was created, but the setup email '
            . 'could not be sent. Please check the mail configuration.';
    }

} catch (Throwable $e) {

    error_log(
        'Demo Admin setup email failed for request #'
        . $requestId
        . ': '
        . $e->getMessage()
    );

    $emailError =
        'The Company Demo Admin was created, but the setup email '
        . 'could not be sent. Please check the mail configuration.';
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

    <title>Demo Admin Created</title>

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
                        Company Demo Admin Created
                    </h4>

                </div>


                <div class="card-body">


                    <div class="alert alert-success">

                        <strong>
                            Company Demo Admin was created successfully.
                        </strong>

                    </div>


                    <?php if ($emailSent): ?>

                        <div class="alert alert-success">

                            <strong>
                                Setup email sent successfully.
                            </strong>

                            <br>

                            The Company Demo Admin received instructions
                            to log in and complete Demo Setup.

                        </div>

                    <?php else: ?>

                        <div class="alert alert-danger">

                            <strong>
                                Setup email was not sent.
                            </strong>

                            <br>

                            <?= htmlspecialchars($emailError) ?>

                        </div>

                    <?php endif; ?>


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
                                Admin ID
                            </th>

                            <td>
                                <?= $adminId ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Username
                            </th>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($adminUsername) ?>
                                </strong>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Email
                            </th>

                            <td>
                                <?= htmlspecialchars($registeredEmail) ?>
                            </td>

                        </tr>


                        <tr>

                            <th>
                                Super Admin
                            </th>

                            <td>

                                <span class="badge bg-secondary">
                                    No
                                </span>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Demo Account
                            </th>

                            <td>

                                <span class="badge bg-success">
                                    Yes
                                </span>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                Force Password Change
                            </th>

                            <td>

                                <span class="badge bg-warning text-dark">
                                    Yes
                                </span>

                            </td>

                        </tr>

                    </table>


                    <div class="alert alert-warning">

                        <strong>
                            Temporary Password
                        </strong>

                        <div class="mt-2">

                            <code class="fs-5">
                                <?= htmlspecialchars($temporaryPassword) ?>
                            </code>

                        </div>

                        <div class="small mt-2">

                            Save this password for testing.
                            It is intentionally temporary.

                        </div>

                    </div>


                    <div class="alert alert-info mb-0">

                        <strong>
                            Step 1.10 complete.
                        </strong>

                        <br>

                        The Company Demo Admin was created.

                        <br><br>

                        The Main / Dev Admin should now stop.

                        <br><br>

                        The Company Demo Admin must log in,
                        change the temporary password, and complete
                        Demo Setup by entering the three required
                        email addresses:

                        <br><br>

                        <strong>Customer Email</strong><br>
                        <strong>Agent 1 Email</strong><br>
                        <strong>Agent 2 Email</strong>

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
