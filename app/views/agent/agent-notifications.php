<?php

if (!isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Agent ID
|--------------------------------------------------------------------------
*/

$agentId = (int) $_SESSION['agent']['id'];


/*
|--------------------------------------------------------------------------
| Mark All Agent Notifications As Read
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_all_read'])
) {

    $stmt = $pdo->prepare("
        UPDATE notifications

        SET
            is_read = 1

        WHERE
            recipient_type = 'agent'
            AND recipient_id = ?
    ");

    $stmt->execute([
        $agentId
    ]);

    header('Location: ?page=agent-notifications');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Agent Notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        *
    FROM notifications

    WHERE
        recipient_type = 'agent'
        AND recipient_id = ?

    ORDER BY
        created_at DESC,
        id DESC
");

$stmt->execute([
    $agentId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Notifications
            </h2>

            <p class="text-muted mb-0">
                View updates and notifications related to your work.
            </p>

        </div>

        <?php if (!empty($notifications)): ?>

            <form method="POST">

                <button
                    type="submit"
                    name="mark_all_read"
                    class="btn btn-outline-success">

                    <i class="bi bi-check2-all"></i>
                    Mark All as Read

                </button>

            </form>

        <?php endif; ?>

    </div>


    <?php if (empty($notifications)): ?>

        <div class="card shadow-sm">

            <div class="card-body text-center py-5">

                <div class="fs-1 mb-3">
                    🔔
                </div>

                <h5>
                    No notifications
                </h5>

                <p class="text-muted mb-0">
                    You currently have no notifications.
                </p>

            </div>

        </div>

    <?php else: ?>

        <div class="list-group shadow-sm">

            <?php foreach ($notifications as $notification): ?>

                <?php
                    $isUnread = (int)$notification['is_read'] === 0;
                ?>

                <a
                    href="?page=agent-open-notification&id=<?= (int) $notification['id'] ?>"
                    class="list-group-item list-group-item-action
                        <?= $isUnread ? 'fw-semibold bg-light' : '' ?>"
                >

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <div class="mb-1">

                                <?= htmlspecialchars(
                                    $notification['title']
                                ) ?>

                                <?php if ($isUnread): ?>

                                    <span class="badge bg-primary ms-2">
                                        New
                                    </span>

                                <?php endif; ?>

                            </div>

                            <div class="text-muted">

                                <?= htmlspecialchars(
                                    $notification['message']
                                ) ?>

                            </div>

                        </div>

                        <small class="text-muted ms-3">

                            <?= htmlspecialchars(
                                $notification['created_at']
                            ) ?>

                        </small>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>