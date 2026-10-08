<?php

/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
|
| Keep each admin role on its own login/dashboard flow.
|
*/
if (isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin');
    exit;
}

if (isset($_SESSION['demo_user'])) {
    header('Location: ?page=dashboard');
    exit;
}

if (isset($_SESSION['user'])) {
    header('Location: ?page=dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/auth.php';

$csrfKey = 'main_admin_login_csrf';
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION[$csrfKey] ?? '');

    if (
        $sessionToken === ''
        || $submittedToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(403);
        die('Invalid security token.');
    }

    $email = trim($_POST['email'] ?? '');

    /*
    | Do not trim passwords. Spaces may legitimately be part of a password.
    */
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Invalid Account
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        $error = 'Invalid email or password.';

    } else {

        $adminId = (int) $user['id'];


        /*
        |--------------------------------------------------------------------------
        | Check Main Admin Security / Lock Status
        |--------------------------------------------------------------------------
        */

        $securityStmt = $pdo->prepare("
            SELECT
                is_locked,
                locked_at,
                lock_reason
            FROM admin_security
            WHERE admin_id = ?
            LIMIT 1
        ");

        $securityStmt->execute([
            $adminId
        ]);

        $security = $securityStmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Locked Account
        |--------------------------------------------------------------------------
        */

        if (
            $security
            && (int) $security['is_locked'] === 1
        ) {

            $error =
                'This Main Admin account is locked. '
                . 'Please use the Admin recovery procedure to regain access.';

        }


        /*
        |--------------------------------------------------------------------------
        | Password Verification
        |--------------------------------------------------------------------------
        */

        elseif (
            password_verify(
                $password,
                $user['password']
            )
        ) {


            /*
            |--------------------------------------------------------------------------
            | Clear Other Role Sessions
            |--------------------------------------------------------------------------
            */

            clearRoleSessions();


            /*
            |--------------------------------------------------------------------------
            | Prevent Session Fixation
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | Admin Security Setup Check
            |--------------------------------------------------------------------------
            |
            | Every Main Admin must have a recovery credential.
            |
            */

            $securityStmt = $pdo->prepare("
                SELECT id
                FROM admin_security
                WHERE admin_id = ?
                LIMIT 1
            ");

            $securityStmt->execute([
                $adminId
            ]);

            $securityRecord = $securityStmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Recovery Credential Not Yet Created
            |--------------------------------------------------------------------------
            |
            | This is the first-time setup.
            |
            | IMPORTANT:
            | Do NOT create $_SESSION['user'] yet.
            |
            */

            if (!$securityRecord) {

                $_SESSION['admin_security_setup_required'] = true;
                $_SESSION['admin_security_admin_id'] = $adminId;

                header(
                    'Location: ?page=admin-recovery-credential'
                );

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | Security Setup Complete
            |--------------------------------------------------------------------------
            |
            | The Admin already has a recovery credential, so this is
            | a normal Admin login.
            |
            */

            $_SESSION['user'] = $user['email'];

            unset(
                $_SESSION['admin_security_setup_required'],
                $_SESSION['admin_security_admin_id']
            );


            /*
            |--------------------------------------------------------------------------
            | Guest Live Chat - Admin Presence
            |--------------------------------------------------------------------------
            */

            $presenceStmt = $pdo->prepare("
                INSERT INTO admin_presence
                    (
                        admin_id,
                        last_seen,
                        is_online
                    )
                VALUES
                    (
                        ?,
                        CURRENT_TIMESTAMP,
                        1
                    )
                ON DUPLICATE KEY UPDATE
                    last_seen = CURRENT_TIMESTAMP,
                    is_online = 1
            ");

            $presenceStmt->execute([
                $adminId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Go To Dashboard
            |--------------------------------------------------------------------------
            */

            header(
                'Location: ?page=dashboard'
            );

            exit;

        } else {

            $error = 'Invalid email or password.';
        }
    }
}

?>

<?php require dirname(__DIR__) . '/layouts/header-public.php'; ?>


<div class="row justify-content-center mt-5">

    <div class="col-md-5">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4 text-center">
                    Admin Login
                </h2>


                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    autocomplete="off"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION[$csrfKey] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="new-email"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Login
                    </button>

                    <p class="mt-3 text-center mb-0">
                        <a href="?page=admin-account-recovery">
                            Account Locked? Recover Admin Access
                        </a>
                    </p>

                </form>

            </div>

        </div>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>