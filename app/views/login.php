<?php require __DIR__ . '/layouts/header.php'; ?>

<h2>Login</h2>

<form method="POST">
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Login</button>
</form>

<?php
require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user'] = $user['email'];
        header("Location: ?page=admin");
        exit;
    } else {
        echo "<p>Invalid login</p>";
    }
}
?>

<?php require __DIR__ . '/layouts/footer.php'; ?>