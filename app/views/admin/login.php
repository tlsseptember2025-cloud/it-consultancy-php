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

    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        /*
         * Clear any other role sessions before creating
         * the Main/Dev Admin session.
         */
        clearRoleSessions();

        /*
         * Main/Dev Admin session.
         *
         * The existing system stores the Admin email
         * in $_SESSION['user'], so this remains unchanged.
         */
        $_SESSION['user'] = $user['email'];

        /*
         * -------------------------------------------------
         * Guest Live Chat - Admin Presence
         * -------------------------------------------------
         *
         * Record this Admin as currently online.
         *
         * admin_presence has one row per Admin because
         * admin_id is UNIQUE.
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
            (int) $user['id']
        ]);

        header('Location: ?page=dashboard');
        exit;

    } else {

        $error = 'Invalid email or password.';
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

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="new-email"
                            required>

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
                            required>

                    </div>

                    <button class="btn btn-primary w-100">
                        Login
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>