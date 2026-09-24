<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/email.php';

requireAdminLogin();

$adminEmail = $_SESSION['user'] ?? '';

if ($adminEmail === '') {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Cancel / Exit Password Change Process
|--------------------------------------------------------------------------
|
| This must happen before anything else so an old OTP verification
| cannot remain active after the Admin leaves the page.
|
*/

if (isset($_GET['cancel']) && $_GET['cancel'] === '1') {

    unset(
        $_SESSION['admin_password_change_verified'],
        $_SESSION['admin_password_change_verified_at'],
        $_SESSION['admin_password_change_verification_pending'],
        $_SESSION['admin_password_change_last_code_sent_at'],
        $_SESSION['admin_password_change_resend_count']
    );

    header('Location: ?page=dashboard');
    exit;
}


/*
|--------------------------------------------------------------------------
| Normal Page Access
|--------------------------------------------------------------------------
|
| If the Admin arrives here normally from the navbar, do not reuse
| an old verification session.
|
| The verified password form is reached only after successful OTP
| verification during this request flow.
|
*/

$isVerificationAction =
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        isset($_POST['send_auth_code'])
        || isset($_POST['verify_auth_code'])
        || isset($_POST['change_password'])
    );

$isVerifiedPage =
    isset($_GET['verified'])
    && $_GET['verified'] === '1';

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && !$isVerifiedPage
) {

    unset(
        $_SESSION['admin_password_change_verified'],
        $_SESSION['admin_password_change_verified_at'],
        $_SESSION['admin_password_change_verification_pending'],
        $_SESSION['admin_password_change_last_code_sent_at'],
        $_SESSION['admin_password_change_resend_count']
    );
}


$error = '';
$success = '';

$showVerification = false;
$showPasswordForm = false;


/*
|--------------------------------------------------------------------------
| Load Main Admin
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        email,
        password
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([
    $adminEmail
]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {

    unset($_SESSION['user']);

    header('Location: ?page=login');
    exit;
}

$adminId = (int) $admin['id'];


/*
|--------------------------------------------------------------------------
| Check Main Admin Security Status
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        is_locked
    FROM admin_security
    WHERE admin_id = ?
    LIMIT 1
");

$stmt->execute([
    $adminId
]);

$security = $stmt->fetch(PDO::FETCH_ASSOC);

if ($security && (int) $security['is_locked'] === 1) {

    $error =
        'The Main Admin account is currently locked. '
        . 'Password changes are unavailable until the account is recovered.';
}


/*
|--------------------------------------------------------------------------
| Existing Verified State
|--------------------------------------------------------------------------
*/

if (
    $isVerifiedPage
    && isset($_SESSION['admin_password_change_verified'])
    && $_SESSION['admin_password_change_verified'] === true
    && $error === ''
) {

    $verifiedAt =
        (int) ($_SESSION['admin_password_change_verified_at'] ?? 0);

    if (
        $verifiedAt === 0
        || (time() - $verifiedAt) > 300
    ) {

        unset(
            $_SESSION['admin_password_change_verified'],
            $_SESSION['admin_password_change_verified_at']
        );

        $error =
            'Your password-change verification has expired. '
            . 'Please request a new authentication code.';

    } else {

        $showPasswordForm = true;
    }
}


