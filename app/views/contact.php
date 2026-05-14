<?php require __DIR__ . '/layouts/header.php'; ?>

<h1>Contact Us</h1>

<form method="POST">
    <input type="text" name="name" placeholder="Your Name" required><br><br>
    <input type="email" name="email" placeholder="Your Email" required><br><br>
    <textarea name="message" placeholder="Message" required></textarea><br><br>
    <button type="submit">Send</button>
</form>

<?php
require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO messages (name, email, message, status) VALUES (?, ?, ?, 'unread')");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['message']
    ]);

    echo "<p>Message sent successfully!</p>";
}
?>

<?php require __DIR__ . '/layouts/footer.php'; ?>