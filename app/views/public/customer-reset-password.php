<?php

require_once HELPER_PATH . '/email.php';
require dirname(__DIR__) . '/layouts/header-public.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE reset_token = ?
");

$stmt->execute([$token]);

$customer = $stmt->fetch();

if (!$customer) {

    die('This password reset link is invalid.');

}

if (strtotime($customer['reset_token_expires']) < time()) {

    header('Location: ?page=customer-forgot-password&expired=1');
    exit;

}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {

        $message = 'Passwords do not match.';

    } elseif (strlen($password) < 8) {

        $message = 'Password must be at least 8 characters long.';

    } else {

        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            UPDATE customers
            SET
                password = ?,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $hash,
            $customer['id']
        ]);

        header('Location: ?page=customer-login&reset=success');
        exit;
    }
}

require __DIR__ . '/layouts/header.php';
?>

<h2>Reset Password</h2>

<?php if (!empty($message)): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>

<form method="POST">

    <div class="mb-3">

        <label>New Password</label>

        <input
            type="password"
            name="password"
            class="form-control"
            required>

    </div>

    <div class="mb-3">

        <label>Confirm Password</label>

        <input
            type="password"
            name="confirm_password"
            class="form-control"
            required>

    </div>

    <button
        type="submit"
        class="btn btn-success">

        Reset Password

    </button>

    <a
        href="?page=customer-login"
        class="btn btn-secondary">

        Cancel

    </a>

</form>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>