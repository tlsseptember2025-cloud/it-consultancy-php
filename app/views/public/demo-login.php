<?php

/*
|--------------------------------------------------------------------------
| DEMO LOGIN
|--------------------------------------------------------------------------
| Separate authentication for temporary Demo accounts.
|
| Each approved Demo request owns one isolated workspace containing:
|   - Admin
|   - Customer
|   - Agent
|
| The 5-day Demo period is shared by all three accounts in that workspace.
| The temporary password must be changed on first successful login.
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

$genericError =
    'Unable to sign in. Please check your credentials and try again.';

$error = '';
$username = '';
$userType = '';

/*
|--------------------------------------------------------------------------
| Helper: redirect an already authenticated Demo session
|--------------------------------------------------------------------------
*/

function redirectExistingDemoSession(PDO $pdo): void
{
    $sessions = [
        'demo_user'     => 'demo-dashboard',
        'demo_customer' => 'demo-customer-dashboard',
        'demo_agent'    => 'demo-agent-dashboard'
    ];

    foreach ($sessions as $sessionKey => $dashboardPage) {

        if (!isset($_SESSION[$sessionKey])) {
            continue;
        }

        $demoUserId = (int) ($_SESSION[$sessionKey]['id'] ?? 0);

        if ($demoUserId <= 0) {
            unset($_SESSION[$sessionKey]);
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM demo_users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$demoUserId]);
        $demoUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$demoUser || $demoUser['status'] !== 'Active') {
            unset($_SESSION[$sessionKey]);
            $_SESSION['demo_logged_out'] = true;
            header('Location: ?page=demo-login');
            exit;
        }

        if (
            !empty($demoUser['expires_at'])
            && strtotime($demoUser['expires_at']) <= time()
        ) {
            if (!empty($demoUser['workspace_id'])) {
                $stmt = $pdo->prepare("
                    UPDATE demo_users
                    SET status = 'Expired'
                    WHERE workspace_id = ?
                      AND status = 'Active'
                ");
                $stmt->execute([(int) $demoUser['workspace_id']]);

                $stmt = $pdo->prepare("
                    UPDATE demo_workspaces
                    SET
                        status = 'Expired',
                        expired_at = COALESCE(expired_at, NOW())
                    WHERE id = ?
                ");
                $stmt->execute([(int) $demoUser['workspace_id']]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE demo_users
                    SET status = 'Expired'
                    WHERE id = ?
                ");
                $stmt->execute([$demoUserId]);
            }

            unset($_SESSION[$sessionKey]);
            $_SESSION['demo_logged_out'] = true;
            header('Location: ?page=demo-login');
            exit;
        }

        /* Keep the session synchronized with the database. */
        $_SESSION[$sessionKey] = $demoUser;

        if ((int) $demoUser['must_change_password'] === 1) {
            header('Location: ?page=demo-change-password');
            exit;
        }

        header('Location: ?page=' . $dashboardPage);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Normal accounts are not allowed to enter the Demo login page.
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

try {
    redirectExistingDemoSession($pdo);
} catch (PDOException $e) {
    error_log(
        'Demo session check failed: ' . $e->getMessage()
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['user_type'] ?? '';

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
            |------------------------------------------------------------------
            | Find the Demo account
            |------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_users
                WHERE username = ?
                  AND role = ?
                LIMIT 1
            ");

            $stmt->execute([$username, $selectedRole]);
            $demoUser = $stmt->fetch(PDO::FETCH_ASSOC);

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

                if ($demoUser['status'] !== 'Active') {
                    $error = $genericError;
                }

                /*
                |--------------------------------------------------------------
                | Check current expiration.
                |--------------------------------------------------------------
                */

                if (
                    $error === ''
                    && !empty($demoUser['expires_at'])
                    && strtotime($demoUser['expires_at']) <= time()
                ) {
                    if (!empty($demoUser['workspace_id'])) {
                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET status = 'Expired'
                            WHERE workspace_id = ?
                              AND status = 'Active'
                        ");
                        $stmt->execute([
                            (int) $demoUser['workspace_id']
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE demo_workspaces
                            SET
                                status = 'Expired',
                                expired_at = COALESCE(expired_at, NOW())
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            (int) $demoUser['workspace_id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET status = 'Expired'
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            (int) $demoUser['id']
                        ]);
                    }

                    $error = $genericError;
                }

                /*
                |--------------------------------------------------------------
                | Start the shared 5-day period for this workspace.
                |--------------------------------------------------------------
                |
                | Whichever Demo role logs in first starts the workspace timer.
                | The other two roles use exactly the same timer.
                |--------------------------------------------------------------
                */

                if (
                    $error === ''
                    && !empty($demoUser['workspace_id'])
                ) {
                    $workspaceId = (int) $demoUser['workspace_id'];

                    $stmt = $pdo->prepare("
                        SELECT
                            MIN(first_login_at) AS shared_first_login,
                            MIN(expires_at) AS shared_expires_at
                        FROM demo_users
                        WHERE workspace_id = ?
                          AND status = 'Active'
                          AND first_login_at IS NOT NULL
                    ");

                    $stmt->execute([$workspaceId]);
                    $sharedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (empty($sharedPeriod['shared_first_login'])) {
                        $firstLoginAt = date('Y-m-d H:i:s');
                        $expiresAt = date(
                            'Y-m-d H:i:s',
                            strtotime($firstLoginAt . ' +5 days')
                        );

                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET
                                first_login_at = ?,
                                expires_at = ?
                            WHERE workspace_id = ?
                              AND status = 'Active'
                        ");

                        $stmt->execute([
                            $firstLoginAt,
                            $expiresAt,
                            $workspaceId
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE demo_workspaces
                            SET
                                status = 'Active',
                                started_at = ?,
                                expires_at = ?
                            WHERE id = ?
                              AND status IN ('Pending', 'Active')
                        ");

                        $stmt->execute([
                            $firstLoginAt,
                            $expiresAt,
                            $workspaceId
                        ]);
                    } else {
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
                            WHERE workspace_id = ?
                              AND status = 'Active'
                        ");

                        $stmt->execute([
                            $sharedFirstLogin,
                            $sharedExpires,
                            $workspaceId
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE demo_workspaces
                            SET
                                status = 'Active',
                                started_at = COALESCE(started_at, ?),
                                expires_at = ?
                            WHERE id = ?
                              AND status IN ('Pending', 'Active')
                        ");

                        $stmt->execute([
                            $sharedFirstLogin,
                            $sharedExpires,
                            $workspaceId
                        ]);
                    }

                    /* Reload after workspace synchronization. */
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
                |--------------------------------------------------------------
                | Final expiration check after starting/synchronizing timer.
                |--------------------------------------------------------------
                */

                if (
                    $error === ''
                    && !empty($demoUser['expires_at'])
                    && strtotime($demoUser['expires_at']) <= time()
                ) {
                    if (!empty($demoUser['workspace_id'])) {
                        $stmt = $pdo->prepare("
                            UPDATE demo_users
                            SET status = 'Expired'
                            WHERE workspace_id = ?
                              AND status = 'Active'
                        ");
                        $stmt->execute([
                            (int) $demoUser['workspace_id']
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE demo_workspaces
                            SET
                                status = 'Expired',
                                expired_at = COALESCE(expired_at, NOW())
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            (int) $demoUser['workspace_id']
                        ]);
                    }

                    $error = $genericError;
                }

                /*
                |--------------------------------------------------------------
                | Successful Demo login
                |--------------------------------------------------------------
                */

                if ($error === '') {
                    unset($_SESSION['demo_logged_out']);

                    session_regenerate_id(true);

                    unset(
                        $_SESSION['demo_user'],
                        $_SESSION['demo_customer'],
                        $_SESSION['demo_agent']
                    );

                    if ($demoUser['role'] === 'admin') {
                        $_SESSION['demo_user'] = $demoUser;
                    } elseif ($demoUser['role'] === 'customer') {
                        $_SESSION['demo_customer'] = $demoUser;
                    } elseif ($demoUser['role'] === 'agent') {
                        $_SESSION['demo_agent'] = $demoUser;
                    } else {
                        $error = $genericError;
                    }

                    if ($error === '') {
                        if ((int) $demoUser['must_change_password'] === 1) {
                            header('Location: ?page=demo-change-password');
                            exit;
                        }

                        if ($demoUser['role'] === 'admin') {
                            header('Location: ?page=demo-dashboard');
                        } elseif ($demoUser['role'] === 'customer') {
                            header('Location: ?page=demo-customer-dashboard');
                        } else {
                            header('Location: ?page=demo-agent-dashboard');
                        }
                        exit;
                    }
                }
            }

        } catch (PDOException $e) {
            error_log(
                'Demo login failed: ' . $e->getMessage()
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
                    Sign in using the Demo credentials provided to you.
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
                                <?= $userType === '' ? 'selected' : '' ?>>
                                Select User Type
                            </option>

                            <option
                                value="admin"
                                <?= $userType === 'admin' ? 'selected' : '' ?>>
                                Admin
                            </option>

                            <option
                                value="customer"
                                <?= $userType === 'customer' ? 'selected' : '' ?>>
                                Customer
                            </option>

                            <option
                                value="agent"
                                <?= $userType === 'agent' ? 'selected' : '' ?>>
                                Agent
                            </option>

                        </select>
                    </div>

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

                    <div class="text-end mb-3">
                        <a href="?page=demo-recover-credentials">
                            Forgot Demo Password?
                        </a>
                    </div>

                    <div class="alert alert-info">
                        <strong>Demo Account</strong>
                        <br><br>
                        Your Demo access is temporary and shared across the
                        Admin, Customer, and Agent accounts in your workspace.
                        <br><br>
                        On first sign-in, you will be required to replace your
                        temporary password before entering the portal.
                    </div>

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
