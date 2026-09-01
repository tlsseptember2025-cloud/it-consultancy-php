<?php

/*
|--------------------------------------------------------------------------
| Customer Contact Approved Count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM consultation_bookings cb
    INNER JOIN requests r
        ON r.id = cb.request_id
    WHERE
        cb.agent_id = ?
        AND r.workflow_stage = ?
");

$stmt->execute([
    $_SESSION['agent']['id'],
    'Customer Contact Approved'
]);

$customerContactApprovedCount =
    (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];


/*
|--------------------------------------------------------------------------
| Agent Notifications
|--------------------------------------------------------------------------
*/

$agentId = (int) $_SESSION['agent']['id'];

$stmt = $pdo->prepare("
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

$agentNotificationCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Agent Notifications
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