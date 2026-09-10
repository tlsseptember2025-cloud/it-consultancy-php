<?php

/*
|--------------------------------------------------------------------------
| DEMO LOGIN
|--------------------------------------------------------------------------
| Separate authentication for temporary demo accounts.
|
| Fixed demo accounts:
|   user     = Admin
|   customer = Customer
|   agent    = Agent
|
| Demo sessions are completely separate from normal application sessions.
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user'])) {
    header('Location: ?page=dashboard');
    exit;
}

if (isset($_SESSION['customer'])) {
    header('Location: ?page=customer-dashboard');
    exit;
}

if (isset($_SESSION['agent'])) {
    header('Location: ?page=agent-dashboard');
    exit;
}

if (isset($_SESSION['demo_user'])) {
    header('Location: ?page=demo-dashboard');
    exit;
}

if (isset($_SESSION['demo_customer'])) {
    header('Location: ?page=demo-customer-dashboard');
    exit;
}

if (isset($_SESSION['demo_agent'])) {
    header('Location: ?page=demo-agent-dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$error = '';
$username = '';
$userType = '';

$genericError =
    'Unable to sign in. Please check your credentials and try again.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | User Type -> Database Role
    |--------------------------------------------------------------------------
    |
    | Admin     -> admin
    | Customer  -> customer
    | Agent     -> agent
    |--------------------------------------------------------------------------
    */

    $roleMap = [
        'admin'    => 'admin',
        'customer' => 'customer',
        'agent'    => 'agent'
    ];

    $selectedRole = $roleMap[$userType] ?? null;

    if (
        $username === ''
        || $password === ''
        || $selectedRole === null
    ) {

        $error = $genericError;

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find Demo Account
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([
                $username
            ]);

            $demoUser = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Verify Username + Password + Role
            |--------------------------------------------------------------------------
            */

            if (
                !$demoUser
                || !password_verify(
                    $password,
                    $demoUser['password_hash']
                )
                || $demoUser['role'] !== $selectedRole
            ) {

                $error = $genericError;

            } else {

                /*
                |--------------------------------------------------------------------------
                | Shared Demo 5-Day Period
                |--------------------------------------------------------------------------
                |
                | Whichever of the three fixed Demo accounts logs in FIRST
                | starts the Demo period. That exact first-login time and
                | expiration time are then shared by ALL THREE accounts.
                |
                | Later logins NEVER reset or extend the 5-day period.
                |--------------------------------------------------------------------------
                */

                if ($demoUser['status'] !== 'Active') {
                    $error = $genericError;
                }

                if ($error === '') {

                    /*
                    |--------------------------------------------------------------------------
                    | Find the existing shared Demo period
                    |--------------------------------------------------------------------------
                    |
                    | MIN() lets us recover safely if older test data has
                    | different first_login_at / expires_at values.
                    | The earliest recorded first login becomes the shared
                    | Demo start time.
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT
                            MIN(first_login_at) AS shared_first_login,
                            MIN(expires_at) AS shared_expires_at
                        FROM demo_users
                        WHERE username IN ('user', 'customer', 'agent')
                          AND first_login_at IS NOT NULL
                    ");

                    $stmt->execute();

                    $sharedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);


                    /*
                    |--------------------------------------------------------------------------
                    | Start the shared Demo period if nobody has logged in yet
                    |--------------------------------------------------------------------------
                    */

                    if (empty($sharedPeriod['shared_first_login'])) {

                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET
                                first_login_at = NOW(),
                                expires_at = DATE_ADD(NOW(), INTERVAL 5 DAY)
                            WHERE username IN ('user', 'customer', 'agent')
                              AND status = 'Active'
                        ");

                        $stmt->execute();

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Shared Demo period already exists
                        |--------------------------------------------------------------------------
                        |
                        | Synchronize all three accounts to the original
                        | Demo start and expiry. Never extend the timer.
                        |--------------------------------------------------------------------------
                        */

                        $sharedFirstLogin = $sharedPeriod['shared_first_login'];

                        $sharedExpires = date(
                            'Y-m-d H:i:s',
                            strtotime($sharedFirstLogin . ' +5 days')
                        );

                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET
                                first_login_at = ?,
                                expires_at = ?
                            WHERE username IN ('user', 'customer', 'agent')
                        ");

                        $stmt->execute([
                            $sharedFirstLogin,
                            $sharedExpires
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Reload the logged-in account with the shared values
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM demo_users
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        (int) $demoUser['id']
                    ]);

                    $demoUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$demoUser) {
                        $error = $genericError;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Shared Expiration Check
                |--------------------------------------------------------------------------
                |
                | When the shared 5-day period expires, ALL THREE fixed
                | Demo accounts are marked Expired together.
                |--------------------------------------------------------------------------
                */

                if ($error === '' && !empty($demoUser['expires_at'])) {

                    if (strtotime($demoUser['expires_at']) <= time()) {

                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET status = 'Expired'
                            WHERE username IN ('user', 'customer', 'agent')
                        ");

                        $stmt->execute();

                        $error = $genericError;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Successful Demo Login
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | Clear ONLY Demo Sessions
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    | Normal $_SESSION['user'], $_SESSION['customer'],
                    | and $_SESSION['agent'] are NOT touched.
                    |--------------------------------------------------------------------------
                    */

                    unset(
                        $_SESSION['demo_user'],
                        $_SESSION['demo_customer'],
                        $_SESSION['demo_agent']
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Create Isolated Demo Session
                    |--------------------------------------------------------------------------
                    */

                    if ($demoUser['role'] === 'admin') {

                        $_SESSION['demo_user'] = $demoUser;

                        header(
                            'Location: ?page=demo-dashboard'
                        );

                        exit;

                    } elseif (
                        $demoUser['role'] === 'customer'
                    ) {

                        $_SESSION['demo_customer'] = $demoUser;

                        header(
                            'Location: ?page=demo-customer-dashboard'
                        );

                        exit;

                    } elseif (
                        $demoUser['role'] === 'agent'
                    ) {

                        $_SESSION['demo_agent'] = $demoUser;

                        header(
                            'Location: ?page=demo-agent-dashboard'
                        );

                        exit;
                    }


                    $error = $genericError;
                }
            }

        } catch (PDOException $e) {

            error_log(
                'Demo login failed: '
                . $e->getMessage()
            );

            $error = $genericError;
        }
    }
}


require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-5 col-md-6">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-2 text-center">
                    🖥 Demo Login
                </h2>

                <p class="text-muted text-center mb-4">
                    Sign in using the demo credentials provided to you.
                </p>

                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>

                <form
                    method="POST"
                    action="?page=demo-login"
                    autocomplete="off">

                    <!-- ====================================================
                         USER TYPE
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="user_type"
                            class="form-label">

                            User Type

                        </label>

                        <select
                            class="form-select"
                            id="user_type"
                            name="user_type"
                            required>

                            <option
                                value=""
                                <?= $userType === ''
                                    ? 'selected'
                                    : '' ?>>

                                Select User Type

                            </option>

                            <option
                                value="admin"
                                <?= $userType === 'admin'
                                    ? 'selected'
                                    : '' ?>>

                                Admin

                            </option>

                            <option
                                value="customer"
                                <?= $userType === 'customer'
                                    ? 'selected'
                                    : '' ?>>

                                Customer

                            </option>

                            <option
                                value="agent"
                                <?= $userType === 'agent'
                                    ? 'selected'
                                    : '' ?>>

                                Agent

                            </option>

                        </select>

                    </div>


                    <!-- ====================================================
                         USERNAME
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="username"
                            class="form-label">

                            Demo Username

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            maxlength="100"
                            value="<?= htmlspecialchars($username) ?>"
                            autocomplete="username"
                            required>

                    </div>


                    <!-- ====================================================
                         PASSWORD
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="password"
                            class="form-label">

                            Demo Password

                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required>

                    </div>


                    <!-- ====================================================
                         DEMO INFORMATION
                         ==================================================== -->

                    <div class="alert alert-info">

                        <strong>Demo Account</strong>

                        <br><br>

                        Your demo access is temporary.

                        <br><br>

                        The 5-day demo period begins after the first
                        successful login for the selected demo account.

                        <br><br>

                        All three demo accounts share the same
                        expiration date.

                        <br><br>

                        Demo accounts are separate from normal
                        customer, agent, and administrator accounts.

                    </div>


                    <!-- ====================================================
                         LOGIN
                         ==================================================== -->

                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Sign In to Demo

                    </button>


                    <div class="text-center mt-3">

                        <a href="?page=home">
                            Back to Main Website
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>
