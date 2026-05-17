<?php

require dirname(__DIR__, 2) . '/config/database.php';

if (isset($_SESSION['user'])) {

    header('Location: ?page=admin');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT * FROM users WHERE email = ?
    ");

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user'] = $user['email'];

        header('Location: ?page=admin');
        exit;

    } else {

        $error = 'Invalid email or password.';
    }
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center mt-5">

    <div class="col-md-5">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4 text-center">
                    Admin Login
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= $error ?>

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
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

<?php require __DIR__ . '/layouts/footer.php'; ?>