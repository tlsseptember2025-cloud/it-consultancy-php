<?php

/*
|--------------------------------------------------------------------------
| Agent Password Reset
|--------------------------------------------------------------------------
|
| Normal Agent reset token -> Main DB ($pdo)
| Demo Agent reset token   -> Demo DB ($demoPdo)
|
| The token itself determines which database contains the reset request.
| The expiration is enforced by password_reset_expires_at in the database.
|
*/

if (
    !isset($_GET['token'])
    && !isset($_POST['token'])
) {
    die('Invalid or missing password reset link.');
}

$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);

if ($token === '') {
    die('Invalid or missing password reset link.');
}

$tokenHash = hash('sha256', $token);


/*
|--------------------------------------------------------------------------
| Load Main Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';


/*
|--------------------------------------------------------------------------
| Find Reset Token
|--------------------------------------------------------------------------
|
| First check the Normal Agent database.
| If no valid token exists there, check the Demo database.
|
| A Demo token is only accepted from a Demo Agent record.
|
*/

$db = $pdo;
$isDemoReset = false;

$stmt = $db->prepare("
    SELECT
        id,
        name,
        email
    FROM agents
    WHERE password_reset_token = ?
      AND password_reset_expires_at IS NOT NULL
      AND password_reset_expires_at > NOW()
      AND (is_demo_account = 0 OR is_demo_account IS NULL)
    LIMIT 1
");

$stmt->execute([$tokenHash]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Check Demo Database
|--------------------------------------------------------------------------
*/

if (!$agent) {

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $stmt = $db->prepare("
        SELECT
            a.id,
            a.name,
            a.email
        FROM agents a
        INNER JOIN demo_tenants dt
            ON dt.id = a.demo_tenant_id
        WHERE a.password_reset_token = ?
          AND a.password_reset_expires_at IS NOT NULL
          AND a.password_reset_expires_at > NOW()
          AND a.is_demo_account = 1
          AND a.demo_tenant_id IS NOT NULL
          AND dt.status = 'Active'
          AND (dt.expires_at IS NULL OR dt.expires_at > NOW())
        LIMIT 1
    ");

    $stmt->execute([$tokenHash]);

    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent) {
        $isDemoReset = true;
    }
}


/*
|--------------------------------------------------------------------------
| Invalid / Expired Token
|--------------------------------------------------------------------------
*/

if (!$agent) {
    die('This password reset link is invalid or has expired.');
}


$error = null;


/*
|--------------------------------------------------------------------------
| Process Password Reset
|--------------------------------------------------------------------------
*/

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
         * Re-check the token immediately before changing
         * the password. This prevents an expired token from
         * being used after the page was opened.
         */

        if ($isDemoReset) {
            $stmt = $db->prepare("
                SELECT a.id
                FROM agents a
                INNER JOIN demo_tenants dt
                    ON dt.id = a.demo_tenant_id
                WHERE a.id = ?
                  AND a.password_reset_token = ?
                  AND a.password_reset_expires_at IS NOT NULL
                  AND a.password_reset_expires_at > NOW()
                  AND a.is_demo_account = 1
                  AND dt.status = 'Active'
                  AND (dt.expires_at IS NULL OR dt.expires_at > NOW())
                LIMIT 1
            ");
        } else {
            $stmt = $db->prepare("
                SELECT id
                FROM agents
                WHERE id = ?
                  AND password_reset_token = ?
                  AND password_reset_expires_at IS NOT NULL
                  AND password_reset_expires_at > NOW()
                  AND (is_demo_account = 0 OR is_demo_account IS NULL)
                LIMIT 1
            ");
        }

        $stmt->execute([
            $agent['id'],
            $tokenHash
        ]);

        $validReset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$validReset) {

            die('This password reset link is invalid or has expired.');
        }


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

        $stmt = $db->prepare("
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

        if ($isDemoReset) {

            header(
                'Location: ?page=demo-login&password-reset=success'
            );
            exit;
        }

        header(
            'Location: ?page=public-login&password-reset=success'
        );
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