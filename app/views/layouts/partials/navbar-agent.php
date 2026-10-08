<?php

/*
|--------------------------------------------------------------------------
| Determine Active Agent Session / Database
|--------------------------------------------------------------------------
|
| Normal Agent  -> $_SESSION['agent'] / main database
| Demo Agent    -> $_SESSION['demo_agent'] / demo database
|
*/

$isDemoAgent = isset($_SESSION['demo_agent']);

if ($isDemoAgent) {

    require_once HELPER_PATH . '/auth.php';
    requireDemoAgent();

    require_once CONFIG_PATH . '/demo-database.php';

    $activeAgent = $_SESSION['demo_agent'];
    $activeAgentId = (int) $activeAgent['id'];
    $demoTenantId = (int) ($activeAgent['demo_tenant_id'] ?? 0);

    if ($demoTenantId <= 0 || $activeAgentId <= 0) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $tenantStmt = $demoPdo->prepare("
        SELECT id
        FROM demo_tenants
        WHERE id = ?
          AND status = 'Active'
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $tenantStmt->execute([$demoTenantId]);

    if (!$tenantStmt->fetchColumn()) {
        unset($_SESSION['demo_agent']);
        header('Location: ?page=demo-login');
        exit;
    }

    $agentCheck = $demoPdo->prepare("
        SELECT id
        FROM agents
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
          AND status = 'Active'
        LIMIT 1
    ");
    $agentCheck->execute([
        $activeAgentId,
        $demoTenantId
    ]);

    if (!$agentCheck->fetchColumn()) {
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
    $activeAgentId = (int) $activeAgent['id'];

    $agentPdo = $pdo;
}


/*
|--------------------------------------------------------------------------
| Customer Contact Approved Count
|--------------------------------------------------------------------------
*/

if ($isDemoAgent) {

    $stmt = $agentPdo->prepare("
        SELECT COUNT(*) AS total
        FROM consultation_bookings cb
        INNER JOIN requests r
            ON r.id = cb.request_id
        INNER JOIN agents a
            ON a.id = cb.agent_id
        WHERE
            cb.agent_id = ?
            AND r.workflow_stage = ?
            AND a.demo_tenant_id = ?
            AND a.is_demo_account = 1
    ");

    $stmt->execute([
        $activeAgentId,
        'Customer Contact Approved',
        $demoTenantId
    ]);

} else {

    $stmt = $agentPdo->prepare("
        SELECT COUNT(*) AS total
        FROM consultation_bookings cb
        INNER JOIN requests r
            ON r.id = cb.request_id
        WHERE
            cb.agent_id = ?
            AND r.workflow_stage = ?
    ");

    $stmt->execute([
        $activeAgentId,
        'Customer Contact Approved'
    ]);
}

$customerContactApprovedCount =
    (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];


/*
|--------------------------------------------------------------------------
| Agent Notifications
|--------------------------------------------------------------------------
*/

$agentId = $activeAgentId;

if ($isDemoAgent) {

    $stmt = $agentPdo->prepare("
        SELECT COUNT(*)
        FROM notifications n
        INNER JOIN agents a
            ON a.id = n.recipient_id
        WHERE
            n.recipient_type = 'agent'
            AND n.recipient_id = ?
            AND n.is_read = 0
            AND a.demo_tenant_id = ?
            AND a.is_demo_account = 1
    ");

    $stmt->execute([
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $agentPdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE
            recipient_type = 'agent'
            AND recipient_id = ?
            AND is_read = 0
    ");

    $stmt->execute([
        $agentId
    ]);
}

$agentNotificationCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Agent Notifications
|--------------------------------------------------------------------------
*/

if ($isDemoAgent) {

    $stmt = $agentPdo->prepare("
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
            n.recipient_type = 'agent'
            AND n.recipient_id = ?
            AND n.is_read = 0
            AND a.demo_tenant_id = ?
            AND a.is_demo_account = 1
        ORDER BY
            n.created_at DESC,
            n.id DESC
        LIMIT 5
    ");

    $stmt->execute([
        $agentId,
        $demoTenantId
    ]);

} else {

    $stmt = $agentPdo->prepare("
        SELECT
            id,
            title,
            message,
            link,
            is_read,
            created_at
        FROM notifications
        WHERE
            recipient_type = 'agent'
            AND recipient_id = ?
            AND is_read = 0
        ORDER BY
            created_at DESC,
            id DESC
        LIMIT 5
    ");

    $stmt->execute([
        $agentId
    ]);
}

$agentNotifications =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="?page=agent-dashboard"
            title="<?= COMPANY_TAGLINE ?>">

            <?= COMPANY_NAME ?>

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarNav">

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <!-- Consultations -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-consultations">

                        My Consultations

                    </a>

                </li>


                <!-- Service Jobs -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-jobs">

                        My Service Jobs

                    </a>

                </li>


                <!-- Profile -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=agent-profile">

                        Profile

                    </a>

                </li>

                <!-- Notifications -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link position-relative"
                        href="#"
                        id="agentNotificationsDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <span style="font-size:18px;">🔔</span>

                        <?php if ($agentNotificationCount > 0): ?>

                            <span
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

                                <?= $agentNotificationCount ?>

                                <span class="visually-hidden">
                                    unread notifications
                                </span>

                            </span>

                        <?php endif; ?>

                    </a>


                    <ul
                        class="dropdown-menu dropdown-menu-end shadow"
                        aria-labelledby="agentNotificationsDropdown"
                        style="min-width:360px;">

                        <?php if (empty($agentNotifications)): ?>

                            <li>

                                <span class="dropdown-item-text text-muted">

                                    No notifications

                                </span>

                            </li>

                        <?php else: ?>

                            <?php foreach ($agentNotifications as $notification): ?>

                                <li>

                                    <a
                                        class="dropdown-item <?= !$notification['is_read'] ? 'fw-bold bg-light' : '' ?>"
                                        href="?page=agent-open-notification&id=<?= (int) $notification['id'] ?>">

                                        <div>

                                            <?= htmlspecialchars(
                                                $notification['title']
                                            ) ?>

                                        </div>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $notification['message']
                                            ) ?>

                                        </small>

                                    </a>

                                </li>

                            <?php endforeach; ?>

                        <?php endif; ?>


                        <li>
                            <hr class="dropdown-divider">
                        </li>


                        <li>

                            <a
                                class="dropdown-item text-center fw-bold"
                                href="?page=agent-notifications">

                                View All Notifications

                            </a>

                        </li>

                    </ul>

                </li>


                <!-- Logout -->

                <li class="nav-item">

                    <a
                        class="nav-link text-danger"
                        href="?page=agent-logout">

                        Logout

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>
