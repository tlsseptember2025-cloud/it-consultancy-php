<?php

require_once CONFIG_PATH . '/demo-database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {

        $error = 'Please enter your email address and password.';

    } else {

        /*
         * ------------------------------------------------------------
         * Check Wahbib Demo Super Admin
         * ------------------------------------------------------------
         *
         * This is the permanent Demo Super Admin account.
         *
         * It is:
         * - Not linked to a Demo tenant
         * - Not marked as a Demo account
         * - Marked as Super Admin
         *
         * The Super Admin has global access to the Demo DB.
         */

        $stmt = $demoPdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
              AND is_super_admin = 1
              AND is_demo_account = 0
              AND demo_tenant_id IS NULL
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            password_verify($password, $user['password'])
        ) {

            unset(
                $_SESSION['user'],
                $_SESSION['customer'],
                $_SESSION['agent'],
                $_SESSION['demo_user'],
                $_SESSION['demo_customer'],
                $_SESSION['demo_agent']
            );

            $_SESSION['demo_super_admin'] = $user;

            header('Location: ?page=demo-super-admin');
            exit;
        }

        $error = 'Invalid Wahbib Admin email or password.';
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-5 col-md-6">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-2 text-center">
                    Wahbib Admin Login
                </h2>

                <p class="text-muted text-center mb-4">
                    Sign in to the Demo Super Admin control center.
                </p>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="new-email"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Sign In

                    </button>

                    <p class="mt-3 text-center mb-2">

                        <a href="?page=demo-super-admin-forgot-password">

                            Forgot Password?

                        </a>

                    </p>

                    <hr>

                    <p class="text-center mb-0">

                        <a
                            href="?page=demo-login"
                            class="small text-secondary text-decoration-none">

                            Back to Demo Login

                        </a>

                    </p>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>