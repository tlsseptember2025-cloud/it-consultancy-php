<?php

require dirname(__DIR__, 2) . '/config/database.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("
    SELECT *
    FROM messages
    WHERE reply_token = ?
");

$stmt->execute([$token]);

$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    die('Invalid or expired conversation link.');
}

$replyStmt = $pdo->prepare("
    SELECT *
    FROM message_replies
    WHERE message_id = ?
    ORDER BY created_at ASC
");

$replyStmt->execute([$message['id']]);

$replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reply = trim($_POST['reply']);

    if (!empty($reply)) {

        $stmt = $pdo->prepare("
            INSERT INTO message_replies
            (
                message_id,
                sender,
                reply_text
            )
            VALUES
            (
                ?,
                'visitor',
                ?
            )
        ");

        $stmt->execute([
            $message['id'],
            $reply
        ]);

        require_once __DIR__ . '/../helpers/notifications.php';

createNotification(
    $pdo,
    'admin',
    null,
    'Visitor Replied',
    $message['name'] . ' replied to the conversation.',
    '?page=view&id=' . $message['id']
);

        header("Location: ?page=visitor-message&token=" . urlencode($token));
        exit;
    }
}

require __DIR__ . '/layouts/header.php';
?>

<div class="card shadow-sm">
    <div class="card-body">

        <h2>Conversation</h2>

        <?php foreach ($replies as $reply): ?>

            <div class="border rounded p-3 mb-3">

                <strong>
                    <?php if ($reply['sender'] === 'visitor'): ?>

                        <?= htmlspecialchars($message['name']) ?>

                    <?php else: ?>

                        IT Consultancy

                    <?php endif; ?>
                </strong>

                <small class="text-muted">
                    <?= $reply['created_at'] ?>
                </small>

                <br><br>

                <?= nl2br(htmlspecialchars($reply['reply_text'])) ?>

            </div>

        <?php endforeach; ?>

    </div>
</div>


<?php if (!$message['is_closed']): ?>

<div class="card shadow-sm mt-3">
    <div class="card-body">

        <h4>Reply to IT Consultancy</h4>

        <form method="POST">

            <div class="mb-3">
                <textarea
                    name="reply"
                    class="form-control"
                    rows="5"
                    placeholder="Type your reply here..."
                    required></textarea>
            </div>

            <button
                type="submit"
                class="btn btn-primary">

                Send Reply

            </button>

            <a
                href="?page=close-conversation&token=<?= urlencode($token) ?>"
                class="btn btn-danger ms-2"
                onclick="return confirm('Close this conversation?');">

                Close Conversation

            </a>

        </form>

    </div>
</div>

<?php else: ?>

<div class="alert alert-success mt-3">

    <strong>Conversation Closed</strong><br>

    This conversation has been closed and archived.
    Thank you for contacting IT Consultancy.

</div>

<?php endif; ?>

<?php require __DIR__ . '/layouts/footer.php'; ?>