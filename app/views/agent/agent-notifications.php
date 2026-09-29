<?php

/*
|--------------------------------------------------------------------------
| Determine Active Agent Session / Database
|--------------------------------------------------------------------------
*/

$isDemoAgent = isset($_SESSION['demo_agent']);

if ($isDemoAgent) {

    require_once HELPER_PATH . '/auth.php';
    requireDemoAgent();

    require_once CONFIG_PATH . '/demo-database.php';

    $activeAgent = $_SESSION['demo_agent'];
    $agentId = (int) $activeAgent['id'];
    $demoTenantId = (int) ($activeAgent['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $agentPdo = $demoPdo;

} else {

    if (!isset($_SESSION['agent'])) {
        header('Location: ?page=public-login');
        exit;
    }

    require_once CONFIG_PATH . '/database.php';

    $activeAgent = $_SESSION['agent'];
    $agentId = (int) $activeAgent['id'];

    $agentPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Mark All Agent Notifications As Read
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_all_read'])
) {

    if ($isDemoAgent) {

        $stmt = $agentPdo->prepare("
            UPDATE notifications n
            INNER JOIN agents a
                ON a.id = n.recipient_id
            SET n.is_read = 1
            WHERE n.recipient_type = 'agent'
              AND n.is_read = 0
              AND a.id = ?
              AND a.demo_tenant_id = ?
              AND a.is_demo_account = 1
        ");

        $stmt->execute([
            $agentId,
            $demoTenantId
        ]);

    } else {

        $stmt = $agentPdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE recipient_type = 'agent'
              AND recipient_id = ?
              AND is_read = 0
        ");

        $stmt->execute([
            $agentId
        ]);
    }

    header('Location: ?page=agent-notifications');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Agent Notifications
|--------------------------------------------------------------------------
*/

if ($isDemoAgent) {

    $stmt = $agentPdo->prepare("
        SELECT n.*
        FROM notifications n
        INNER JOIN agents a
            ON a.id = n.recipient_id
        WHERE n.recipient_type = 'agent'
          AND n.recipient_id = ?
          AND a.demo_tenant_id = ?
          AND a.is_demo_account = 1
        ORDER BY
            n.created_at DESC,
            n.id DESC
    ");

    $stmt->execute([
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $agentPdo->prepare("
        SELECT *
        FROM notifications
        WHERE recipient_type = 'agent'
          AND recipient_id = ?
        ORDER BY
            created_at DESC,
            id DESC
    ");

    $stmt->execute([
        $agentId
    ]);
}

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Check for unread notifications
|--------------------------------------------------------------------------
*/

$hasUnread = false;

foreach ($notifications as $notification) {

    if ((int) $notification['is_read'] === 0) {
        $hasUnread = true;
        break;
    }
}


require VIEW_PATH . '/layouts/header-agent.php';

?>

<div class="container py-4">

    <h1 class="mb-4">
        My Notifications
    </h1>

    <div class="card">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Notifications
                </h5>

                <?php if (!empty($notifications)): ?>

                    <form method="POST" class="mb-0">

                        <button
                            type="submit"
                            name="mark_all_read"
                            value="1"
                            class="btn btn-primary btn-sm">

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

                        <h5 class="mb-2">
                            No notifications
                        </h5>

                        <p class="text-muted mb-0">
                            You currently have no notifications.
                        </p>

                    </div>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-striped mb-0">

                        <thead>

                            <tr>

                                <th>Status</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($notifications as $notification): ?>

                                <?php
                                    $isUnread = (int) $notification['is_read'] === 0;
                                ?>

                                <tr class="<?= $isUnread ? 'table-warning' : '' ?>">

                                    <td>

                                        <?php if ($isUnread): ?>

                                            <span class="badge bg-warning text-dark">
                                                🔔 New
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-success">
                                                ✓ Read
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $notification['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $notification['message'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $notification['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <a
                                            href="?page=agent-open-notification&id=<?= (int) $notification['id'] ?>"
                                            class="btn btn-sm btn-primary">

                                            Open

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>