/*
|--------------------------------------------------------------------------
| Send Authentication Code / Resend Authentication Code
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['send_auth_code'])
    && $error === ''
) {

    $isResend = (
        isset($_SESSION['admin_password_change_verification_pending'])
        && $_SESSION['admin_password_change_verification_pending'] === true
    );

    $resendCount = (int) (
        $_SESSION['admin_password_change_resend_count'] ?? 0
    );

    $lastCodeSentAt = (int) (
        $_SESSION['admin_password_change_last_code_sent_at'] ?? 0
    );

    /*
     * Resend protection:
     * - 2-minute cooldown
     * - maximum 3 resends
     *
     * Reaching the resend limit does NOT lock the Admin account.
     */
    if ($isResend) {

        if ($resendCount >= 3) {

            $showVerification = true;

            $error =
                'You have reached the maximum number of authentication-code '
                . 'resend requests. Please cancel and start the password-change '
                . 'process again.';

        } else {

            $cooldownRemaining = 120 - (time() - $lastCodeSentAt);

            if ($lastCodeSentAt > 0 && $cooldownRemaining > 0) {

                $showVerification = true;

                $error =
                    'Please wait '
                    . $cooldownRemaining
                    . ' seconds before requesting another authentication code.';
            }
        }
    }

    if ($error === '') {

        $stmt = $pdo->prepare("
            DELETE FROM admin_security_tokens
            WHERE admin_id = ?
              AND token_type = 'password_change'
              AND used_at IS NULL
        ");

        $stmt->execute([
            $adminId
        ]);

        $authCode = (string) random_int(100000, 999999);

        $codeHash = password_hash(
            $authCode,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO admin_security_tokens (
                admin_id,
                token_type,
                code_hash,
                expires_at
            )
            VALUES (
                ?,
                'password_change',
                ?,
                DATE_ADD(NOW(), INTERVAL 2 MINUTE)
            )
        ");

        $stmt->execute([
            $adminId,
            $codeHash
        ]);

        $subject = 'Main Admin Password Change Authentication Code';

        $body = "
            <h2>Main Admin Password Change</h2>

            <p>
                A request was made to change the password
                for your Main Admin account.
            </p>

            <p>
                Your authentication code is:
            </p>

            <p
                style='
                    font-size:28px;
                    font-weight:bold;
                    letter-spacing:6px;
                    padding:15px;
                    background:#f1f3f5;
                    border-radius:6px;
                    text-align:center;
                '
            >
                {$authCode}
            </p>

            <p>
                This code is valid for
                <strong>2 minutes</strong>.
            </p>

            <p>
                You have a maximum of
                <strong>3 attempts</strong>.
            </p>

            <p>
                If you did not request a password change,
                you can safely ignore this email.
            </p>

            <hr>

            <p>
                Regards,<br>
                <strong>IT Consultancy System</strong>
            </p>
        ";

        if (sendEmail(
            $admin['email'],
            $subject,
            $body
        )) {

            $_SESSION['admin_password_change_verification_pending'] = true;
            $_SESSION['admin_password_change_last_code_sent_at'] = time();

            if ($isResend) {
                $_SESSION['admin_password_change_resend_count'] =
                    $resendCount + 1;
            } else {
                $_SESSION['admin_password_change_resend_count'] = 0;
            }

            $showVerification = true;

            if ($isResend) {
                $success =
                    'A new authentication code has been sent. '
                    . 'You must wait 2 minutes before requesting another code.';
            } else {
                $success =
                    'An authentication code has been sent to your '
                    . 'registered Admin email address.';
            }

        } else {

            $stmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE admin_id = ?
                  AND token_type = 'password_change'
                  AND used_at IS NULL
            ");

            $stmt->execute([
                $adminId
            ]);

            $error =
                'The authentication code could not be sent. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Keep Verification Screen Active
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['admin_password_change_verification_pending'])
    && $_SESSION['admin_password_change_verification_pending'] === true
    && $error === ''
    && !isset($_SESSION['admin_password_change_verified'])
) {

    $showVerification = true;
}


/*
|--------------------------------------------------------------------------
| Verify Authentication Code
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['verify_auth_code'])
    && $error === ''
) {

    $enteredCode = trim($_POST['auth_code'] ?? '');

    if (!preg_match('/^\d{6}$/', $enteredCode)) {

        $error =
            'Please enter the 6-digit authentication code.';

        $showVerification = true;

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                code_hash,
                expires_at,
                attempts
            FROM admin_security_tokens
            WHERE admin_id = ?
              AND token_type = 'password_change'
              AND used_at IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([
            $adminId
        ]);

        $token = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$token) {

            $error =
                'No active authentication code was found. '
                . 'Please request a new code.';

            $showVerification = true;

        } elseif (
            strtotime($token['expires_at']) < time()
        ) {

            $stmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE id = ?
            ");

            $stmt->execute([
                (int) $token['id']
            ]);

            $showVerification = true;

            $error =
                'The authentication code has expired. '
                . 'Please request a new code.';

        } elseif ((int) $token['attempts'] >= 3) {

            $stmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE id = ?
            ");

            $stmt->execute([
                (int) $token['id']
            ]);

            $showVerification = true;

            $error =
                'This authentication code is no longer valid. '
                . 'Please request a new code.';

        } elseif (
            password_verify(
                $enteredCode,
                $token['code_hash']
            )
        ) {

            /*
             * Mark code as used.
             */
            $stmt = $pdo->prepare("
                UPDATE admin_security_tokens
                SET used_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                (int) $token['id']
            ]);


            /*
             * Reset failed OTP counter.
             */
            $stmt = $pdo->prepare("
                UPDATE admin_security
                SET failed_otp_attempts = 0
                WHERE admin_id = ?
            ");

            $stmt->execute([
                $adminId
            ]);


            /*
             * Authentication succeeded.
             */
            $_SESSION['admin_password_change_verified'] = true;

            $_SESSION['admin_password_change_verified_at'] = time();

            unset(
                $_SESSION['admin_password_change_verification_pending'],
                $_SESSION['admin_password_change_last_code_sent_at'],
                $_SESSION['admin_password_change_resend_count']
            );


            /*
             * Redirect to a verified URL.
             *
             * This is important because a normal click on
             * Account > Change Password will not reuse this state.
             */
            header('Location: ?page=admin-change-password&verified=1');
            exit;

        } else {

            /*
             * Incorrect code.
             */
            $newAttempts = (int) $token['attempts'] + 1;

            $stmt = $pdo->prepare("
                UPDATE admin_security_tokens
                SET attempts = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $newAttempts,
                (int) $token['id']
            ]);

            if ($newAttempts >= 3) {

    /*
     * Third incorrect attempt:
     * lock the Main Admin account.
     */
    $stmt = $pdo->prepare("
        UPDATE admin_security
        SET
            is_locked = 1,
            locked_at = NOW(),
            lock_reason = 'Three failed password-change authentication attempts',
            failed_otp_attempts = 3
        WHERE admin_id = ?
    ");

    $stmt->execute([
        $adminId
    ]);


    /*
     * Invalidate all password-change authentication codes.
     */
    $stmt = $pdo->prepare("
        DELETE FROM admin_security_tokens
        WHERE admin_id = ?
          AND token_type = 'password_change'
    ");

    $stmt->execute([
        $adminId
    ]);


    /*
     * Clear password-change verification state.
     */
    unset(
        $_SESSION['admin_password_change_verification_pending'],
        $_SESSION['admin_password_change_verified'],
        $_SESSION['admin_password_change_verified_at']
    );


    /*
     * Log the Main Admin out immediately.
     */
    unset($_SESSION['user']);

    /*
     * Clear other role/session information as well.
     */
    clearRoleSessions();


    /*
     * Regenerate the session ID after logout.
     */
    session_regenerate_id(true);


    /*
     * Send the Admin to the login page with a
     * lock notification.
     */
    header(
        'Location: ?page=login&locked=1'
    );

    exit;
}


             else {

                $remainingAttempts = 3 - $newAttempts;

                $showVerification = true;

                $error =
                    'Incorrect authentication code. '
                    . $remainingAttempts
                    . ' attempt'
                    . ($remainingAttempts === 1 ? '' : 's')
                    . ' remaining.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['change_password'])
    && $error === ''
) {

    if (
        !isset($_SESSION['admin_password_change_verified'])
        || $_SESSION['admin_password_change_verified'] !== true
    ) {

        $error =
            'Authentication verification is required before changing '
            . 'the Admin password.';

    } else {

        $verifiedAt =
            (int) ($_SESSION['admin_password_change_verified_at'] ?? 0);

        if (
            $verifiedAt === 0
            || (time() - $verifiedAt) > 300
        ) {

            unset(
                $_SESSION['admin_password_change_verified'],
                $_SESSION['admin_password_change_verified_at']
            );

            $error =
                'Your password-change verification has expired. '
                . 'Please request a new authentication code.';

        } else {

            $currentPassword =
                $_POST['current_password'] ?? '';

            $newPassword =
                $_POST['new_password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';


            if ($currentPassword === '') {

                $error =
                    'Please enter your current password.';

            } elseif ($newPassword === '') {

                $error =
                    'Please enter a new password.';

            } elseif ($confirmPassword === '') {

                $error =
                    'Please confirm your new password.';

            } elseif (strlen($newPassword) < 8) {

                $error =
                    'The new password must contain at least 8 characters.';

            } elseif (
                password_verify(
                    $newPassword,
                    $admin['password']
                )
            ) {

                $error =
                    'The new password must be different from your current password.';

            } elseif ($newPassword !== $confirmPassword) {

                $error =
                    'The new password and confirmation do not match.';

            } elseif (
                !password_verify(
                    $currentPassword,
                    $admin['password']
                )
            ) {

                $error =
                    'The current password is incorrect.';

            } else {

                $newPasswordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                      AND email = ?
                ");

                $stmt->execute([
    $newPasswordHash,
    $adminId,
    $admin['email']
]);


/*
 * Invalidate all password-change tokens.
 */
$stmt = $pdo->prepare("
    DELETE FROM admin_security_tokens
    WHERE admin_id = ?
      AND token_type = 'password_change'
");

$stmt->execute([
    $adminId
]);


/*
 * Clear the entire password-change verification state.
 */
unset(
    $_SESSION['admin_password_change_verified'],
    $_SESSION['admin_password_change_verified_at'],
    $_SESSION['admin_password_change_verification_pending'],
    $_SESSION['admin_password_change_last_code_sent_at'],
    $_SESSION['admin_password_change_resend_count']
);


/*
 * Store success message for the Dashboard.
 */
$_SESSION['admin_password_change_success'] =
    'Your Main Admin password has been changed successfully.';


/*
 * Return to Dashboard.
 */
header('Location: ?page=dashboard');
exit;
                
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-admin.php';

?>

<div class="row justify-content-center">

    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">

            <div class="card-header bg-dark text-white">

                <h4 class="mb-0">
                    Change Password
                </h4>

            </div>

            <div class="card-body">

                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <?php if ($success !== ''): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                <?php endif; ?>


                <?php if ($showPasswordForm): ?>

                    <p class="text-muted">

                        Authentication has been verified.
                        Enter your current password and choose
                        a new password.

                    </p>


                    <form method="POST">

                        <div class="mb-3">

                            <label
                                for="current_password"
                                class="form-label"
                            >
                                Current Password
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-control"
                                autocomplete="current-password"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                for="new_password"
                                class="form-label"
                            >
                                New Password
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                class="form-control"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >

                            <div class="form-text">
                                Minimum 8 characters.
                            </div>

                        </div>


                        <div class="mb-4">

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


                        <div class="d-flex justify-content-between">

                            <a
                                href="?page=admin-change-password&cancel=1"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                name="change_password"
                                value="1"
                                class="btn btn-primary"
                            >
                                Change Password
                            </button>

                        </div>

                    </form>



                <?php elseif ($showVerification): ?>

                    <p class="text-muted">

                        Enter the 6-digit authentication code
                        sent to your registered Admin email address.

                    </p>


                    <div class="alert alert-warning">

                        The code expires after
                        <strong>2 minutes</strong>.

                        <br>

                        You have a maximum of
                        <strong>3 attempts</strong>.

                        <br><br>

                        A new code can be requested only once every
                        <strong>2 minutes</strong>, with a maximum of
                        <strong>3 resend requests</strong>.

                    </div>


                    <form method="POST">

                        <div class="mb-4">

                            <label
                                for="auth_code"
                                class="form-label"
                            >
                                Authentication Code
                            </label>

                            <input
                                type="text"
                                id="auth_code"
                                name="auth_code"
                                class="form-control text-center"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required
                            >

                        </div>


                        <div class="d-flex justify-content-between">

                            <a
                                href="?page=admin-change-password&cancel=1"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                name="verify_auth_code"
                                value="1"
                                class="btn btn-primary"
                            >
                                Verify Code
                            </button>

                        </div>

                    </form>


                    <?php
                    $resendCount = (int) (
                        $_SESSION['admin_password_change_resend_count'] ?? 0
                    );

                    $lastCodeSentAt = (int) (
                        $_SESSION['admin_password_change_last_code_sent_at'] ?? 0
                    );

                    $resendCooldownRemaining = 0;

                    if ($lastCodeSentAt > 0) {
                        $resendCooldownRemaining = max(
                            0,
                            120 - (time() - $lastCodeSentAt)
                        );
                    }

                    $resendLimitReached = $resendCount >= 3;
                    ?>

                    <form method="POST" class="mt-3">

                        <input
                            type="hidden"
                            name="send_auth_code"
                            value="1"
                        >

                        <div class="text-center">

                            <?php if ($resendLimitReached): ?>

                                <div class="alert alert-secondary py-2 mb-2">
                                    You have reached the maximum of
                                    <strong>3 resend requests</strong>.
                                    Please cancel and start the password-change
                                    process again.
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary btn-sm"
                                    disabled
                                >
                                    Resend Limit Reached
                                </button>

                            <?php else: ?>

                                <button
                                    type="submit"
                                    id="resendAuthCodeButton"
                                    class="btn btn-outline-primary btn-sm"
                                    <?= $resendCooldownRemaining > 0 ? 'disabled' : '' ?>
                                >
                                    Resend Authentication Code
                                </button>

                                <div
                                    id="resendCooldownMessage"
                                    class="small text-muted mt-2"
                                    <?= $resendCooldownRemaining > 0 ? '' : 'style="display:none;"' ?>
                                >
                                    You can request another code in
                                    <strong>
                                        <span id="resendCountdown">
                                            <?= $resendCooldownRemaining ?>
                                        </span>
                                    </strong>
                                    seconds.
                                </div>

                                <div class="small text-muted mt-2">
                                    Resends used:
                                    <strong><?= $resendCount ?> of 3</strong>
                                </div>

                            <?php endif; ?>

                        </div>

                    </form>

                    <?php if (!$resendLimitReached): ?>
                        <script>
                        (function () {
                            let remaining =
                                <?= (int) $resendCooldownRemaining ?>;

                            const button =
                                document.getElementById(
                                    'resendAuthCodeButton'
                                );

                            const message =
                                document.getElementById(
                                    'resendCooldownMessage'
                                );

                            const countdown =
                                document.getElementById(
                                    'resendCountdown'
                                );

                            if (!button || !message || !countdown) {
                                return;
                            }

                            if (remaining <= 0) {
                                button.disabled = false;
                                message.style.display = 'none';
                                return;
                            }

                            message.style.display = 'block';

                            const timer = setInterval(function () {

                                remaining--;

                                if (remaining <= 0) {
                                    clearInterval(timer);
                                    button.disabled = false;
                                    message.style.display = 'none';
                                    return;
                                }

                                countdown.textContent = remaining;

                            }, 1000);
                        })();
                        </script>
                    <?php endif; ?>


                <?php else: ?>

                    <p class="text-muted">

                        Changing the Main Admin password requires
                        an authentication code sent to the registered
                        Admin email address.

                    </p>


                    <div class="alert alert-warning">

                        <strong>Security verification required.</strong>

                        <br><br>

                        Click the button below to receive a
                        6-digit authentication code by email.

                        <br><br>

                        The code will expire after
                        <strong>2 minutes</strong>.

                    </div>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="send_auth_code"
                            value="1"
                        >

                        <div class="d-flex justify-content-between">

                            <a
                                href="?page=dashboard"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Send Authentication Code
                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
