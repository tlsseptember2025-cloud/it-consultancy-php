<?php

if (!isset($_SESSION['user'])) {

    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$totalMessages = $pdo->query("
    SELECT COUNT(*) FROM messages
")->fetchColumn();

$unreadMessages = $pdo->query("
    SELECT COUNT(*) FROM messages
    WHERE status = 'unread'
")->fetchColumn();

$totalServices = $pdo->query("
    SELECT COUNT(*) FROM services
")->fetchColumn();

$latestMessage = $pdo->query("
    SELECT * FROM messages
    ORDER BY created_at DESC
    LIMIT 1
")->fetch();

?>

<?php require __DIR__ . '/layouts/header.php'; ?>

<h1 class="mb-5">
    Dashboard
</h1>

<div class="row g-4">

    <div class="col-md-4">

        <div class="card bg-primary text-white shadow-sm">

            <div class="card-body">

                <h4>Total Messages</h4>

                <h1>
                    <?= $totalMessages ?>
                </h1>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card bg-danger text-white shadow-sm">

            <div class="card-body">

                <h4>Unread Messages</h4>

                <h1>
                    <?= $unreadMessages ?>
                </h1>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card bg-success text-white shadow-sm">

            <div class="card-body">

                <h4>Total Services</h4>

                <h1>
                    <?= $totalServices ?>
                </h1>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm mt-5">

    <div class="card-body">

        <h3 class="mb-4">
            Latest Message
        </h3>

        <?php if ($latestMessage): ?>

            <p>

                <strong>Name:</strong>

                <?= htmlspecialchars($latestMessage['name']) ?>

            </p>

            <p>

                <strong>Service:</strong>

                <?= htmlspecialchars($latestMessage['service']) ?>

            </p>

            <p>

                <strong>Message:</strong>

                <?= htmlspecialchars($latestMessage['message']) ?>

            </p>

        <?php else: ?>

            <p>
                No messages found.
            </p>

        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>