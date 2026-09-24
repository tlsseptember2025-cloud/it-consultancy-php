<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/email.php';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Reset incomplete recovery session on a fresh page visit
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && !isset($_GET['otp'])
    && !isset($_GET['reset'])
    && !isset($_GET['complete'])
) {
    unset(
        $_SESSION['admin_recovery_admin_id'],
        $_SESSION['admin_recovery_email'],
        $_SESSION['admin_recovery_verified'],
        $_SESSION['admin_recovery_verified_at'],
        $_SESSION['admin_recovery_otp_pending'],
        $_SESSION['admin_recovery_otp_verified'],
        $_SESSION['admin_recovery_otp_verified_at']
    );
}


/*
|--------------------------------------------------------------------------
| Close Recovery / New Credential Screen
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['close_recovery'])
) {

    unset(
        $_SESSION['admin_new_recovery_credential'],
        $_SESSION['admin_recovery_completed'],
        $_SESSION['admin_recovery_admin_id'],
        $_SESSION['admin_recovery_email'],
        $_SESSION['admin_recovery_verified'],
        $_SESSION['admin_recovery_verified_at'],
        $_SESSION['admin_recovery_otp_pending'],
        $_SESSION['admin_recovery_otp_verified'],
        $_SESSION['admin_recovery_otp_verified_at']
    );

    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Main Admin from submitted / verified email
|--------------------------------------------------------------------------
*/

$adminId = null;
$adminEmail = '';

if (isset($_POST['admin_email'])) {

    $adminEmail = trim($_POST['admin_email']);

} elseif (isset($_SESSION['admin_recovery_email'])) {

    $adminEmail = $_SESSION['admin_recovery_email'];
}


