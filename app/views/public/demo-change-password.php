<?php

/*
|--------------------------------------------------------------------------
| DEMO CHANGE PASSWORD
|--------------------------------------------------------------------------
| Mandatory password change for temporary Demo accounts.
|
| The user must complete this page before accessing any Demo dashboard.
| The password is changed only for the currently authenticated Demo role.
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoCustomer = isset($_SESSION['demo_customer']);
$isDemoAgent = isset($_SESSION['demo_agent']);

if (!$isDemoAdmin && !$isDemoCustomer && !$isDemoAgent) {
    if (!empty($_SESSION['demo_logged_out'])) {
        header('Location: ?page=demo-login');
    } else {
        header('Location: ?page=public-login');
    }
    exit;
}

if ($isDemoAdmin) {
    $sessionKey = 'demo_user';
} elseif ($isDemoCustomer) {
    $sessionKey = 'demo_customer';
} else {
    $sessionKey = 'demo_agent';
}

$demoSessionUser = $_SESSION[$sessionKey];
$demoUserId = (int) ($demoSessionUser['id'] ?? 0);

if ($demoUserId <= 0) {
    unset($_SESSION[$sessionKey]);
    $_SESSION['demo_logged_out'] = true;
    header('Location: ?page=demo-login');
    exit;
}

/*
|--------------------------------------------------------------------------
| Reload account from database
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Check Demo expiration
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| If password has already been changed, do not show this page again.
|--------------------------------------------------------------------------
*/

if ((int) $demoUser['must_change_password'] !== 1) {
    if ($demoUser['role'] === 'admin') {
        header('Location: ?page=demo-dashboard');
    } elseif ($demoUser['role'] === 'customer') {
        header('Location: ?page=demo-customer-dashboard');
    } else {
        header('Location: ?page=demo-agent-dashboard');
    }
    exit;
}

$error = '';
$newPassword = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'The new password and confirmation do not match.';
    } elseif (password_verify($newPassword, $demoUser['password_hash'])) {
        $error = 'Your new password must be different from your temporary password.';
    } else {

        try {
            $newPasswordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            if ($newPasswordHash === false) {
                throw new RuntimeException('Password hashing failed.');
            }

            $stmt = $pdo->prepare("
                UPDATE demo_users
                SET
                    password_hash = ?,
                    must_change_password = 0
                WHERE id = ?
                  AND status = 'Active'
                  AND must_change_password = 1
            ");

            $stmt->execute([
                $newPasswordHash,
                $demoUserId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The password could not be changed. Please try again.'
                );
            }

            /*
            |------------------------------------------------------------------
            | Reload the updated Demo account and synchronize the session.
            |------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM demo_users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$demoUserId]);
            $updatedDemoUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedDemoUser) {
                throw new RuntimeException(
                    'The Demo account could not be reloaded.'
                );
            }

            $_SESSION[$sessionKey] = $updatedDemoUser;

            unset($_SESSION['demo_logged_out']);

            if ($updatedDemoUser['role'] === 'admin') {
                header('Location: ?page=demo-dashboard');
            } elseif ($updatedDemoUser['role'] === 'customer') {
                header('Location: ?page=demo-customer-dashboard');
            } else {
                header('Location: ?page=demo-agent-dashboard');
            }
            exit;

        } catch (PDOException $e) {
            error_log(
                'Demo password change failed: ' . $e->getMessage()
            );

            $error =
                'We could not change your password right now. Please try again.';
        } catch (RuntimeException $e) {
            error_log(
                'Demo password change failed: ' . $e->getMessage()
            );

            $error = $e->getMessage();
        }
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-2 text-center">
                    🔐 Change Your Demo Password
                </h2>

                <p class="text-muted text-center mb-4">
                    For security, you must replace the temporary password
                    before entering your Demo portal.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Password requirements</strong>
                    <ul class="mb-0 mt-2">
                        <li>At least 8 characters.</li>
                        <li>Must be different from your temporary password.</li>
                        <li>This change applies only to your current Demo role.</li>
                    </ul>
                </div>

                <form
                    method="POST"
                    action="?page=demo-change-password"
                    autocomplete="off">

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
                            minlength="8"
                            autocomplete="new-password"
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
                            class="form-control"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            required>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-100">
                        Change Password &amp; Continue
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
