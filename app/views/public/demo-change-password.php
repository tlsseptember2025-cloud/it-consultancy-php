<?php

/*
|--------------------------------------------------------------------------
| DEMO CHANGE PASSWORD
|--------------------------------------------------------------------------
|
| Handles first-login password changes for:
|
| - Demo Admin
| - Demo Customer
| - Demo Agent 1
| - Demo Agent 2
|
|--------------------------------------------------------------------------
*/


require_once CONFIG_PATH . '/demo-database.php';


/*
|--------------------------------------------------------------------------
| Determine Logged-In Demo Account
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoCustomer = isset($_SESSION['demo_customer']);
$isDemoAgent = isset($_SESSION['demo_agent']);


if (
    !$isDemoAdmin &&
    !$isDemoCustomer &&
    !$isDemoAgent
) {

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determine Account Type
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $sessionKey = 'demo_user';
    $accountType = 'admin';

} elseif ($isDemoCustomer) {

    $sessionKey = 'demo_customer';
    $accountType = 'customer';

} else {

    $sessionKey = 'demo_agent';
    $accountType = 'agent';
}


/*
|--------------------------------------------------------------------------
| Get Session Account
|--------------------------------------------------------------------------
*/

$sessionAccount = $_SESSION[$sessionKey];

$accountId = (int)($sessionAccount['id'] ?? 0);
$tenantId = (int)($sessionAccount['demo_tenant_id'] ?? 0);