/*
|--------------------------------------------------------------------------
| Verify Emergency Recovery Credential
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['verify_recovery_credential'])
) {

    $adminEmail = trim($_POST['admin_email'] ?? '');
    $recoveryCredential = $_POST['recovery_credential'] ?? '';

    if (
        $adminEmail === ''
        || $recoveryCredential === ''
    ) {

        $error = 'Admin email and recovery credential are required.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.email,
                s.recovery_credential_hash,
                s.is_locked
            FROM users u
            INNER JOIN admin_security s
                ON s.admin_id = u.id
            WHERE u.email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $adminEmail
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {

            $error = 'The recovery information could not be verified.';

        } elseif (
            !password_verify(
                $recoveryCredential,
                $admin['recovery_credential_hash']
            )
        ) {

            $error = 'The recovery credential is incorrect.';

        } else {

            $adminId = (int) $admin['id'];
            $adminEmail = $admin['email'];

            /*
             * Remember the verified recovery session.
             */
            $_SESSION['admin_recovery_admin_id'] = $adminId;
            $_SESSION['admin_recovery_email'] = $adminEmail;
            $_SESSION['admin_recovery_verified'] = true;
            $_SESSION['admin_recovery_verified_at'] = time();

            /*
             * Remove any previous unused recovery OTP.
             */
            $deleteStmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE admin_id = ?
                  AND token_type = 'unlock_recovery'
                  AND used_at IS NULL
            ");

            $deleteStmt->execute([
                $adminId
            ]);

            /*
             * Generate a six-digit OTP.
             */
            $otp = (string) random_int(
                100000,
                999999
            );

            $otpHash = password_hash(
                $otp,
                PASSWORD_DEFAULT
            );

            /*
             * OTP is valid for 2 minutes.
             */
            $insertStmt = $pdo->prepare("
                INSERT INTO admin_security_tokens
                (
                    admin_id,
                    token_type,
                    code_hash,
                    expires_at
                )
                VALUES
                (
                    ?,
                    'unlock_recovery',
                    ?,
                    DATE_ADD(NOW(), INTERVAL 2 MINUTE)
                )
            ");

            $insertStmt->execute([
                $adminId,
                $otpHash
            ]);

            /*
             * Send OTP to the registered Main Admin email.
             */
            $emailSubject =
                'WAHHIB Consultancy - Admin Recovery Verification Code';

            $emailBody = "
                <h2>Admin Account Recovery</h2>

                <p>
                    A recovery attempt has been started for your
                    Main Admin account.
                </p>

                <p>
                    Your recovery verification code is:
                </p>

                <p
                    style=\"
                        font-size: 28px;
                        font-weight: bold;
                        letter-spacing: 6px;
                    \"
                >
                    {$otp}
                </p>

                <p>
                    This code is valid for
                    <strong>2 minutes</strong>.
                </p>

                <p>
                    If you did not initiate this recovery process,
                    you can safely ignore this email.
                </p>

                <p>
                    WAHHIB Consultancy
                </p>
            ";

            if (
                sendEmail(
                    $adminEmail,
                    $emailSubject,
                    $emailBody
                )
            ) {

                $_SESSION['admin_recovery_otp_pending'] = true;

                header(
                    'Location: ?page=admin-account-recovery&otp=1'
                );

                exit;

            } else {

                /*
                 * Email failed, so remove the OTP.
                 */
                $deleteStmt = $pdo->prepare("
                    DELETE FROM admin_security_tokens
                    WHERE admin_id = ?
                      AND token_type = 'unlock_recovery'
                      AND used_at IS NULL
                ");

                $deleteStmt->execute([
                    $adminId
                ]);

                unset(
                    $_SESSION['admin_recovery_otp_pending']
                );

                $error =
                    'The verification email could not be sent. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Verify Recovery Email OTP
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['verify_recovery_otp'])
) {

    $adminId = (int) (
        $_SESSION['admin_recovery_admin_id'] ?? 0
    );

    $otp = trim(
        $_POST['recovery_otp'] ?? ''
    );

    if (
        $adminId <= 0
        || !isset($_SESSION['admin_recovery_verified'])
        || $_SESSION['admin_recovery_verified'] !== true
    ) {

        $error =
            'The recovery session is no longer valid. Please start again.';

        unset(
            $_SESSION['admin_recovery_admin_id'],
            $_SESSION['admin_recovery_email'],
            $_SESSION['admin_recovery_verified'],
            $_SESSION['admin_recovery_verified_at'],
            $_SESSION['admin_recovery_otp_pending']
        );

    } elseif ($otp === '') {

        $error =
            'Please enter the verification code.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                code_hash,
                expires_at,
                attempts
            FROM admin_security_tokens
            WHERE admin_id = ?
              AND token_type = 'unlock_recovery'
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
                'No active recovery verification code was found. Please start again.';

        } elseif (
            strtotime($token['expires_at']) < time()
        ) {

            $deleteStmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE id = ?
            ");

            $deleteStmt->execute([
                (int) $token['id']
            ]);

            unset(
                $_SESSION['admin_recovery_otp_pending']
            );

            $error =
                'The recovery verification code has expired. Please start again.';

        } elseif (
            !password_verify(
                $otp,
                $token['code_hash']
            )
        ) {

            $newAttempts =
                (int) $token['attempts'] + 1;

            if ($newAttempts >= 3) {

                $deleteStmt = $pdo->prepare("
                    DELETE FROM admin_security_tokens
                    WHERE id = ?
                ");

                $deleteStmt->execute([
                    (int) $token['id']
                ]);

                unset(
                    $_SESSION['admin_recovery_otp_pending'],
                    $_SESSION['admin_recovery_verified'],
                    $_SESSION['admin_recovery_verified_at']
                );

                $error =
                    'Too many incorrect verification attempts. Please start the recovery process again.';

            } else {

                $updateStmt = $pdo->prepare("
                    UPDATE admin_security_tokens
                    SET attempts = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $newAttempts,
                    (int) $token['id']
                ]);

                $remaining =
                    3 - $newAttempts;

                $error =
                    'Incorrect verification code. '
                    . $remaining
                    . ' attempt'
                    . ($remaining === 1 ? '' : 's')
                    . ' remaining.';
            }

        } else {

            /*
             * OTP verified successfully.
             */
            $updateStmt = $pdo->prepare("
                UPDATE admin_security_tokens
                SET used_at = NOW()
                WHERE id = ?
            ");

            $updateStmt->execute([
                (int) $token['id']
            ]);

            $_SESSION['admin_recovery_otp_verified'] = true;

            $_SESSION['admin_recovery_otp_verified_at'] =
                time();

            unset(
                $_SESSION['admin_recovery_otp_pending']
            );

            header(
                'Location: ?page=admin-account-recovery&reset=1'
            );

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Complete Recovery
|--------------------------------------------------------------------------
|
| This is only available after:
|
| 1. Recovery credential was verified
| 2. Email OTP was verified
|
| The account is then unlocked and a NEW recovery credential
| is generated.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['complete_recovery'])
) {

    $adminId = (int) (
        $_SESSION['admin_recovery_admin_id'] ?? 0
    );

    $otpVerified =
        isset($_SESSION['admin_recovery_otp_verified'])
        && $_SESSION['admin_recovery_otp_verified'] === true;

    $otpVerifiedAt =
        (int) (
            $_SESSION['admin_recovery_otp_verified_at']
            ?? 0
        );

    /*
     * Recovery verification expires after 10 minutes.
     */
    if (
        $adminId <= 0
        || !$otpVerified
        || $otpVerifiedAt <= 0
        || (time() - $otpVerifiedAt) > 600
    ) {

        unset(
            $_SESSION['admin_recovery_admin_id'],
            $_SESSION['admin_recovery_email'],
            $_SESSION['admin_recovery_verified'],
            $_SESSION['admin_recovery_verified_at'],
            $_SESSION['admin_recovery_otp_pending'],
            $_SESSION['admin_recovery_otp_verified'],
            $_SESSION['admin_recovery_otp_verified_at']
        );

        $error =
            'The recovery session has expired. Please start the recovery process again.';

    } else {

        /*
         * Confirm that the Admin still exists.
         */
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.email,
                s.is_locked
            FROM users u
            INNER JOIN admin_security s
                ON s.admin_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $adminId
        ]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {

            $error =
                'The Admin account could not be found.';

        } else {

            /*
             * Generate a completely new emergency recovery credential.
             *
             * The plaintext credential is never stored in the database.
             */
            $newRecoveryCredential =
                bin2hex(
                    random_bytes(16)
                );

            $newRecoveryCredentialHash =
                password_hash(
                    $newRecoveryCredential,
                    PASSWORD_DEFAULT
                );

            /*
             * Unlock the Main Admin account and rotate
             * the emergency recovery credential.
             */
            $updateStmt = $pdo->prepare("
                UPDATE admin_security
                SET
                    recovery_credential_hash = ?,
                    recovery_credential_rotated_at = NOW(),
                    is_locked = 0,
                    locked_at = NULL,
                    lock_reason = NULL,
                    failed_otp_attempts = 0
                WHERE admin_id = ?
            ");

            $updateStmt->execute([
                $newRecoveryCredentialHash,
                $adminId
            ]);

            /*
             * Remove any remaining recovery tokens.
             */
            $deleteStmt = $pdo->prepare("
                DELETE FROM admin_security_tokens
                WHERE admin_id = ?
                  AND token_type = 'unlock_recovery'
            ");

            $deleteStmt->execute([
                $adminId
            ]);

            /*
             * Keep the NEW credential temporarily in the session.
             *
             * It is displayed once on the recovery screen.
             */
            $_SESSION['admin_new_recovery_credential'] =
                $newRecoveryCredential;

            $_SESSION['admin_recovery_completed'] =
                true;

            /*
             * Clear verification state.
             */
            unset(
                $_SESSION['admin_recovery_verified'],
                $_SESSION['admin_recovery_verified_at'],
                $_SESSION['admin_recovery_otp_pending'],
                $_SESSION['admin_recovery_otp_verified'],
                $_SESSION['admin_recovery_otp_verified_at']
            );

            header(
                'Location: ?page=admin-account-recovery&complete=1'
            );

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Current Recovery State
|--------------------------------------------------------------------------
*/

$recoveryVerified =
    isset($_SESSION['admin_recovery_verified'])
    && $_SESSION['admin_recovery_verified'] === true;

$otpPending =
    isset($_SESSION['admin_recovery_otp_pending'])
    && $_SESSION['admin_recovery_otp_pending'] === true;

$otpVerified =
    isset($_SESSION['admin_recovery_otp_verified'])
    && $_SESSION['admin_recovery_otp_verified'] === true;

$adminEmail =
    $_SESSION['admin_recovery_email']
    ?? $adminEmail;


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-6 col-md-8">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-2 text-center">
                    Admin Account Recovery
                </h2>

                <p class="text-muted text-center mb-4">
                    Recover access to the locked Main Admin account.
                </p>


                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    isset($_GET['complete'])
                    && $_GET['complete'] === '1'
                    && isset($_SESSION['admin_new_recovery_credential'])
                ): ?>

                    <div class="alert alert-success">

                        <strong>
                            Main Admin account recovered successfully.
                        </strong>

                        <p class="mb-0 mt-2">
                            The account has been unlocked and a new
                            emergency recovery credential has been generated.
                        </p>

                    </div>


                    <div class="card border-warning mt-4">

                        <div class="card-header bg-warning text-dark">

                            <strong>
                                ⚠️ New Emergency Recovery Credential
                            </strong>

                        </div>


                        <div class="card-body">

                            <p>
                                This credential is shown
                                <strong>once only</strong>.
                                Save it somewhere secure before closing
                                this page.
                            </p>


                            <div class="mb-3">

                                <label
                                    for="newRecoveryCredential"
                                    class="form-label">

                                    Recovery Credential

                                </label>

                                <input
                                    type="text"
                                    id="newRecoveryCredential"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_SESSION['admin_new_recovery_credential']) ?>"
                                    readonly>

                            </div>


                            <div class="d-flex gap-2 flex-wrap">

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    onclick="saveRecoveryCredential()">

                                    💾 Save Credential

                                </button>


                                <form
                                    method="POST"
                                    action="?page=admin-account-recovery"
                                    class="d-inline">

                                    <input
                                        type="hidden"
                                        name="close_recovery"
                                        value="1">

                                    <button
                                        type="submit"
                                        class="btn btn-secondary">

                                        Close

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>


                    <script>

                    function saveRecoveryCredential() {

                        const credential =
                            document.getElementById(
                                'newRecoveryCredential'
                            ).value;

                        const blob = new Blob(
                            [credential],
                            {
                                type: 'text/plain'
                            }
                        );

                        const url =
                            URL.createObjectURL(blob);

                        const link =
                            document.createElement('a');

                        link.href = url;

                        link.download =
                            'wahhib-admin-recovery-credential.txt';

                        document.body.appendChild(link);

                        link.click();

                        document.body.removeChild(link);

                        URL.revokeObjectURL(url);
                    }

                    </script>


                <?php elseif ($otpVerified): ?>

                    <div class="alert alert-success">

                        <strong>
                            Email verification successful.
                        </strong>

                        <p class="mb-0 mt-2">
                            Your recovery email has been verified.
                        </p>

                    </div>


                    <div class="alert alert-warning mt-3">

                        <strong>
                            Final Recovery Step
                        </strong>

                        <p class="mb-0 mt-2">
                            Continue to unlock the Main Admin account
                            and generate a new emergency recovery credential.
                        </p>

                    </div>


                    <form
                        method="POST"
                        action="?page=admin-account-recovery"
                        class="mt-3">

                        <button
                            type="submit"
                            name="complete_recovery"
                            value="1"
                            class="btn btn-danger w-100">

                            Unlock Account & Generate New Recovery Credential

                        </button>

                    </form>


                <?php elseif ($otpPending): ?>

                    <div class="alert alert-info">

                        <strong>
                            Verification code sent.
                        </strong>

                        <p class="mb-0 mt-2">
                            A six-digit verification code has been sent
                            to your registered Admin email address.
                            The code is valid for 2 minutes.
                        </p>

                    </div>


                    <form
                        method="POST"
                        action="?page=admin-account-recovery"
                        autocomplete="off">

                        <div class="mb-3">

                            <label
                                for="recovery_otp"
                                class="form-label">

                                Verification Code

                            </label>

                            <input
                                type="text"
                                class="form-control text-center"
                                id="recovery_otp"
                                name="recovery_otp"
                                maxlength="6"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required>

                        </div>


                        <button
                            type="submit"
                            name="verify_recovery_otp"
                            value="1"
                            class="btn btn-primary w-100">

                            Verify Email Code

                        </button>

                    </form>


                <?php elseif ($recoveryVerified): ?>

                    <div class="alert alert-success">

                        <strong>
                            Recovery credential verified.
                        </strong>

                        <p class="mb-0 mt-2">
                            A verification code will be sent to your
                            registered Admin email address.
                        </p>

                    </div>


                <?php else: ?>

                    <div class="alert alert-info">

                        Use your emergency recovery credential to begin
                        recovering the locked Main Admin account.

                    </div>


                    <form
                        method="POST"
                        action="?page=admin-account-recovery"
                        autocomplete="off">

                        <div class="mb-3">

                            <label
                                for="admin_email"
                                class="form-label">

                                Admin Email

                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="admin_email"
                                name="admin_email"
                                value="<?= htmlspecialchars($adminEmail) ?>"
                                autocomplete="email"
                                required>

                        </div>


                        <div class="mb-3">

                            <label
                                for="recovery_credential"
                                class="form-label">

                                Recovery Credential

                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="recovery_credential"
                                name="recovery_credential"
                                autocomplete="off"
                                required>

                        </div>


                        <button
                            type="submit"
                            name="verify_recovery_credential"
                            value="1"
                            class="btn btn-danger w-100">

                            Verify Recovery Credential

                        </button>

                    </form>

                <?php endif; ?>


                <div class="text-center mt-3">

                    <a href="?page=login">
                        Back to Admin Login
                    </a>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>