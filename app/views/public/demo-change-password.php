<?php

/*
|--------------------------------------------------------------------------
| Demo First-Login Password Change
|--------------------------------------------------------------------------
|
| Used only when a Demo account has:
|
| force_password_change = 1
|
| Supported Demo roles:
| - Demo Admin
| - Demo Customer
| - Demo Agent
|
*/

require_once CONFIG_PATH . '/demo-database.php';


/*
|--------------------------------------------------------------------------
| Determine Active Demo Session
|--------------------------------------------------------------------------
*/

$role = null;
$userId = null;

if (isset($_SESSION['demo_user'])) {

    $role = 'admin';
    $userId = (int) $_SESSION['demo_user']['id'];

} elseif (isset($_SESSION['demo_customer'])) {

    $role = 'customer';
    $userId = (int) $_SESSION['demo_customer']['id'];

} elseif (isset($_SESSION['demo_agent'])) {

    $role = 'agent';
    $userId = (int) $_SESSION['demo_agent']['id'];

} else {

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Current Demo Account
|--------------------------------------------------------------------------
*/

if ($role === 'admin') {

    $stmt = $demoPdo->prepare("
        SELECT
            id,
            username,
            email,
            force_password_change
        FROM users
        WHERE
            id = ?
            AND is_demo_account = 1
            AND is_super_admin = 0
        LIMIT 1
    ");

} elseif ($role === 'customer') {

    $stmt = $demoPdo->prepare("
        SELECT
            id,
            username,
            email,
            force_password_change
        FROM customers
        WHERE
            id = ?
            AND is_demo_account = 1
        LIMIT 1
    ");

} else {

    $stmt = $demoPdo->prepare("
        SELECT
            id,
            username,
            email,
            force_password_change
        FROM agents
        WHERE
            id = ?
            AND is_demo_account = 1
        LIMIT 1
    ");
}

$stmt->execute([$userId]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Account Validation
|--------------------------------------------------------------------------
*/

if (!$account) {

    session_destroy();

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Password Change Already Completed
|--------------------------------------------------------------------------
|
| If force_password_change is already 0, the user does not belong
| on this page.
|
*/

if ((int) $account['force_password_change'] !== 1) {

    if ($role === 'admin') {

        header('Location: ?page=dashboard');

    } elseif ($role === 'customer') {

        header('Location: ?page=customer-dashboard');

    } else {

        header('Location: ?page=agent-dashboard');
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Process Password Change
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
     * Password validation
     */

    if ($password === '' || $confirmPassword === '') {

        $error = 'Please enter and confirm your new password.';

    } elseif ($password !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } elseif (strlen($password) < 8) {

        $error = 'Password must be at least 8 characters long.';

    } else {

        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        /*
         * Update the correct Demo account.
         */

        if ($role === 'admin') {

            $stmt = $demoPdo->prepare("
                UPDATE users
                SET
                    password = ?,
                    force_password_change = 0
                WHERE id = ?
            ");

        } elseif ($role === 'customer') {

            $stmt = $demoPdo->prepare("
                UPDATE customers
                SET
                    password = ?,
                    force_password_change = 0
                WHERE id = ?
            ");

        } else {

            $stmt = $demoPdo->prepare("
                UPDATE agents
                SET
                    password = ?,
                    force_password_change = 0
                WHERE id = ?
            ");
        }


        $stmt->execute([
            $hash,
            $userId
        ]);


        /*
         * Redirect according to Demo role.
         */

        if ($role === 'admin') {

            header('Location: ?page=demo-setup');

        } elseif ($role === 'customer') {

            header('Location: ?page=customer-dashboard');

        } else {

            header('Location: ?page=agent-dashboard');
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-public.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Change Your Password
                    </h4>

                </div>

                <div class="card-body">

                    <div class="alert alert-info">

                        You are logging in for the first time.

                        Please create a new password that you can
                        easily remember for future logins.

                    </div>


                    <?php if (!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <form method="POST">

                        <div class="mb-3">

                            <label
                                for="password"
                                class="form-label">

                                New Password

                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                required>

                        </div>


                        <div class="mb-4">

                            <label
                                for="confirm_password"
                                class="form-label">

                                Confirm New Password

                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                minlength="8"
                                required>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            Change Password

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>