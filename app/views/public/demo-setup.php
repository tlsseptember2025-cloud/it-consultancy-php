<?php

/*
|--------------------------------------------------------------------------
| Demo Admin Setup
|--------------------------------------------------------------------------
| First-login setup for the Company Demo Admin.
|
| The Demo Admin can provide:
| - Customer email
| - Agent email
|
| The Demo Admin cannot change:
| - Admin email
| - Usernames
| - Company/domain
| - Password
| - Other account information
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/demo-database.php';
require_once HELPER_PATH . '/email.php';


/*
|--------------------------------------------------------------------------
| Require Company Demo Admin
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_user'])) {
    header('Location: ?page=demo-login');
    exit;
}


$demoUser = $_SESSION['demo_user'];

$tenantId = (int)($demoUser['demo_tenant_id'] ?? 0);

if ($tenantId <= 0) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Demo Tenant
|--------------------------------------------------------------------------
*/

$stmt = $demoPdo->prepare("
    SELECT
        id,
        company_name,
        company_domain,
        registered_email,
        status,
        expires_at
    FROM demo_tenants
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$tenantId]);

$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    unset($_SESSION['demo_user']);
    header('Location: ?page=demo-login');
    exit;
}


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
        force_password_change
    FROM customers
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND username = ?
    LIMIT 1
");

