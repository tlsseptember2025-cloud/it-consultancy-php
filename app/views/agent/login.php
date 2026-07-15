<?php

if (isset($_SESSION['agent'])) {

    header('Location: ?page=agent-dashboard');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM agents
        WHERE email = ?
        AND status = 'Active'
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent && password_verify($password, $agent['password'])) {

        $_SESSION['agent'] = $agent;

        header('Location: ?page=agent-dashboard');
        exit;

    } else {

        $error = 'Invalid email or password.';
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';
?>

<div class="row justify-content-center mt-5">

    <div class="col-md-5">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4 text-center">
                    Agent Login
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
                            autocomplete="username"
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
                            autocomplete="current-password"
                            required>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-success w-100">

                        Login

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>