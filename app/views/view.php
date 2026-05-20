<?php
if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

if (!$message) {
    echo "Message not found";
    exit;
}

// ✅ Mark as read here (BEST place)
$pdo->prepare("UPDATE messages SET status='read' WHERE id=?")->execute([$id]);
?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<h2>View Message</h2>

<div class="card p-3">
    <p><strong>Name:</strong> <?= htmlspecialchars($message['name']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($message['email']) ?></p>
    <p><strong>Service:</strong><?= htmlspecialchars($message['service']) ?></p>
    <p><strong>Message:</strong><br><?= nl2br(htmlspecialchars($message['message'])) ?></p>
    <p><strong>Date:</strong> <?= $message['created_at'] ?></p>
</div>

<br>
<a class="btn btn-secondary" href="?page=messages">Back</a>

<?php require __DIR__ . '/layouts/footer.php'; ?>