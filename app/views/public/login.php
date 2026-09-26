<?php

require CONFIG_PATH . '/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // ---------------------------
// Check Customer
// ---------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE email = ?
");

$stmt->execute([$email]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    $customer &&
    password_verify($password, $customer['password'])
) {

    $_SESSION['customer'] = $customer;

    header('Location: ?page=customer-dashboard');
    exit;

}

// ---------------------------
// Check Agent
// ---------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM agents
    WHERE email = ?
    AND status = 'Active'
");

$stmt->execute([$email]);

$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    $agent &&
    password_verify($password, $agent['password'])
) {

    $_SESSION['agent'] = $agent;

    header('Location: ?page=agent-dashboard');
    exit;

}

// ---------------------------
// Invalid Login
// ---------------------------

$error = 'Invalid email or password.';
}

$isAgentLogin =
    isset($_SESSION['login_role']) &&
    $_SESSION['login_role'] === 'agent';

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-5 col-md-6">

        <div class="card shadow-sm">

            <div class="card-body">

               <h2 class="mb-2 text-center">
                    Welcome Back
                </h2>

                <p class="text-muted text-center mb-4">
                    Sign in with your email address and password.
                </p>

                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= $error ?>

                    </div>

                <?php endif; ?>

                <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>

                    <div class="alert alert-success">

                        Your password has been reset successfully.
                        You can now log in with your new password.

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

                    <button class="btn btn-primary w-100">

                        Sign In

                    </button>

                    <p class="mt-3 text-center">
                        <a href="?page=customer-forgot-password">
                            Forgot your password?
                        </a>

                    </p>

                    <hr>

                    <p class="text-center mb-0">

                       <a
                            href="?page=login"
                            class="small text-secondary text-decoration-none">

                            Administrator Login

                        </a>

                    </p>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>