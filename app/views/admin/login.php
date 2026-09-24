<?php

if (isset($_SESSION['user'])) {
    header('Location: ?page=dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

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
            | Main / Dev Admin Session
            |--------------------------------------------------------------------------
            |
            | The existing system stores the Admin email
            | in $_SESSION['user'], so this remains unchanged.
            |
            */

            $_SESSION['user'] = $user['email'];


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
            | Admin Security Setup
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
            */

            if (!$securityRecord) {

                $_SESSION['admin_security_setup_required'] = true;

                header(
                    'Location: ?page=admin-recovery-credential'
                );

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | Security Setup Complete
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['admin_security_setup_required']
            );


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