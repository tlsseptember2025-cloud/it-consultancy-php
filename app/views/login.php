<?php

require dirname(__DIR__, 2) . '/config/database.php';

$totalCustomers = $pdo->query("
    SELECT COUNT(*)
    FROM customers
")->fetchColumn();

$totalRequests = $pdo->query("
    SELECT COUNT(*)
    FROM requests
")->fetchColumn();

$totalServices = $pdo->query("
    SELECT COUNT(*)
    FROM services
")->fetchColumn();

$unreadMessages = $pdo->query("
    SELECT COUNT(*)
    FROM messages
    WHERE status = 'unread'
")->fetchColumn();

$totalPayments = $pdo->query("
    SELECT COUNT(*)
    FROM payments
")->fetchColumn();

$totalRevenue = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
")->fetchColumn();

$totalQuoted = $pdo->query("
    SELECT COALESCE(SUM(quoted_price),0)
    FROM requests
")->fetchColumn();

$outstandingBalance = $totalQuoted - $totalRevenue;

$latestRequest = $pdo->query("
    SELECT
        customers.name,
        services.title,
        requests.status
    FROM requests
    JOIN customers
        ON customers.id = requests.customer_id
    JOIN services
        ON services.id = requests.service_id
    ORDER BY requests.id DESC
    LIMIT 1
")->fetch();

$latestPayment = $pdo->query("
    SELECT
        customers.name,
        payments.amount,
        payments.status
    FROM payments
    JOIN requests
        ON requests.id = payments.request_id
    JOIN customers
        ON customers.id = requests.customer_id
    ORDER BY payments.id DESC
    LIMIT 1
")->fetch();

$latestMessage = $pdo->query("
    SELECT *
    FROM messages
    ORDER BY id DESC
    LIMIT 1
")->fetch();

if (isset($_SESSION['user'])) {

    header('Location: ?page=dashboard');
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

        header('Location: ?page=dashboard');
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

<?php require __DIR__ . '/layouts/footer.php'; ?>