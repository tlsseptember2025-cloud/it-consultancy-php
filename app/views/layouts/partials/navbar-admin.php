<?php

$notificationCount = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE recipient_type = 'admin'
          AND is_read = 0
    ");

    $notificationCount = (int) $stmt->fetchColumn();

} catch (Exception $e) {

    $notificationCount = 0;

}


$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM requests
    WHERE workflow_stage = ?
");

$stmt->execute([
    'Needs Admin Review'
]);

$needsAdminReviewCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];


?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="?page=dashboard"
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

                <!-- Needs Admin Review -->

                <li class="nav-item">
                    <a class="nav-link <?= $needsAdminReviewCount > 0 ? 'text-warning fw-semibold' : '' ?>"
                    href="?page=needs-admin-review">
                        Needs Admin Review
                    </a>
                </li>

                <!-- Services -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=services-admin">

                        Services

                    </a>


                </li>

                <li class="nav-item">
                    <a href="?page=pricing" class="nav-link">
                        Price List
                    </a> 
                </li>

                <!-- Customers -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=customers">

                        Customers

                    </a>

                </li>

                <!-- Agents -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Agents

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=agents">

                                View Agents

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=add-agent">

                                Add Agent

                            </a>

                        </li>

                    </ul>

                </li>

                <!-- Requests -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Requests

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=requests">

                                Current Requests

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=archived-requests">

                                Archived Requests

                            </a>

                        </li>

                    </ul>

                </li>

                <!-- Finance -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Finance

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=payments">

                                Payments

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=refund-requests">

                                Refund Requests

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=refunds">

                                Approved Refunds

                            </a>

                        </li>

                    </ul>

                </li>

                                <!-- System -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        System

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=messages">

                                Active Messages

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=archived-messages">

                                Archived Messages

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=backup">

                                Database Backup

                            </a>

                        </li>

                    </ul>

                </li>

                <!-- Notifications -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link position-relative"
                        href="#"
                        id="notificationsDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <i class="bi bi-bell-fill"></i>

                        <?php if ($notificationCount > 0): ?>

                            <span
                                id="notification-count"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

                                <?= $notificationCount ?>

                            </span>

                        <?php endif; ?>

                    </a>

                    <ul
                        class="dropdown-menu dropdown-menu-end"
                        style="width: 380px;"
                        id="notification-list">

                        <?php

                        $stmt = $pdo->query("
                            SELECT *
                            FROM notifications
                            WHERE recipient_type = 'admin'
                              AND is_read = 0
                            ORDER BY created_at DESC
                            LIMIT 10
                        ");

                        $notifications = $stmt->fetchAll();

                        ?>

                        <?php if (empty($notifications)): ?>

                            <li class="dropdown-item text-muted">

                                No notifications

                            </li>

                        <?php else: ?>

                            <?php foreach ($notifications as $notification): ?>

                                <li>

                                    <a
                                        class="dropdown-item"
                                        href="?page=open-notification&id=<?= $notification['id'] ?>">

                                        <strong>

                                            <?= htmlspecialchars($notification['title']) ?>

                                        </strong>

                                        <br>

                                        <small>

                                            <?= htmlspecialchars($notification['message']) ?>

                                        </small>

                                        <br>

                                        <small class="text-muted">

                                            <?= $notification['created_at'] ?>

                                        </small>

                                    </a>

                                </li>

                                <li>

                                    <hr class="dropdown-divider">

                                </li>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        <li>

                            <a
                                class="dropdown-item text-center"
                                href="?page=notifications">

                                View All Notifications

                            </a>

                        </li>

                    </ul>

                </li>

                <!-- Logout -->

                <li class="nav-item">

                    <a
                        class="nav-link text-danger"
                        href="?page=logout">

                        Logout

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>