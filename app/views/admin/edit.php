<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require CONFIG_PATH . '/database.php';

$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    echo "Message not found";
    exit;
}
?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<h2>Edit Message</h2>

<form method="POST">
    <input type="text" name="name" value="<?= htmlspecialchars($message['name']) ?>" required><br><br>
    <input type="email" name="email" value="<?= htmlspecialchars($message['email']) ?>" required><br><br>
    <textarea name="message" required><?= htmlspecialchars($message['message']) ?></textarea><br><br>
    <button type="submit">Update</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE messages SET name=?, email=?, message=? WHERE id=?");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['message'],
        $id
    ]);

    header("Location: ?page=messages");
    exit;
}
?>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>