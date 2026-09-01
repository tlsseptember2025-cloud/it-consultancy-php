<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$agentId = (int) $_SESSION['agent']['id'];

$notificationId = (int) ($_GET['id'] ?? 0);

if ($notificationId <= 0) {

    die('Invalid notification.');
}


/*
|--------------------------------------------------------------------------
| Load Agent Notification
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        message,
        link,
        is_read,
        created_at
    FROM notifications
    WHERE
        id = ?
        AND recipient_type = 'agent'
        AND recipient_id = ?
    LIMIT 1
");

$stmt->execute([
    $notificationId,
    $agentId
]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$notification) {

    die('Notification not found.');

}


/*
|--------------------------------------------------------------------------
| Mark Notification As Read
|--------------------------------------------------------------------------
*/

if (!(int) $notification['is_read']) {

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE
            id = ?
            AND recipient_type = 'agent'
            AND recipient_id = ?
    ");

    $stmt->execute([
        $notificationId,
        $agentId
    ]);

}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header bg-primary text-white">

            <strong>
                <?= htmlspecialchars($notification['title']) ?>
            </strong>

        </div>

        <div class="card-body">

            <p class="text-muted mb-3">

                <?= htmlspecialchars(
                    $notification['created_at']
                ) ?>

            </p>

            <div class="border rounded p-3 bg-light">

                <?= nl2br(
                    htmlspecialchars(
                        $notification['message']
                    )
                ) ?>

            </div>

            <div class="mt-4">

                <a
                    href="?page=agent-notifications"
                    class="btn btn-secondary">

                    ← Back to Notifications

                </a>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>