<?php

require CONFIG_PATH . '/database.php';
require dirname(__DIR__) . '/layouts/header-public.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    if ($password !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } else {

        $check = $pdo->prepare("
            SELECT id
            FROM customers
            WHERE email = ?
        ");

        $check->execute([$email]);

        if ($check->fetch()) {

            $error = 'Email already registered.';

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                INSERT INTO customers
                (
                    name,
                    email,
                    phone,
                    password
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $hashedPassword
            ]);

            header('Location: ?page=public-login');
            exit;
        }
    }
}

?>

<div class="row justify-content-center">

    <div class="col-md-6">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-4">
                    Customer Registration
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>

                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label>Name</label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>Email</label>

                        <input type="email"
                            name="email"
                            class="form-control"
                            autocomplete="off"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>Phone</label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>Password</label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>Confirm Password</label>

                        <input type="password"
                            name="confirm_password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <button class="btn btn-primary">

                        Register

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>