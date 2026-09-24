<?php

require_once CONFIG_PATH . '/demo-database.php';
require_once HELPER_PATH . '/email.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {

        $error = 'Please enter your email address.';

    } else {

        /*
         * ------------------------------------------------------------
         * Find the Permanent Demo Super Admin
         * ------------------------------------------------------------
         *
         * This account:
         * - is_super_admin = 1
         * - is_demo_account = 0
         * - demo_tenant_id IS NULL
         */

        $stmt = $demoPdo->prepare("
            SELECT
                id,
                email,
                username
            FROM users
            WHERE email = ?
              AND is_super_admin = 1
              AND is_demo_account = 0
              AND demo_tenant_id IS NULL
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * ------------------------------------------------------------
         * Do not reveal whether the email exists
         * ------------------------------------------------------------
         */

        if (!$user) {

            $message =
                'If this email belongs to the Demo Super Admin account, '
                . 'a password reset link has been sent.';

        } else {

            /*
             * --------------------------------------------------------
             * Generate Secure Reset Token
             * --------------------------------------------------------
             */

            $token = bin2hex(random_bytes(32));

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + 3600
            );

            /*
             * --------------------------------------------------------
             * Store Token
             * --------------------------------------------------------
             */

            $stmt = $demoPdo->prepare("
                UPDATE users
                SET
                    password_reset_token = ?,
                    password_reset_expires_at = ?
                WHERE id = ?
                  AND is_super_admin = 1
                  AND is_demo_account = 0
                  AND demo_tenant_id IS NULL
            ");

            $stmt->execute([
                $token,
                $expiresAt,
                $user['id']
            ]);

            /*
             * --------------------------------------------------------
             * Build Reset Link
             * --------------------------------------------------------
             */

            $resetLink =
                APP_URL
                . '/?page=demo-super-admin-reset-password&token='
                . urlencode($token);

            /*
             * --------------------------------------------------------
             * Send Email
             * --------------------------------------------------------
             */

            $displayName = !empty($user['username'])
                ? $user['username']
                : 'Wahbib Admin';

            $subject = 'Demo Super Admin Password Reset';

            $body = "
                <h2>Demo Super Admin Password Reset</h2>

                <p>
                    Hello {$displayName},
                </p>

                <p>
                    We received a request to reset the password
                    for the Demo Super Admin account.
                </p>

                <p>
                    Click the button below to create a new password:
                </p>

                <p>
                    <a
                        href='{$resetLink}'
                        style='
                            display:inline-block;
                            background:#0d6efd;
                            color:#ffffff;
                            padding:12px 20px;
                            text-decoration:none;
                            border-radius:6px;
                        '
                    >
                        Reset Password
                    </a>
                </p>

                <p>
                    This password reset link will expire in
                    <strong>1 hour</strong>.
                </p>

                <p>
                    If you did not request this password reset,
                    you can safely ignore this email.
                </p>

                <br>

                <p>
                    Regards,<br>
                    <strong>WAHHIB Consultancy</strong>
                </p>
            ";

            if (sendEmail(
                $user['email'],
                $subject,
                $body
            )) {

                $message =
                    'If this email belongs to the Demo Super Admin account, '
                    . 'a password reset link has been sent.';

            } else {

                /*
                 * Email failed.
                 * Remove the token so it cannot be used.
                 */

                $stmt = $demoPdo->prepare("
                    UPDATE users
                    SET
                        password_reset_token = NULL,
                        password_reset_expires_at = NULL
                    WHERE id = ?
                      AND is_super_admin = 1
                      AND is_demo_account = 0
                      AND demo_tenant_id IS NULL
                ");

                $stmt->execute([
                    $user['id']
                ]);

                $error =
                    'Unable to send the password reset email. '
                    . 'Please try again later.';
            }
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
                    Forgot Password
                </h2>

                <p class="text-muted text-center mb-4">
                    Reset the Demo Super Admin password.
                </p>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <?php if ($message): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($message) ?>

                    </div>

                <?php endif; ?>

                <?php if (!$message): ?>

                    <form
                        method="POST"
                        autocomplete="off"
                    >

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                autocomplete="email"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Send Reset Link
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