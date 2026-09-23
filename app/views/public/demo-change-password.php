<?php

require_once CONFIG_PATH . '/demo-database.php';

$isDemoAdmin    = isset($_SESSION['demo_user']);
$isDemoCustomer = isset($_SESSION['demo_customer']);
$isDemoAgent    = isset($_SESSION['demo_agent']);

if (!$isDemoAdmin && !$isDemoCustomer && !$isDemoAgent) {
    header('Location: ?page=demo-login');
    exit;
}

if ($isDemoAdmin) {
    $sessionKey  = 'demo_user';
    $accountType = 'admin';
} elseif ($isDemoCustomer) {
    $sessionKey  = 'demo_customer';
    $accountType = 'customer';
} else {
    $sessionKey  = 'demo_agent';
    $accountType = 'agent';
}

$demoSessionUser = $_SESSION[$sessionKey];
$demoUserId      = (int) ($demoSessionUser['id'] ?? 0);
$demoTenantId    = (int) ($demoSessionUser['demo_tenant_id'] ?? 0);

if ($demoUserId <= 0 || $demoTenantId <= 0) {
    unset($_SESSION[$sessionKey]);
    header('Location: ?page=demo-login');
    exit;
}

if ($accountType === 'admin') {
    $stmt = $demoPdo->prepare("SELECT * FROM users WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 AND is_super_admin = 0 LIMIT 1");
} elseif ($accountType === 'customer') {
    $stmt = $demoPdo->prepare("SELECT * FROM customers WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 LIMIT 1");
} else {
    $stmt = $demoPdo->prepare("SELECT * FROM agents WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 AND status = 'Active' LIMIT 1");
}

$stmt->execute([$demoUserId, $demoTenantId]);
$demoUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demoUser) {
    unset($_SESSION[$sessionKey]);
    header('Location: ?page=demo-login');
    exit;
}

$tenantStmt = $demoPdo->prepare("SELECT status, expires_at FROM demo_tenants WHERE id = ? LIMIT 1");
$tenantStmt->execute([$demoTenantId]);
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant || $tenant['status'] !== 'Active' || ($tenant['expires_at'] !== null && strtotime($tenant['expires_at']) <= time())) {
    unset($_SESSION[$sessionKey]);
    header('Location: ?page=demo-login');
    exit;
}

if ((int) $demoUser['force_password_change'] !== 1) {
    if ($accountType === 'admin') {
        header('Location: ?page=dashboard');
    } elseif ($accountType === 'customer') {
        header('Location: ?page=customer-dashboard');
    } else {
        header('Location: ?page=agent-dashboard');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'The new password and confirmation do not match.';
    } elseif (!empty($demoUser['password']) && password_verify($newPassword, $demoUser['password'])) {
        $error = 'Your new password must be different from your temporary password.';
    } else {
        try {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($newPasswordHash === false) {
                throw new RuntimeException('Password hashing failed.');
            }

            if ($accountType === 'admin') {
                $updateStmt = $demoPdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 AND is_super_admin = 0 AND force_password_change = 1");
            } elseif ($accountType === 'customer') {
                $updateStmt = $demoPdo->prepare("UPDATE customers SET password = ?, force_password_change = 0 WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 AND force_password_change = 1");
            } else {
                $updateStmt = $demoPdo->prepare("UPDATE agents SET password = ?, force_password_change = 0 WHERE id = ? AND demo_tenant_id = ? AND is_demo_account = 1 AND status = 'Active' AND force_password_change = 1");
            }

            $updateStmt->execute([$newPasswordHash, $demoUserId, $demoTenantId]);

            if ($updateStmt->rowCount() !== 1) {
                throw new RuntimeException('The password could not be changed. Please try again.');
            }

            if ($accountType === 'admin') {
                $reloadStmt = $demoPdo->prepare("SELECT * FROM users WHERE id = ? AND demo_tenant_id = ? LIMIT 1");
            } elseif ($accountType === 'customer') {
                $reloadStmt = $demoPdo->prepare("SELECT * FROM customers WHERE id = ? AND demo_tenant_id = ? LIMIT 1");
            } else {
                $reloadStmt = $demoPdo->prepare("SELECT * FROM agents WHERE id = ? AND demo_tenant_id = ? LIMIT 1");
            }

            $reloadStmt->execute([$demoUserId, $demoTenantId]);
            $updatedDemoUser = $reloadStmt->fetch(PDO::FETCH_ASSOC);

            if (!$updatedDemoUser) {
                throw new RuntimeException('The Demo account could not be reloaded.');
            }

            $_SESSION[$sessionKey] = $updatedDemoUser;

            if ($accountType === 'admin') {
                header('Location: ?page=dashboard');
            } elseif ($accountType === 'customer') {
                header('Location: ?page=customer-dashboard');
            } else {
                header('Location: ?page=agent-dashboard');
            }
            exit;
        } catch (PDOException $e) {
            error_log('Demo password change failed: ' . $e->getMessage());
            $error = 'We could not change your password right now. Please try again.';
        } catch (RuntimeException $e) {
            error_log('Demo password change failed: ' . $e->getMessage());
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
                <h2 class="mb-2 text-center">🔐 Change Your Demo Password</h2>
                <p class="text-muted text-center mb-4">
                    For security, you must replace your temporary password before entering your Demo portal.
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

                <form method="POST" action="?page=demo-change-password" autocomplete="off">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Change Password &amp; Continue
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="?page=demo-login">Return to Demo Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