if (
    $accountId <= 0 ||
    $tenantId <= 0
) {

    unset($_SESSION[$sessionKey]);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Reload Current Account
|--------------------------------------------------------------------------
*/

if ($accountType === 'admin') {

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
        WHERE u.id = ?
          AND u.demo_tenant_id = ?
          AND u.is_demo_account = 1
          AND u.is_super_admin = 0
        LIMIT 1
    ");

} elseif ($accountType === 'customer') {

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
        WHERE c.id = ?
          AND c.demo_tenant_id = ?
          AND c.is_demo_account = 1
        LIMIT 1
    ");

} else {

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
        WHERE a.id = ?
          AND a.demo_tenant_id = ?
          AND a.is_demo_account = 1
          AND a.status = 'Active'
          AND a.active = 1
        LIMIT 1
    ");
}


$stmt->execute([
    $accountId,
    $tenantId
]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$account) {

    unset($_SESSION[$sessionKey]);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Tenant
|--------------------------------------------------------------------------
*/

if ($account['tenant_status'] !== 'Active') {

    unset($_SESSION[$sessionKey]);

    header('Location: ?page=demo-login');
    exit;
}


if (
    $account['expires_at'] !== null &&
    strtotime($account['expires_at']) <= time()
) {

    unset($_SESSION[$sessionKey]);

    header('Location: ?page=demo-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| If Password Change Is Not Required
|--------------------------------------------------------------------------
*/

if ((int)$account['force_password_change'] !== 1) {

    if ($accountType === 'customer') {

        header('Location: ?page=customer-dashboard');
        exit;
    }


    if ($accountType === 'agent') {

        header('Location: ?page=agent-dashboard');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    |
    | Admin may only reach Dashboard after all three Demo accounts
    | have passwords.
    |
    */

    $usernameBase = preg_replace(
        '/_admin$/',
        '',
        $account['username']
    );


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
            ) AS agent1_password,

            (
                SELECT password
                FROM agents
                WHERE demo_tenant_id = ?
                  AND is_demo_account = 1
                  AND username = ?
                LIMIT 1
            ) AS agent2_password
    ");

    $setupStmt->execute([

        (int)$account['demo_tenant_id'],
        $usernameBase . '_customer',

        (int)$account['demo_tenant_id'],
        $usernameBase . '_agent1',

        (int)$account['demo_tenant_id'],
        $usernameBase . '_agent2'

    ]);

    $setupStatus = $setupStmt->fetch(PDO::FETCH_ASSOC);


    if (
        !$setupStatus ||
        empty($setupStatus['customer_password']) ||
        empty($setupStatus['agent1_password']) ||
        empty($setupStatus['agent2_password'])
    ) {

        header('Location: ?page=demo-setup');
        exit;
    }


    header('Location: ?page=dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

$error = '';

$currentTemporaryPassword = '';


/*
|--------------------------------------------------------------------------
| Process Password Change
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate Current Password
    |--------------------------------------------------------------------------
    */

    if ($currentPassword === '') {

        $error =
            'Please enter your current temporary password.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate New Password
    |--------------------------------------------------------------------------
    */

    elseif ($newPassword === '') {

        $error =
            'Please enter a new password.';
    }


    elseif (strlen($newPassword) < 8) {

        $error =
            'Your new password must contain at least 8 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */

    elseif ($confirmPassword === '') {

        $error =
            'Please confirm your new password.';
    }


    elseif ($newPassword !== $confirmPassword) {

        $error =
            'The new password and confirmation password do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Temporary Password
    |--------------------------------------------------------------------------
    */

    elseif (
        empty($account['password']) ||
        !password_verify(
            $currentPassword,
            $account['password']
        )
    ) {

        $error =
            'The current temporary password is incorrect.';
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Reusing Temporary Password
    |--------------------------------------------------------------------------
    */

    elseif (
        password_verify(
            $newPassword,
            $account['password']
        )
    ) {

        $error =
            'Your new password must be different from the temporary password.';
    }


    /*
    |--------------------------------------------------------------------------
    | Update Password
    |--------------------------------------------------------------------------
    */

    else {

        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );


        if ($newPasswordHash === false) {

            $error =
                'The new password could not be created. Please try again.';

        } else {

            try {

                if ($accountType === 'admin') {

                    $stmt = $demoPdo->prepare("
                        UPDATE users
                        SET
                            password = ?,
                            force_password_change = 0
                        WHERE id = ?
                          AND demo_tenant_id = ?
                          AND is_demo_account = 1
                          AND is_super_admin = 0
                    ");

                } elseif ($accountType === 'customer') {

                    $stmt = $demoPdo->prepare("
                        UPDATE customers
                        SET
                            password = ?,
                            force_password_change = 0
                        WHERE id = ?
                          AND demo_tenant_id = ?
                          AND is_demo_account = 1
                    ");

                } else {

                    $stmt = $demoPdo->prepare("
                        UPDATE agents
                        SET
                            password = ?,
                            force_password_change = 0
                        WHERE id = ?
                          AND demo_tenant_id = ?
                          AND is_demo_account = 1
                          AND status = 'Active'
                          AND active = 1
                    ");
                }


                $stmt->execute([
                    $newPasswordHash,
                    $accountId,
                    $tenantId
                ]);


                if ($stmt->rowCount() !== 1) {

                    throw new RuntimeException(
                        'The password could not be updated.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Reload Updated Account Into Session
                |--------------------------------------------------------------------------
                */

                if ($accountType === 'admin') {

                    $reloadStmt = $demoPdo->prepare("
                        SELECT
                            u.*,
                            t.company_name,
                            t.company_domain,
                            t.expires_at,
                            t.status AS tenant_status
                        FROM users u
                        INNER JOIN demo_tenants t
                            ON t.id = u.demo_tenant_id
                        WHERE u.id = ?
                          AND u.demo_tenant_id = ?
                        LIMIT 1
                    ");

                } elseif ($accountType === 'customer') {

                    $reloadStmt = $demoPdo->prepare("
                        SELECT
                            c.*,
                            t.company_name,
                            t.company_domain,
                            t.expires_at,
                            t.status AS tenant_status
                        FROM customers c
                        INNER JOIN demo_tenants t
                            ON t.id = c.demo_tenant_id
                        WHERE c.id = ?
                          AND c.demo_tenant_id = ?
                        LIMIT 1
                    ");

                } else {

                    $reloadStmt = $demoPdo->prepare("
                        SELECT
                            a.*,
                            t.company_name,
                            t.company_domain,
                            t.expires_at,
                            t.status AS tenant_status
                        FROM agents a
                        INNER JOIN demo_tenants t
                            ON t.id = a.demo_tenant_id
                        WHERE a.id = ?
                          AND a.demo_tenant_id = ?
                        LIMIT 1
                    ");
                }


                $reloadStmt->execute([
                    $accountId,
                    $tenantId
                ]);


                $updatedAccount =
                    $reloadStmt->fetch(PDO::FETCH_ASSOC);


                if (!$updatedAccount) {

                    throw new RuntimeException(
                        'The updated Demo account could not be reloaded.'
                    );
                }


                $_SESSION[$sessionKey] =
                    $updatedAccount;


                /*
                |--------------------------------------------------------------------------
                | Customer
                |--------------------------------------------------------------------------
                */

                if ($accountType === 'customer') {

                    header(
                        'Location: ?page=customer-dashboard'
                    );

                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | Agent
                |--------------------------------------------------------------------------
                */

                if ($accountType === 'agent') {

                    header(
                        'Location: ?page=agent-dashboard'
                    );

                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | Admin
                |--------------------------------------------------------------------------
                |
                | Check all three Demo accounts.
                |
                */

                $usernameBase = preg_replace(
                    '/_admin$/',
                    '',
                    $updatedAccount['username']
                );


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
                        ) AS agent1_password,

                        (
                            SELECT password
                            FROM agents
                            WHERE demo_tenant_id = ?
                              AND is_demo_account = 1
                              AND username = ?
                            LIMIT 1
                        ) AS agent2_password
                ");


                $setupStmt->execute([

                    (int)$updatedAccount['demo_tenant_id'],
                    $usernameBase . '_customer',

                    (int)$updatedAccount['demo_tenant_id'],
                    $usernameBase . '_agent1',

                    (int)$updatedAccount['demo_tenant_id'],
                    $usernameBase . '_agent2'

                ]);


                $setupStatus =
                    $setupStmt->fetch(PDO::FETCH_ASSOC);


                if (
                    !$setupStatus ||
                    empty($setupStatus['customer_password']) ||
                    empty($setupStatus['agent1_password']) ||
                    empty($setupStatus['agent2_password'])
                ) {

                    header(
                        'Location: ?page=demo-setup'
                    );

                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | All Demo Accounts Configured
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ?page=dashboard'
                );

                exit;


            } catch (PDOException $e) {

                error_log(
                    'Demo password change failed: '
                    . $e->getMessage()
                );

                $error =
                    'The password could not be changed. Please try again.';

            } catch (RuntimeException $e) {

                error_log(
                    'Demo password change failed: '
                    . $e->getMessage()
                );

                $error =
                    $e->getMessage();
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

    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">


            <div class="card-header bg-dark text-white">

                <h4 class="mb-0">

                    Change Demo Password

                </h4>

            </div>


            <div class="card-body">


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <div class="alert alert-info">

                    <strong>
                        First Login
                    </strong>

                    <br><br>

                    You are using a temporary Demo password.

                    Please create your own password before continuing.

                </div>


                <form
                    method="POST"
                    action="?page=demo-change-password"
                    autocomplete="off">


                    <!-- ==================================================
                         CURRENT PASSWORD
                         ================================================== -->

                    <div class="mb-3">

                        <label
                            for="current_password"
                            class="form-label">

                            Current Temporary Password

                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required>

                    </div>


                    <!-- ==================================================
                         NEW PASSWORD
                         ================================================== -->

                    <div class="mb-3">

                        <label
                            for="new_password"
                            class="form-label">

                            New Password

                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="new_password"
                            name="new_password"
                            autocomplete="new-password"
                            minlength="8"
                            required>

                        <div class="form-text">

                            Minimum 8 characters.

                        </div>

                    </div>


                    <!-- ==================================================
                         CONFIRM PASSWORD
                         ================================================== -->

                    <div class="mb-4">

                        <label
                            for="confirm_password"
                            class="form-label">

                            Confirm New Password

                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            minlength="8"
                            required>

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary w-100">

                        Change Password

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