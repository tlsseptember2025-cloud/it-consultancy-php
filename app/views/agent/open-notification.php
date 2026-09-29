<?php

/*
|--------------------------------------------------------------------------
| Agent Authentication
|--------------------------------------------------------------------------
*/

$isDemoAgent = isset($_SESSION['demo_agent']);

if (!$isDemoAgent && !isset($_SESSION['agent'])) {

    header('Location: ?page=public-login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

if ($isDemoAgent) {

    require_once CONFIG_PATH . '/demo-database.php';

    $db = $demoPdo;

    $agentId = (int) $_SESSION['demo_agent']['id'];
    $demoTenantId = (int) ($_SESSION['demo_agent']['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0) {

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    require_once CONFIG_PATH . '/database.php';

    $db = $pdo;

    $agentId = (int) $_SESSION['agent']['id'];
}


$notificationId = (int) ($_GET['id'] ?? 0);

if ($notificationId <= 0) {

    die('Invalid notification.');
}


/*
|--------------------------------------------------------------------------
| Load Agent Notification
|--------------------------------------------------------------------------
|
| Normal Agent:
|   notification belongs to the logged-in Agent.
|
| Demo Agent:
|   notification belongs to the logged-in Demo Agent and the Agent
|   must belong to the current Demo tenant.
|
*/

if ($isDemoAgent) {

    $stmt = $db->prepare("
        SELECT
            n.id,
            n.title,
            n.message,
            n.link,
            n.is_read,
            n.created_at
        FROM notifications n
        INNER JOIN agents a
            ON a.id = n.recipient_id
        WHERE
            n.id = ?
            AND n.recipient_type = 'agent'
            AND n.recipient_id = ?
            AND a.demo_tenant_id = ?
            AND a.is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $notificationId,
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $db->prepare("
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
}


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

    if ($isDemoAgent) {

        $stmt = $db->prepare("
            UPDATE notifications n
            INNER JOIN agents a
                ON a.id = n.recipient_id
            SET n.is_read = 1
            WHERE
                n.id = ?
                AND n.recipient_type = 'agent'
                AND n.recipient_id = ?
                AND a.demo_tenant_id = ?
                AND a.is_demo_account = 1
        ");

        $stmt->execute([
            $notificationId,
            $agentId,
            $demoTenantId
        ]);

    } else {

        $stmt = $db->prepare("
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