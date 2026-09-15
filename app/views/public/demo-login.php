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
         * Check Demo Admin
         * ------------------------------------------------------------
         */

        $stmt = $demoPdo->prepare("
            SELECT u.*, t.company_name, t.company_domain, t.expires_at, t.status AS tenant_status
            FROM users u
            INNER JOIN demo_tenants t
                ON t.id = u.demo_tenant_id
            WHERE u.email = ?
              AND u.is_demo_account = 1
              AND u.demo_tenant_id IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            password_verify($password, $user['password'])
        ) {

            if (
                $user['tenant_status'] !== 'Active' ||
                strtotime($user['expires_at']) <= time()
            ) {

                $error = 'This Demo account has expired.';

            } else {

                unset(
                    $_SESSION['user'],
                    $_SESSION['customer'],
                    $_SESSION['agent']
                );

                $_SESSION['demo_user'] = $user;

                header('Location: ?page=dashboard');
                exit;
            }
        }


        /*
         * ------------------------------------------------------------
         * Check Demo Customer
         * ------------------------------------------------------------
         */

        if ($error === '') {

            $stmt = $demoPdo->prepare("
                SELECT c.*, t.company_name, t.company_domain, t.expires_at, t.status AS tenant_status
                FROM customers c
                INNER JOIN demo_tenants t
                    ON t.id = c.demo_tenant_id
                WHERE c.email = ?
                  AND c.is_demo_account = 1
                  AND c.demo_tenant_id IS NOT NULL
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $customer &&
                password_verify($password, $customer['password'])
            ) {

                if (
                    $customer['tenant_status'] !== 'Active' ||
                    strtotime($customer['expires_at']) <= time()
                ) {

                    $error = 'This Demo account has expired.';

                } else {

                    unset(
                        $_SESSION['user'],
                        $_SESSION['customer'],
                        $_SESSION['agent']
                    );

                    $_SESSION['demo_customer'] = $customer;

                    header('Location: ?page=customer-dashboard');
                    exit;
                }
            }
        }


        /*
         * ------------------------------------------------------------
         * Check Demo Agent
         * ------------------------------------------------------------
         */

        if ($error === '') {

            $stmt = $demoPdo->prepare("
                SELECT a.*, t.company_name, t.company_domain, t.expires_at, t.status AS tenant_status
                FROM agents a
                INNER JOIN demo_tenants t
                    ON t.id = a.demo_tenant_id
                WHERE a.email = ?
                  AND a.is_demo_account = 1
                  AND a.demo_tenant_id IS NOT NULL
                  AND a.status = 'Active'
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $agent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $agent &&
                password_verify($password, $agent['password'])
            ) {

                if (
                    $agent['tenant_status'] !== 'Active' ||
                    strtotime($agent['expires_at']) <= time()
                ) {

                    $error = 'This Demo account has expired.';

                } else {

                    unset(
                        $_SESSION['user'],
                        $_SESSION['customer'],
                        $_SESSION['agent']
                    );

                    $_SESSION['demo_agent'] = $agent;

                    header('Location: ?page=agent-dashboard');
                    exit;
                }
            }
        }


        /*
         * ------------------------------------------------------------
         * Invalid Login
         * ------------------------------------------------------------
         */

        if ($error === '') {
            $error = 'Invalid Demo email or password.';
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
                    Demo Login
                </h2>

                <p class="text-muted text-center mb-4">
                    Sign in to your Demo account.
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

                    <p class="mt-3 text-center mb-0">

                        <a
                            href="?page=public-login"
                            class="small text-secondary text-decoration-none">

                            Main Website Login

                        </a>

                    </p>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>

