<?php

if (!isset($_GET['token']) || trim($_GET['token']) === '') {

    die('Invalid or missing password reset link.');

}

$token = trim($_GET['token']);

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email
    FROM agents
    WHERE password_reset_token = ?
      AND password_reset_expires_at IS NOT NULL
      AND password_reset_expires_at > NOW()
    LIMIT 1
");

$stmt->execute([$tokenHash]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {

    die('This password reset link is invalid or has expired.');

}


$error = null;


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['reset_password'])
) {

    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirmation'] ?? '';

    /*
     * Make sure both passwords match.
     */
    if ($newPassword !== $confirmPassword) {

        $error = 'The passwords do not match.';

    /*
     * Minimum password length.
     */
    } elseif (strlen($newPassword) < 8) {

        $error = 'Password must be at least 8 characters.';

    } else {

        /*
         * Hash the new password securely.
         */
        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        /*
         * Update the password and invalidate
         * the reset token immediately.
         */
        $stmt = $pdo->prepare("
            UPDATE agents
            SET
                password = ?,
                password_reset_token = NULL,
                password_reset_expires_at = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $passwordHash,
            $agent['id']
        ]);

        /*
         * Password successfully changed.
         */
        header('Location: ?page=public-login&password-reset=success');
        exit;
    }
}


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h4 class="mb-0">
                        Set New Password
                    </h4>

                </div>

                <div class="card-body">

                <?php if (!empty($error)): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>

                    <p>
                        Hello
                        <strong>
                            <?= htmlspecialchars($agent['name']) ?>
                        </strong>
                    </p>

                    <p class="text-muted">
                        Enter a new password for your agent account.
                    </p>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="token"
                            value="<?= htmlspecialchars($token) ?>">


                        <div class="mb-3">

                            <label class="form-label fw-bold">
                                New Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required
                                minlength="8">

                            <div class="form-text">
                                Password must be at least 8 characters.
                            </div>

                        </div>


                        <div class="mb-4">

                            <label class="form-label fw-bold">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                required
                                minlength="8">

                        </div>


                        <button
                            type="submit"
                            name="reset_password"
                            class="btn btn-primary">

                            🔐 Set New Password

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>