<?php

require_once HELPER_PATH . '/email.php';

if (empty($_SESSION['public_csrf_token']) || !is_string($_SESSION['public_csrf_token'])) {
    $_SESSION['public_csrf_token'] = bin2hex(random_bytes(32));
}

$publicCsrfToken = $_SESSION['public_csrf_token'];


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

    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrf) || !hash_equals($publicCsrfToken, $submittedCsrf)) {
        http_response_code(400);
        exit('Invalid form submission. Please refresh the page and try again.');
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

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
              AND reset_token = ?
              AND reset_token_expires >= NOW()
        ");

        $stmt->execute([
            $hash,
            $customer['id'],
            $token
        ]);

        if ($stmt->rowCount() !== 1) {
            $message = 'This password reset link is no longer valid. Please request a new one.';
        } 
    }
}

require VIEW_PATH . '/layouts/header-public.php';
require dirname(__DIR__) . '/public/demo-banner.php';
?>

<h2>Reset Password</h2>

<?php if (!empty($message)): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>

<form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($publicCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

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
        href="?page=public-login"
        class="btn btn-secondary">

        Cancel

    </a>

</form>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>