<?php

require_once CONFIG_PATH . '/demo-database.php';

$token = trim($_GET['token'] ?? '');

$error = '';

$user = null;

/*
 * ------------------------------------------------------------
 * Validate Reset Token
 * ------------------------------------------------------------
 */

if ($token === '') {

    $error = 'This password reset link is invalid.';

} else {

    $stmt = $demoPdo->prepare("
        SELECT
            id,
            username,
            email,
            password_reset_expires_at
        FROM users
        WHERE password_reset_token = ?
          AND is_super_admin = 1
          AND is_demo_account = 0
          AND demo_tenant_id IS NULL
        LIMIT 1
    ");

    $stmt->execute([$token]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $error = 'This password reset link is invalid.';

    } elseif (
        empty($user['password_reset_expires_at']) ||
        strtotime($user['password_reset_expires_at']) <= time()
    ) {

        $error = 'This password reset link has expired.';

        /*
         * Clear expired token.
         */

        $clearStmt = $demoPdo->prepare("
            UPDATE users
            SET
                password_reset_token = NULL,
                password_reset_expires_at = NULL
            WHERE id = ?
              AND is_super_admin = 1
              AND is_demo_account = 0
              AND demo_tenant_id IS NULL
        ");

        $clearStmt->execute([
            $user['id']
        ]);

        $user = null;
    }
}


/*
 * ------------------------------------------------------------
 * Process New Password
 * ------------------------------------------------------------
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $user
) {

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

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

        $stmt = $demoPdo->prepare("
            UPDATE users
            SET
                password = ?,
                password_reset_token = NULL,
                password_reset_expires_at = NULL,
                force_password_change = 0
            WHERE id = ?
              AND password_reset_token = ?
              AND is_super_admin = 1
              AND is_demo_account = 0
              AND demo_tenant_id IS NULL
        ");

        $stmt->execute([
            $hash,
            $user['id'],
            $token
        ]);

        if ($stmt->rowCount() === 1) {

            header(
                'Location: ?page=demo-super-admin-login&reset=success'
            );

            exit;

        } else {

            $error =
                'This password reset link is no longer valid. '
                . 'Please request a new one.';
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
                    Reset Password
                </h2>

                <p class="text-muted text-center mb-4">
                    Create a new password for the Demo Super Admin account.
                </p>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <?php if ($user): ?>

                    <form
                        method="POST"
                        action="?page=demo-super-admin-reset-password&token=<?= urlencode($token) ?>"
                        autocomplete="off"
                    >

                        <div class="mb-3">

                            <label
                                for="password"
                                class="form-label"
                            >
                                New Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >

                            <div class="form-text">
                                Password must be at least 8 characters long.
                            </div>

                        </div>

                        <div class="mb-3">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-success w-100"
                        >
                            Reset Password
                        </button>

                    </form>

                <?php endif; ?>

                <hr>

                <p class="text-center mb-0">

                    <a
                        href="?page=demo-super-admin-login"
                        class="small text-secondary text-decoration-none"
                    >
                        Back to Wahbib Admin Login
                    </a>

                </p>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>