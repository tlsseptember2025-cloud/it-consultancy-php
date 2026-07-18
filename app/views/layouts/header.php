<?php

$customerNotificationCount = 0;
$customerNotifications = [];

if (isset($_SESSION['customer'])) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE recipient_type = 'customer'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        (int) $_SESSION['customer']['id']
    ]);

    $customerNotificationCount = (int) $stmt->fetchColumn();
  
  	$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE recipient_type = 'customer'
      AND recipient_id = ?
      AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([
    (int) $_SESSION['customer']['id']
]);

$customerNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
}
?>

<!DOCTYPE html>
<html>

<head>

    <title><?= PRODUCT_NAME ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

</head>

<body>

<?php if (isDemo()): ?>

<div class="bg-warning text-dark text-center py-2 small">

    <strong>Demo Version</strong>

    | This is a demonstration of the software.

    | Data may be reset periodically.

</div>

<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="?page=home">
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

                <?php if (isset($_SESSION['user'])): ?>

                    <?php

$notificationCount = 0;

try {

    require dirname(__DIR__, 3) . '/config/database.php';

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

                <?php elseif (isset($_SESSION['customer'])): ?>

                    <!-- CUSTOMER MENU -->

                    <a class="nav-link" href="?page=customer-dashboard">
                        Dashboard
                    </a>

                    <a class="nav-link" href="?page=customer-requests">
                        My Requests
                    </a>

                    <a class="nav-link" href="?page=customer-payments">
                        My Payments
                    </a>

                    <a class="nav-link" href="?page=customer-refunds">
                        My Refunds
                    </a>
  
  					<li class="nav-item dropdown">

    <a
        class="nav-link position-relative"
        href="#"
        data-bs-toggle="dropdown">

        🔔

        <?php if ($customerNotificationCount > 0): ?>

            <span
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

                <?= $customerNotificationCount ?>

            </span>

        <?php endif; ?>

    </a>

    <ul class="dropdown-menu dropdown-menu-end" style="min-width:350px;">

    <?php if (empty($customerNotifications)): ?>

        <li>

            <span class="dropdown-item-text text-muted">

                No new notifications

            </span>

        </li>

    <?php else: ?>

        <?php foreach ($customerNotifications as $notification): ?>

            <li>

                <a
                    class="dropdown-item"
                    href="?page=customer-notifications">

                    <strong>

                        <?= htmlspecialchars($notification['title']) ?>

                    </strong>

                    <br>

                    <small class="text-muted">

                        <?= htmlspecialchars($notification['message']) ?>

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
            href="?page=customer-notifications">

            View All Notifications

        </a>

    </li>

</ul>

</li>

                    <a class="nav-link text-danger"
                       href="?page=customer-logout">
                        Logout
                    </a>

                <?php else: ?>

                    <!-- PUBLIC MENU -->

                    <a class="nav-link" href="?page=home">
                        Home
                    </a>

                    <a class="nav-link" href="?page=services">
                        Services
                    </a>

                    <a class="nav-link" href="?page=contact">
                        Contact
                    </a>

                    <a class="nav-link" href="?page=customer-register">
                        Register
                    </a>

                    <a class="nav-link" href="?page=public-login">
                        Login
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>

<div class="container py-4">

<?php if (!empty($_SESSION['error'])): ?>

    <div class="alert alert-danger alert-dismissible fade show">

        <?= htmlspecialchars($_SESSION['error']) ?>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"></button>

    </div>

    <?php unset($_SESSION['error']); ?>

<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>

    <div class="alert alert-success alert-dismissible fade show">

        <?= htmlspecialchars($_SESSION['success']) ?>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"></button>

    </div>

    <?php unset($_SESSION['success']); ?>

<?php endif; ?>