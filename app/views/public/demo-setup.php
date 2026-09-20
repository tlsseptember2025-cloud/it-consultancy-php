<?php

/*
|--------------------------------------------------------------------------
| DEMO SETUP
|--------------------------------------------------------------------------
| Step 1.12
|
| Demo Admin completes the initial Demo account setup.
|
| Required:
| - Customer Email
| - Agent 1 Email
| - Agent 2 Email
|
| The system then:
| - Generates a separate temporary password for each account
| - Saves each password as a hash
| - Sets force_password_change = 1
| - Sends Customer credentials only to Customer email
| - Sends Agent 1 credentials only to Agent 1 email
| - Sends Agent 2 credentials only to Agent 2 email
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Database + Email
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/demo-database.php';
require_once APP_PATH . '/helpers/email.php';


/*
|--------------------------------------------------------------------------
| Require Demo Admin
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_user'])) {

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Demo Admin Session
|--------------------------------------------------------------------------
*/

$demoAdmin = $_SESSION['demo_user'];

$adminId = (int)($demoAdmin['id'] ?? 0);
$tenantId = (int)($demoAdmin['demo_tenant_id'] ?? 0);


if ($adminId <= 0 || $tenantId <= 0) {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Reload Demo Admin + Tenant
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        u.*,
        t.company_name,
        t.company_domain,
        t.registered_email,
        t.expires_at,
        t.status AS tenant_status
    FROM users u
    INNER JOIN demo_tenants t
        ON t.id = u.demo_tenant_id
    WHERE u.id = ?
      AND u.demo_tenant_id = ?
      AND u.is_demo_account = 1
      AND u.is_super_admin = 0
    LIMIT 1
");

$stmt->execute([
    $adminId,
    $tenantId
]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$admin) {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Tenant
|--------------------------------------------------------------------------
*/

if ($admin['tenant_status'] !== 'Active') {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Demo Expiration
|--------------------------------------------------------------------------
*/

if (
    $admin['expires_at'] !== null &&
    strtotime($admin['expires_at']) <= time()
) {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Demo Usernames
|--------------------------------------------------------------------------
|
| Example:
|
| loopsautomation_admin
| loopsautomation_customer
| loopsautomation_agent1
| loopsautomation_agent2
|
|--------------------------------------------------------------------------
*/

$usernameBase = preg_replace(
    '/_admin$/',
    '',
    $admin['username']
);

if ($usernameBase === '') {

    die('Unable to determine the Demo username base.');
}


$customerUsername = $usernameBase . '_customer';
$agent1Username   = $usernameBase . '_agent1';
$agent2Username   = $usernameBase . '_agent2';


/*
|--------------------------------------------------------------------------
| Load Demo Customer
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
        force_password_change,
        is_demo_account,
        demo_tenant_id
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


if (!$customer) {

    die(
        'Demo Customer account was not found. '
        . 'Complete Demo provisioning first.'
    );
}


/*
|--------------------------------------------------------------------------
| Load Demo Agent 1
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
        force_password_change,
        status,
        active,
        is_demo_account,
        demo_tenant_id
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


if (!$agent1) {

    die(
        'Demo Agent 1 account was not found. '
        . 'Complete Demo provisioning first.'
    );
}


/*
|--------------------------------------------------------------------------
| Load Demo Agent 2
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        username,
        name,
        email,
        password,
        force_password_change,
        status,
        active,
        is_demo_account,
        demo_tenant_id
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


if (!$agent2) {

    die(
        'Demo Agent 2 account was not found. '
        . 'Complete Demo provisioning first.'
    );
}


/*
|--------------------------------------------------------------------------
| Check If Setup Is Already Complete
|--------------------------------------------------------------------------
*/

if (
    !empty($customer['password']) &&
    !empty($agent1['password']) &&
    !empty($agent2['password'])
) {

    header('Location: ?page=dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$customerEmail = '';
$agent1Email = '';
$agent2Email = '';

$error = '';

$customerEmailSent = false;
$agent1EmailSent = false;
$agent2EmailSent = false;


/*
|--------------------------------------------------------------------------
| Process Setup
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerEmail = trim(
        $_POST['customer_email'] ?? ''
    );

    $agent1Email = trim(
        $_POST['agent1_email'] ?? ''
    );

    $agent2Email = trim(
        $_POST['agent2_email'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Customer Email
    |--------------------------------------------------------------------------
    */

    if (
        $customerEmail === '' ||
        !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            'Please enter a valid Customer email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Agent 1 Email
    |--------------------------------------------------------------------------
    */

    elseif (
        $agent1Email === '' ||
        !filter_var($agent1Email, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            'Please enter a valid Agent 1 email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Agent 2 Email
    |--------------------------------------------------------------------------
    */

    elseif (
        $agent2Email === '' ||
        !filter_var($agent2Email, FILTER_VALIDATE_EMAIL)
    ) {

        $error =
            'Please enter a valid Agent 2 email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Emails Must Be Different
    |--------------------------------------------------------------------------
    */

    elseif (
        strtolower($customerEmail) === strtolower($agent1Email) ||
        strtolower($customerEmail) === strtolower($agent2Email) ||
        strtolower($agent1Email) === strtolower($agent2Email)
    ) {

        $error =
            'Customer, Agent 1 and Agent 2 must use three different email addresses.';
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Agents Are Active
    |--------------------------------------------------------------------------
    */

    elseif (
        $agent1['status'] !== 'Active' ||
        (int)$agent1['active'] !== 1
    ) {

        $error =
            'Demo Agent 1 is not active.';
    }


    elseif (
        $agent2['status'] !== 'Active' ||
        (int)$agent2['active'] !== 1
    ) {

        $error =
            'Demo Agent 2 is not active.';
    }


    /*
    |--------------------------------------------------------------------------
    | Save Credentials
    |--------------------------------------------------------------------------
    */

    else {

        /*
        |--------------------------------------------------------------------------
        | Generate Separate Temporary Passwords
        |--------------------------------------------------------------------------
        */

        $customerTemporaryPassword =
            bin2hex(random_bytes(8));

        $agent1TemporaryPassword =
            bin2hex(random_bytes(8));

        $agent2TemporaryPassword =
            bin2hex(random_bytes(8));


        /*
        |--------------------------------------------------------------------------
        | Hash Passwords
        |--------------------------------------------------------------------------
        */

        $customerPasswordHash = password_hash(
            $customerTemporaryPassword,
            PASSWORD_DEFAULT
        );

        $agent1PasswordHash = password_hash(
            $agent1TemporaryPassword,
            PASSWORD_DEFAULT
        );

        $agent2PasswordHash = password_hash(
            $agent2TemporaryPassword,
            PASSWORD_DEFAULT
        );


        if (
            $customerPasswordHash === false ||
            $agent1PasswordHash === false ||
            $agent2PasswordHash === false
        ) {

            $error =
                'The temporary passwords could not be generated. Please try again.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Begin Transaction
                |--------------------------------------------------------------------------
                */

                $demoPdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Update Customer
                |--------------------------------------------------------------------------
                */

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
                    (int)$customer['id'],
                    $tenantId
                ]);


                if ($stmt->rowCount() !== 1) {

                    throw new RuntimeException(
                        'The Demo Customer account could not be updated.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Update Agent 1
                |--------------------------------------------------------------------------
                */

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
                    (int)$agent1['id'],
                    $tenantId
                ]);


                if ($stmt->rowCount() !== 1) {

                    throw new RuntimeException(
                        'The Demo Agent 1 account could not be updated.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Update Agent 2
                |--------------------------------------------------------------------------
                */

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
                    (int)$agent2['id'],
                    $tenantId
                ]);


                if ($stmt->rowCount() !== 1) {

                    throw new RuntimeException(
                        'The Demo Agent 2 account could not be updated.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Commit Database Changes
                |--------------------------------------------------------------------------
                */

                $demoPdo->commit();


                /*
                |--------------------------------------------------------------------------
                | Demo Login URL
                |--------------------------------------------------------------------------
                */

                $loginLink =
                    rtrim(APP_URL, '/')
                    . '/?page=demo-login';


                /*
                |--------------------------------------------------------------------------
                | Safe Company Name
                |--------------------------------------------------------------------------
                */

                $safeCompanyName = htmlspecialchars(
                    $admin['company_name'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                );


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER EMAIL
                |--------------------------------------------------------------------------
                |
                | Customer receives ONLY Customer credentials.
                |
                */

                $safeCustomerUsername = htmlspecialchars(
                    $customerUsername,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeCustomerPassword = htmlspecialchars(
                    $customerTemporaryPassword,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $customerEmailBody = "

                    <h2>Welcome to Your Demo</h2>

                    <p>
                        Your Demo Customer account has been created.
                    </p>

                    <p>
                        <strong>Company:</strong>
                        {$safeCompanyName}
                    </p>

                    <table
                        cellpadding='8'
                        cellspacing='0'
                        border='1'
                        style='
                            border-collapse:collapse;
                            width:100%;
                            max-width:600px;
                        '>

                        <tr>
                            <td><strong>Account</strong></td>
                            <td>Customer</td>
                        </tr>

                        <tr>
                            <td><strong>Username</strong></td>
                            <td>
                                <code>{$safeCustomerUsername}</code>
                            </td>
                        </tr>

                        <tr>
                            <td><strong>Temporary Password</strong></td>
                            <td>
                                <code>{$safeCustomerPassword}</code>
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:20px;'>
                        <strong>Demo Login:</strong>
                        <a href='{$loginLink}'>
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        This is a temporary password.
                        You must change it when you first log in.
                    </p>

                    <p>
                        Please keep these credentials secure.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>

                ";


                /*
                |--------------------------------------------------------------------------
                | Send Customer Email
                |--------------------------------------------------------------------------
                */

                $customerEmailSent = sendEmail(
                    $customerEmail,
                    'Your Demo Customer Credentials',
                    $customerEmailBody
                );


                /*
                |--------------------------------------------------------------------------
                | AGENT 1 EMAIL
                |--------------------------------------------------------------------------
                |
                | Agent 1 receives ONLY Agent 1 credentials.
                |
                */

                $safeAgent1Username = htmlspecialchars(
                    $agent1Username,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent1Password = htmlspecialchars(
                    $agent1TemporaryPassword,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $agent1EmailBody = "

                    <h2>Welcome to Your Demo</h2>

                    <p>
                        Your Demo Agent 1 account has been created.
                    </p>

                    <p>
                        <strong>Company:</strong>
                        {$safeCompanyName}
                    </p>

                    <table
                        cellpadding='8'
                        cellspacing='0'
                        border='1'
                        style='
                            border-collapse:collapse;
                            width:100%;
                            max-width:600px;
                        '>

                        <tr>
                            <td><strong>Account</strong></td>
                            <td>Agent 1</td>
                        </tr>

                        <tr>
                            <td><strong>Username</strong></td>
                            <td>
                                <code>{$safeAgent1Username}</code>
                            </td>
                        </tr>

                        <tr>
                            <td><strong>Temporary Password</strong></td>
                            <td>
                                <code>{$safeAgent1Password}</code>
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:20px;'>
                        <strong>Demo Login:</strong>
                        <a href='{$loginLink}'>
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        This is a temporary password.
                        You must change it when you first log in.
                    </p>

                    <p>
                        Please keep these credentials secure.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>

                ";


                /*
                |--------------------------------------------------------------------------
                | Send Agent 1 Email
                |--------------------------------------------------------------------------
                */

                $agent1EmailSent = sendEmail(
                    $agent1Email,
                    'Your Demo Agent 1 Credentials',
                    $agent1EmailBody
                );


                /*
                |--------------------------------------------------------------------------
                | AGENT 2 EMAIL
                |--------------------------------------------------------------------------
                |
                | Agent 2 receives ONLY Agent 2 credentials.
                |
                */

                $safeAgent2Username = htmlspecialchars(
                    $agent2Username,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeAgent2Password = htmlspecialchars(
                    $agent2TemporaryPassword,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $agent2EmailBody = "

                    <h2>Welcome to Your Demo</h2>

                    <p>
                        Your Demo Agent 2 account has been created.
                    </p>

                    <p>
                        <strong>Company:</strong>
                        {$safeCompanyName}
                    </p>

                    <table
                        cellpadding='8'
                        cellspacing='0'
                        border='1'
                        style='
                            border-collapse:collapse;
                            width:100%;
                            max-width:600px;
                        '>

                        <tr>
                            <td><strong>Account</strong></td>
                            <td>Agent 2</td>
                        </tr>

                        <tr>
                            <td><strong>Username</strong></td>
                            <td>
                                <code>{$safeAgent2Username}</code>
                            </td>
                        </tr>

                        <tr>
                            <td><strong>Temporary Password</strong></td>
                            <td>
                                <code>{$safeAgent2Password}</code>
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:20px;'>
                        <strong>Demo Login:</strong>
                        <a href='{$loginLink}'>
                            Open Demo Login
                        </a>
                    </p>

                    <p>
                        This is a temporary password.
                        You must change it when you first log in.
                    </p>

                    <p>
                        Please keep these credentials secure.
                    </p>

                    <p>
                        Kind Regards,<br>
                        <strong>IT Consultancy Team</strong>
                    </p>

                ";


                /*
                |--------------------------------------------------------------------------
                | Send Agent 2 Email
                |--------------------------------------------------------------------------
                */

                $agent2EmailSent = sendEmail(
                    $agent2Email,
                    'Your Demo Agent 2 Credentials',
                    $agent2EmailBody
                );


                /*
                |--------------------------------------------------------------------------
                | Final Setup Result
                |--------------------------------------------------------------------------
                */

                if (
                    $customerEmailSent &&
                    $agent1EmailSent &&
                    $agent2EmailSent
                ) {

                    header('Location: ?page=dashboard');
                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | Email Failure
                |--------------------------------------------------------------------------
                |
                | Database credentials have already been saved.
                |
                | We do NOT silently pretend that all emails were sent.
                |
                */

                $failedRecipients = [];

                if (!$customerEmailSent) {
                    $failedRecipients[] = 'Customer';
                }

                if (!$agent1EmailSent) {
                    $failedRecipients[] = 'Agent 1';
                }

                if (!$agent2EmailSent) {
                    $failedRecipients[] = 'Agent 2';
                }


                $error =
                    'The Demo accounts were created, but the credential email '
                    . 'could not be sent to: '
                    . implode(', ', $failedRecipients)
                    . '. Please review the email configuration and complete '
                    . 'the setup again if necessary.';


            } catch (PDOException $e) {

                if ($demoPdo->inTransaction()) {
                    $demoPdo->rollBack();
                }

                error_log(
                    'Demo setup failed: '
                    . $e->getMessage()
                );

                $error =
                    'The Demo accounts could not be configured. '
                    . 'Please try again.';

            } catch (RuntimeException $e) {

                if ($demoPdo->inTransaction()) {
                    $demoPdo->rollBack();
                }

                error_log(
                    'Demo setup failed: '
                    . $e->getMessage()
                );

                $error = $e->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-public.php';

?>


<div class="row justify-content-center mt-5">

    <div class="col-lg-7 col-md-9">

        <div class="card shadow-sm">

            <div class="card-header bg-dark text-white">

                <h4 class="mb-0">
                    Demo Setup
                </h4>

            </div>


            <div class="card-body">


                <h5 class="mb-3">

                    Complete Demo Account Setup

                </h5>


                <p class="text-muted">

                    Enter a separate email address for each Demo account.
                    Each person will receive only their own login credentials.

                </p>


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <div class="alert alert-info">

                    <strong>
                        Three separate Demo accounts will be configured:
                    </strong>

                    <ul class="mb-0 mt-2">

                        <li>
                            Customer:
                            <code>
                                <?= htmlspecialchars($customerUsername) ?>
                            </code>
                        </li>

                        <li>
                            Agent 1:
                            <code>
                                <?= htmlspecialchars($agent1Username) ?>
                            </code>
                        </li>

                        <li>
                            Agent 2:
                            <code>
                                <?= htmlspecialchars($agent2Username) ?>
                            </code>
                        </li>

                    </ul>

                </div>


                <form
                    method="POST"
                    action="?page=demo-setup"
                    autocomplete="off">


                    <!-- ==================================================
                         CUSTOMER EMAIL
                         ================================================== -->

                    <div class="mb-4">

                        <label
                            for="customer_email"
                            class="form-label">

                            <strong>
                                Customer Email
                            </strong>

                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="customer_email"
                            name="customer_email"
                            value="<?= htmlspecialchars($customerEmail) ?>"
                            autocomplete="off"
                            required>

                        <div class="form-text">

                            The Customer will receive only the Customer
                            username and temporary password.

                        </div>

                    </div>


                    <!-- ==================================================
                         AGENT 1 EMAIL
                         ================================================== -->

                    <div class="mb-4">

                        <label
                            for="agent1_email"
                            class="form-label">

                            <strong>
                                Agent 1 Email
                            </strong>

                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="agent1_email"
                            name="agent1_email"
                            value="<?= htmlspecialchars($agent1Email) ?>"
                            autocomplete="off"
                            required>

                        <div class="form-text">

                            Agent 1 will receive only the Agent 1
                            username and temporary password.

                        </div>

                    </div>


                    <!-- ==================================================
                         AGENT 2 EMAIL
                         ================================================== -->

                    <div class="mb-4">

                        <label
                            for="agent2_email"
                            class="form-label">

                            <strong>
                                Agent 2 Email
                            </strong>

                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="agent2_email"
                            name="agent2_email"
                            value="<?= htmlspecialchars($agent2Email) ?>"
                            autocomplete="off"
                            required>

                        <div class="form-text">

                            Agent 2 will receive only the Agent 2
                            username and temporary password.

                        </div>

                    </div>


                    <!-- ==================================================
                         INFORMATION
                         ================================================== -->

                    <div class="alert alert-warning">

                        <strong>
                            Important
                        </strong>

                        <ul class="mb-0 mt-2">

                            <li>
                                All three email addresses must be different.
                            </li>

                            <li>
                                Three separate temporary passwords will
                                be generated.
                            </li>

                            <li>
                                Each account must change its password
                                on first login.
                            </li>

                            <li>
                                The temporary passwords will not be
                                displayed on this page.
                            </li>

                        </ul>

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Complete Demo Setup

                    </button>


                </form>


                <div class="text-center mt-3">

                    <a href="?page=demo-logout">

                        Sign out of Demo

                    </a>

                </div>


            </div>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>