$stmt->execute([
    $tenantId,
    preg_replace(
        '/_admin$/',
        '_customer',
        $demoUser['username'] ?? ''
    )
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Demo Agent
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
        active
    FROM agents
    WHERE demo_tenant_id = ?
      AND is_demo_account = 1
      AND username = ?
    LIMIT 1
");

$stmt->execute([
    $tenantId,
    preg_replace(
        '/_admin$/',
        '_agent',
        $demoUser['username'] ?? ''
    )
]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);


// Demo Setup is already complete — do not allow access again.
if (
    !empty($customer['password']) &&
    !empty($agent['password'])
) {
    header('Location: ?page=dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Demo Accounts
|--------------------------------------------------------------------------
*/

if (!$customer || !$agent) {
    die(
        'Demo Setup cannot continue because the Customer or Agent account '
        . 'could not be found.'
    );
}


$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| Handle Setup Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customerEmail = trim($_POST['customer_email'] ?? '');
    $agentEmail    = trim($_POST['agent_email'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validate Customer Email
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
    | Validate Agent Email
    |--------------------------------------------------------------------------
    */

    if ($error === '' && (
        $agentEmail === '' ||
        !filter_var($agentEmail, FILTER_VALIDATE_EMAIL)
    )) {
        $error = 'Please enter a valid Agent email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Same Email
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        strcasecmp($customerEmail, $agentEmail) === 0
    ) {
        $error = 'Customer and Agent must use different email addresses.';
    }


    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        /*
         * Passwords will be generated here.
         * They will be stored only as secure hashes.
         */

        $customerPassword = bin2hex(random_bytes(8));
        $agentPassword    = bin2hex(random_bytes(8));

        $customerPasswordHash = password_hash(
            $customerPassword,
            PASSWORD_DEFAULT
        );

        $agentPasswordHash = password_hash(
            $agentPassword,
            PASSWORD_DEFAULT
        );


        try {

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


            /*
            |--------------------------------------------------------------------------
            | Update Agent
            |--------------------------------------------------------------------------
            */

            $stmt = $demoPdo->prepare("
                UPDATE agents
                SET
                    email = ?,
                    password = ?,
                    force_password_change = 1
                WHERE id = ?
                  AND demo_tenant_id = ?
                  AND is_demo_account = 1
            ");

            $stmt->execute([
                $agentEmail,
                $agentPasswordHash,
                (int)$agent['id'],
                $tenantId
            ]);


        $demoPdo->commit();


/*
|--------------------------------------------------------------------------
| Send Customer Credentials
|--------------------------------------------------------------------------
*/

$loginLink = rtrim(APP_URL, '/') . '/?page=demo-login';

$customerEmailBody = "
    <h2>Demo Customer Account</h2>

    <p>
        Your Demo Customer account has been created.
    </p>

    <p>
        <strong>Username:</strong>
        " . htmlspecialchars($customer['username']) . "
    </p>

    <p>
        <strong>Password:</strong>
        <code>" . htmlspecialchars($customerPassword) . "</code>
    </p>

    <p>
        <strong>Demo Login:</strong>
        <a href=\"" . htmlspecialchars($loginLink) . "\">
            Open Demo Login
        </a>
    </p>

    <p>
        You will be asked to change your temporary password
        when you first sign in.
    </p>

    <p>
        Kind Regards,<br>
        <strong>IT Consultancy Team</strong>
    </p>
";


$customerEmailSent = sendEmail(
    $customerEmail,
    'Your Demo Customer Credentials',
    $customerEmailBody
);


/*
|--------------------------------------------------------------------------
| Send Agent Credentials
|--------------------------------------------------------------------------
*/

$agentEmailBody = "
    <h2>Demo Agent Account</h2>

    <p>
        Your Demo Agent account has been created.
    </p>

    <p>
        <strong>Username:</strong>
        " . htmlspecialchars($agent['username']) . "
    </p>

    <p>
        <strong>Password:</strong>
        <code>" . htmlspecialchars($agentPassword) . "</code>
    </p>

    <p>
        <strong>Demo Login:</strong>
        <a href=\"" . htmlspecialchars($loginLink) . "\">
            Open Demo Login
        </a>
    </p>

    <p>
        You will be asked to change your temporary password
        when you first sign in.
    </p>

    <p>
        Kind Regards,<br>
        <strong>IT Consultancy Team</strong>
    </p>
";


$agentEmailSent = sendEmail(
    $agentEmail,
    'Your Demo Agent Credentials',
    $agentEmailBody
);


/*
|--------------------------------------------------------------------------
| Setup Result
|--------------------------------------------------------------------------
*/

if ($customerEmailSent && $agentEmailSent) {

    header('Location: ?page=dashboard');
    exit;

}

elseif ($customerEmailSent && !$agentEmailSent) {

    $error =
        'Demo Setup was completed, but the Agent credentials '
        . 'could not be sent by email.';

} elseif (!$customerEmailSent && $agentEmailSent) {

    $error =
        'Demo Setup was completed, but the Customer credentials '
        . 'could not be sent by email.';

} else {

    $error =
        'Demo Setup was completed, but the Customer and Agent '
        . 'credential emails could not be sent.';
}


/*
|--------------------------------------------------------------------------
| Refresh Account Data
|--------------------------------------------------------------------------
*/

$customer['email'] = $customerEmail;
$agent['email'] = $agentEmail;


        } catch (PDOException $e) {

            if ($demoPdo->inTransaction()) {
                $demoPdo->rollBack();
            }

            error_log(
                'Demo Setup failed: ' . $e->getMessage()
            );

            $error =
                'Demo Setup could not be completed. '
                . 'Please try again.';
        }
    }
}


require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Demo Setup
                    </h4>

                </div>


                <div class="card-body p-4">

                    <h5 class="mb-3">
                        Welcome to your Demo
                    </h5>

                    <p class="text-muted">
                        Before you start using the system, please provide
                        the email addresses for the Demo Customer and
                        Demo Agent accounts.
                    </p>


                    <?php if ($error): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>


                    <?php if ($success): ?>

                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>

                    <?php endif; ?>


                    <div class="alert alert-light border">

                        <strong>Company:</strong>
                        <?= htmlspecialchars($tenant['company_name'] ?? '') ?>

                        <br>

                        <strong>Domain:</strong>
                        <?= htmlspecialchars($tenant['company_domain'] ?? '') ?>

                    </div>


                    <form method="POST" autocomplete="off">


                        <!-- Customer -->

                        <div class="mb-4">

                            <label
                                for="customer_email"
                                class="form-label fw-semibold">

                                Customer Email

                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="customer_email"
                                name="customer_email"
                                value="<?= htmlspecialchars(
                                    $_POST['customer_email']
                                    ?? $customer['email']
                                    ?? ''
                                ) ?>"
                                required>

                            <div class="form-text">
                                This email will be used by the Demo Customer
                                to sign in.
                            </div>

                        </div>


                        <!-- Agent -->

                        <div class="mb-4">

                            <label
                                for="agent_email"
                                class="form-label fw-semibold">

                                Agent Email

                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="agent_email"
                                name="agent_email"
                                value="<?= htmlspecialchars(
                                    $_POST['agent_email']
                                    ?? $agent['email']
                                    ?? ''
                                ) ?>"
                                required>

                            <div class="form-text">
                                This email will be used by the Demo Agent
                                to sign in.
                            </div>

                        </div>


                        <!-- Locked Admin Information -->

                        <div class="alert alert-secondary">

                            <strong>Demo Admin</strong>

                            <br>

                            Username:
                            <code>
                                <?= htmlspecialchars(
                                    $demoUser['username'] ?? ''
                                ) ?>
                            </code>

                            <br>

                            Email:
                            <code>
                                <?= htmlspecialchars(
                                    $demoUser['email'] ?? ''
                                ) ?>
                            </code>

                            <div class="small mt-2">
                                Demo Admin account information cannot be
                                changed during Setup.
                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary w-100">

                            Complete Demo Setup

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>