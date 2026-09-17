<?php

require_once CONFIG_PATH . '/demo-database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {

        $error = 'Please enter your username/email and password.';

    } else {

        /*
         * ------------------------------------------------------------
         * Check Demo Customer
         * ------------------------------------------------------------
         *
         * Customer login uses the immutable username.
         *
         */

        $stmt = $demoPdo->prepare("
            SELECT
                c.*,
                t.company_name,
                t.company_domain,
                t.expires_at,
                t.status AS tenant_status
            FROM customers c
            INNER JOIN demo_tenants t
                ON t.id = c.demo_tenant_id
            WHERE c.username = ?
              AND c.is_demo_account = 1
              AND c.demo_tenant_id IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([$login]);

        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $customer &&
            !empty($customer['password']) &&
            password_verify($password, $customer['password'])
        ) {

            if (
                $customer['tenant_status'] !== 'Active' ||
                (
                    $customer['expires_at'] !== null &&
                    strtotime($customer['expires_at']) <= time()
                )
            ) {

                $error = 'This Demo account has expired.';

            } else {

                unset(
                    $_SESSION['user'],
                    $_SESSION['customer'],
                    $_SESSION['agent'],
                    $_SESSION['demo_user'],
                    $_SESSION['demo_agent']
                );

                $_SESSION['demo_customer'] = $customer;

                /*
                 * ----------------------------------------------------
                 * First Login Password Change
                 * ----------------------------------------------------
                 */

                if ((int) $customer['force_password_change'] === 1) {

                    header('Location: ?page=demo-change-password');
                    exit;
                }

                header('Location: ?page=customer-dashboard');
                exit;
            }
        }


        /*
         * ------------------------------------------------------------
         * Check Demo Agent
         * ------------------------------------------------------------
         *
         * Agent login uses the immutable username.
         *
         */

        if ($error === '') {

            $stmt = $demoPdo->prepare("
                SELECT
                    a.*,
                    t.company_name,
                    t.company_domain,
                    t.expires_at,
                    t.status AS tenant_status
                FROM agents a
                INNER JOIN demo_tenants t
                    ON t.id = a.demo_tenant_id
                WHERE a.username = ?
                  AND a.is_demo_account = 1
                  AND a.demo_tenant_id IS NOT NULL
                  AND a.status = 'Active'
                LIMIT 1
            ");

            $stmt->execute([$login]);

            $agent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $agent &&
                !empty($agent['password']) &&
                password_verify($password, $agent['password'])
            ) {

                if (
                    $agent['tenant_status'] !== 'Active' ||
                    (
                        $agent['expires_at'] !== null &&
                        strtotime($agent['expires_at']) <= time()
                    )
                ) {

                    $error = 'This Demo account has expired.';

                } else {

                    unset(
                        $_SESSION['user'],
                        $_SESSION['customer'],
                        $_SESSION['agent'],
                        $_SESSION['demo_user'],
                        $_SESSION['demo_customer']
                    );

                    $_SESSION['demo_agent'] = $agent;

                    /*
                     * ------------------------------------------------
                     * First Login Password Change
                     * ------------------------------------------------
                     */

                    if ((int) $agent['force_password_change'] === 1) {

                        header('Location: ?page=demo-change-password');
                        exit;
                    }

                    header('Location: ?page=agent-dashboard');
                    exit;
                }
            }
        }


        /*
         * ------------------------------------------------------------
         * Check Demo Admin
         * ------------------------------------------------------------
         *
         * Demo Admin currently continues to use EMAIL login.
         *
         * Customer and Agent use USERNAME login.
         *
         */

        if ($error === '') {

            $stmt = $demoPdo->prepare("
                SELECT
                    u.*,
                    t.company_name,
                    t.company_domain,
                    t.expires_at,
                    t.status AS tenant_status
                FROM users u
                INNER JOIN demo_tenants t
                    ON t.id = u.demo_tenant_id
                WHERE u.email = ?
                  AND u.is_demo_account = 1
                  AND u.is_super_admin = 0
                  AND u.demo_tenant_id IS NOT NULL
                LIMIT 1
            ");

            $stmt->execute([$login]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                !empty($user['password']) &&
                password_verify($password, $user['password'])
            ) {

                if (
                    $user['tenant_status'] !== 'Active' ||
                    (
                        $user['expires_at'] !== null &&
                        strtotime($user['expires_at']) <= time()
                    )
                ) {

                    $error = 'This Demo account has expired.';

                } else {

                    unset(
                        $_SESSION['user'],
                        $_SESSION['customer'],
                        $_SESSION['agent'],
                        $_SESSION['demo_customer'],
                        $_SESSION['demo_agent'],
                        $_SESSION['demo_super_admin']
                    );

                    $_SESSION['demo_user'] = $user;


                    /*
                     * ------------------------------------------------
                     * First Login Password Change
                     * ------------------------------------------------
                     *
                     * Demo Admin must change the temporary password
                     * on the first login before accessing Demo Setup.
                     *
                     */

                    if ((int) $user['force_password_change'] === 1) {

                        header('Location: ?page=demo-change-password');
                        exit;
                    }


                    /*
                     * ------------------------------------------------
                     * Check Demo Setup Status
                     * ------------------------------------------------
                     *
                     * Customer and Agent accounts are created during
                     * provisioning, but their passwords remain NULL
                     * until Demo Setup is completed.
                     *
                     */

                    $setupStmt = $demoPdo->prepare("
                        SELECT
                            (
                                SELECT password
                                FROM customers
                                WHERE demo_tenant_id = ?
                                  AND is_demo_account = 1
                                  AND username = ?
                                LIMIT 1
                            ) AS customer_password,

                            (
                                SELECT password
                                FROM agents
                                WHERE demo_tenant_id = ?
                                  AND is_demo_account = 1
                                  AND username = ?
                                LIMIT 1
                            ) AS agent_password
                    ");

                    $usernameBase = preg_replace(
                        '/_admin$/',
                        '',
                        $user['username']
                    );

                    $setupStmt->execute([
                        (int) $user['demo_tenant_id'],
                        $usernameBase . '_customer',
                        (int) $user['demo_tenant_id'],
                        $usernameBase . '_agent'
                    ]);

                    $setupStatus = $setupStmt->fetch(PDO::FETCH_ASSOC);


                    /*
                     * ------------------------------------------------
                     * Setup Required
                     * ------------------------------------------------
                     */

                    if (
                        !$setupStatus ||
                        empty($setupStatus['customer_password']) ||
                        empty($setupStatus['agent_password'])
                    ) {

                        header('Location: ?page=demo-setup');
                        exit;
                    }


                    /*
                     * ------------------------------------------------
                     * Setup Already Completed
                     * ------------------------------------------------
                     */

                    header('Location: ?page=dashboard');
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

            $error = 'Invalid Demo username/email or password.';
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
                            Username / Email
                        </label>

                        <input
                            type="text"
                            name="login"
                            class="form-control"
                            autocomplete="off"
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

                        <a href="?page=demo-forgot-password">

                            Forgot Demo Password?

                        </a>

                    </p>

                    <hr>

                    <p class="text-center mb-2">

                        <a
                            href="?page=demo-super-admin-login"
                            class="small text-secondary text-decoration-none">

                            Wahbib Admin Login

                        </a>

                    </p>

                    <p class="text-center mb-0">

                        <a
                            href="?page=home"
                            class="small text-secondary text-decoration-none">

                            Back to Main Website

                        </a>

                    </p>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>