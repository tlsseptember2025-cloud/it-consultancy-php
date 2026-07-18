<?php

require_once HELPER_PATH . '/email.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM customers
        WHERE email = ?
    ");

    $stmt->execute([$email]);

    $customer = $stmt->fetch();

    if ($customer) {

        $token = bin2hex(random_bytes(32));

        $expires = date(
            'Y-m-d H:i:s',
            strtotime('+10 minutes')
        );

        $stmt = $pdo->prepare("
            UPDATE customers
            SET
                reset_token = ?,
                reset_token_expires = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $token,
            $expires,
            $customer['id']
        ]);

        sendPasswordResetEmail(
            $email,
            $customer['name'],
            $token
        );
    }

    // Generic message for security
    $message =
        'If an account exists with that email address, a reset link has been sent.';
}

require VIEW_PATH . '/layouts/header-public.php';
?>

<h2>Forgot Password</h2>

<?php if ($message): ?>

    <div class="alert alert-success">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>

<?php if (isset($_GET['expired'])): ?>

    <div class="alert alert-warning">
        Your password reset link has expired.
        Please request a new one.
    </div>

<?php endif; ?>

<form method="POST">

    <div class="mb-3">

        <label>Email Address</label>

        <input
            type="email"
            name="email"
            class="form-control"
            required>

    </div>

    <button
        type="submit"
        class="btn btn-primary">

        Send Reset Link

    </button>

    <a
        href="?page=public-login"
        class="btn btn-secondary">

        Cancel

    </a>

</form>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>