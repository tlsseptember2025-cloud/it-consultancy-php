<?php

require dirname(__DIR__, 2) . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM customers
        WHERE email = ?
    ");

    $stmt->execute([$email]);

    $customer = $stmt->fetch();

    if (
        $customer &&
        password_verify($password, $customer['password'])
    ) {

        $_SESSION['customer'] = $customer;

        header('Location: ?page=customer-dashboard');
        exit;

    } else {

        $error = 'Invalid email or password.';
    }
}

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-5">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-4">
                    Customer Login
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= $error ?>

                    </div>

                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            autocomplete="new-email"
                            required>

                    </div>

                    <div class="mb-3">

                        <label>Password</label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                            required>

                    </div>

                    <button class="btn btn-primary">

                        Login

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>