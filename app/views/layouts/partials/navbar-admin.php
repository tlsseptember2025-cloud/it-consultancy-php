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

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="?page=dashboard">
            <?= PRODUCT_NAME ?>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <div class="navbar-nav ms-auto">

<!-- ADMIN MENU -->

                    <a class="nav-link" href="?page=dashboard">
                        Dashboard
                    </a>

                    <a class="nav-link" href="?page=services-admin">
                        Services
                    </a>

                    <a class="nav-link" href="?page=customers">
                        Customers
                    </a>

                    <div class="nav-item dropdown">

    <a
        class="nav-link dropdown-toggle"
        href="#"
        role="button"
        data-bs-toggle="dropdown">

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

</div>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

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

                    </div>
                    
                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

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
                                <a class="dropdown-item" href="?page=refund-requests">
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

                    </div>


                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

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

                    </div>

                    <li class="nav-item dropdown">

    <a
        class="nav-link position-relative"
        href="#"
        id="notificationsDropdown"
        role="button"
        data-bs-toggle="dropdown"
        aria-expanded="false">

        🔔

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

if (empty($notifications)):

?>

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

        <li><hr class="dropdown-divider"></li>

    <?php endforeach; ?>
    

<?php endif; ?>

<li>
    <hr class="dropdown-divider">
</li>

<li>

    <a
        class="dropdown-item text-center"
        href="?page=notifications">

        View All Notifications

    </a>

</li>

</ul>

</li>
                   

                    <a class="nav-link text-danger" href="?page=logout">
                        Logout
                    </a>

            </div>

        </div>

    </div>

</nav>