<?php

/*
|--------------------------------------------------------------------------
| DEMO LOGIN
|--------------------------------------------------------------------------
| Separate authentication for temporary demo accounts.
|
| Demo users are NOT customers, agents, or administrators.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Prevent Already Logged-In Users From Accessing Demo Login
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


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$username = '';


/*
|--------------------------------------------------------------------------
| DEMO LOGIN SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($username === '') {

        $error = 'Please enter your demo username.';

    } elseif ($password === '') {

        $error = 'Please enter your demo password.';

    }


    /*
    |--------------------------------------------------------------------------
    | Find Demo User
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | Load Demo Account
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            $demoUser = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Invalid Username / Password
            |--------------------------------------------------------------------------
            */

            if (
                !$demoUser ||
                !password_verify(
                    $password,
                    $demoUser['password_hash']
                )
            ) {

                $error = 'Invalid demo username or password.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | Check Account Status
                |--------------------------------------------------------------------------
                */

                if ($demoUser['status'] === 'Rejected') {

                    $error =
                        'This demo account is no longer available.';

                } elseif ($demoUser['status'] === 'Expired') {

                    $error =
                        'This demo account has expired.';

                } elseif ($demoUser['status'] !== 'Active') {

                    $error =
                        'This demo account is not currently available.';

                }


                /*
                |--------------------------------------------------------------------------
                | Check Existing Expiration
                |--------------------------------------------------------------------------
                */

                if (
                    $error === ''
                    && !empty($demoUser['expires_at'])
                    && strtotime($demoUser['expires_at']) <= time()
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Mark Account Expired
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE demo_users
                        SET status = 'Expired'
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        (int) $demoUser['id']
                    ]);


                    $error =
                        'This demo account has expired.';

                }


                /*
                |--------------------------------------------------------------------------
                | FIRST SUCCESSFUL LOGIN
                |--------------------------------------------------------------------------
                |
                | The 5-day demo period starts here.
                |--------------------------------------------------------------------------
                */

                if (
                    $error === ''
                    && empty($demoUser['first_login_at'])
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Start Demo Period
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE demo_users
                        SET
                            first_login_at = NOW(),
                            expires_at = DATE_ADD(NOW(), INTERVAL 5 DAY)
                        WHERE id = ?
                        AND status = 'Active'
                        AND first_login_at IS NULL
                    ");

                    $stmt->execute([
                        (int) $demoUser['id']
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Reload Updated Account
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

                        $error =
                            'Unable to start the demo account. Please try again.';

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Create Demo Session
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    /*
                    |--------------------------------------------------------------------------
                    | Final Expiration Check
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($demoUser['expires_at'])
                        && strtotime($demoUser['expires_at']) <= time()
                    ) {

                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET status = 'Expired'
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            (int) $demoUser['id']
                        ]);


                        $error =
                            'This demo account has expired.';

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Successful Login
                |--------------------------------------------------------------------------
                */

                if ($error === '') {

                    /*
                    |--------------------------------------------------------------------------
                    | Prevent Session Fixation
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | Store Demo Session
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['demo_user'] = $demoUser;


                    /*
                    |--------------------------------------------------------------------------
                    | Redirect
                    |--------------------------------------------------------------------------
                    */

                    header('Location: ?page=demo-dashboard');
                    exit;

                }

            }

        } catch (PDOException $e) {

            error_log(
                'Demo login failed: '
                . $e->getMessage()
            );

            $error =
                'Unable to process the demo login right now. '
                . 'Please try again later.';

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

                        Your demo account is temporary.

                        <br><br>

                        The 5-day demo period begins after your
                        first successful login.

                        <br><br>

                        Demo data is separate from normal customer,
                        agent, and administrator accounts.

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


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>