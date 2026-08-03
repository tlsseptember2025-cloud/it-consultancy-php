<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/email.php';
require CONFIG_PATH . '/database.php';

$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();

$replyStmt = $pdo->prepare("
    SELECT *
    FROM message_replies
    WHERE message_id = ?
    ORDER BY created_at ASC
");

$replyStmt->execute([$id]);

$replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$message) {
    echo "Message not found";
    exit;
}

// ✅ Mark as read here (BEST place)
$pdo->prepare("UPDATE messages SET status='read' WHERE id=?")->execute([$id]);


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$message['is_closed']
) {

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
                'admin',
                ?
            )
        ");

        $stmt->execute([
            $id,
            $reply
        ]);

        // Reload message information
        $stmt = $pdo->prepare("
            SELECT *
            FROM messages
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
    $message['preferred_contact'] === 'Email'
    && !empty($message['email'])
) {

    $conversationLink =
        'http://localhost/it-consultancy-php/public/?page=visitor-message&token=' .
        $message['reply_token'];

    sendEmail(
        $message['email'],
        'New Reply from IT Consultancy',
        "
        <h2>Hello {$message['name']},</h2>

        <p>
            You have received a new reply regarding your inquiry.
        </p>

        <p>
            Please click the button below to continue the conversation.
        </p>

        <p>
            <a
                href='{$conversationLink}'
                style='
                    background:#0d6efd;
                    color:white;
                    padding:10px 20px;
                    text-decoration:none;
                    border-radius:5px;
                    display:inline-block;
                '
            >
                View Conversation
            </a>
        </p>

        <p>
            IT Consultancy Team
        </p>
        "
    );
}

        header("Location: ?page=view&id=" . $id);
        exit;
    }
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<h2>View Message</h2>

<div class="card p-3">
    <p><strong>Name:</strong> <?= htmlspecialchars($message['name']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($message['email']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($message['phone']) ?></p>
    <p><strong>Preferred Contact:</strong> <?= htmlspecialchars($message['preferred_contact']) ?></p>
    <p><strong>Service:</strong><?= htmlspecialchars($message['service']) ?></p>
    <p><strong>Date:</strong> <?= formatDateTime($message['created_at']) ?></p>
</div>

<div class="card p-3 mt-3">

    <h4>Conversation</h4>

    <?php if (empty($replies)): ?>

        <p class="text-muted">
            No replies yet.
        </p>

    <?php else: ?>

        <?php foreach ($replies as $reply): ?>

            <div class="border rounded p-2 mb-2">

                <strong>
                    <?php if ($reply['sender'] === 'visitor'): ?>

                        <?= htmlspecialchars($message['name']) ?>

                    <?php else: ?>

                        IT Consultancy

                    <?php endif; ?>
                </strong>

                <small class="text-muted">
                    (<?= formatDateTime($reply['created_at']) ?>)
                </small>

                <br>

                <?= nl2br(htmlspecialchars($reply['reply_text'])) ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php if (!$message['is_closed']): ?>
    <div class="card p-3 mt-3">

        <h4>Reply to Visitor</h4>

        <form method="POST">

            <div class="mb-3">

                <textarea
                    name="reply"
                    class="form-control"
                    rows="5"
                    placeholder="Type your reply..."
                    required></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                Send Reply

            </button>

        </form>

    </div>

<?php else: ?>

    <div class="alert alert-secondary mt-3">
        <strong>Conversation Closed</strong><br>
        This conversation has been closed by the visitor and is now archived.
    </div>

<?php endif; ?>

<br>
<a class="btn btn-secondary" href="?page=messages">Back</a>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>