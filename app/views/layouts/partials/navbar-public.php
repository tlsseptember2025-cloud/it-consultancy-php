<?php

require_once HELPER_PATH . '/GuestChatHelper.php';

/*
|--------------------------------------------------------------------------
| Guest Chat Availability
|--------------------------------------------------------------------------
|
| Public and Demo users use the current application database context.
| Keep the existing helper behavior unchanged.
|
*/

$guestChatAvailability = getGuestChatAvailability($pdo);


/*
|--------------------------------------------------------------------------
| Customer Notification Count
|--------------------------------------------------------------------------
*/

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

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand"
            href="?page=home">

            <?= htmlspecialchars(COMPANY_NAME) ?>

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

            <div class="navbar-nav ms-auto">


                <!-- =========================================================
                     DEMO SUPER ADMIN
                     ========================================================= -->

                <?php if (isset($_SESSION['demo_super_admin'])): ?>

                    <a
                        class="nav-link"
                        href="?page=demo-super-admin">

                        Dashboard

                    </a>

                    <span
                        class="nav-link text-warning fw-semibold">

                        Demo Super Admin

                    </span>

                    <a
                        class="nav-link text-danger"
                        href="?page=logout">

                        Logout

                    </a>


                <!-- =========================================================
                     DEMO ADMIN
                     ========================================================= -->

                <?php elseif (isset($_SESSION['demo_user'])): ?>

                    <a
                        class="nav-link"
                        href="?page=dashboard">

                        Dashboard

                    </a>

                    <span
                        class="nav-link text-warning fw-semibold">

                        Demo Admin

                    </span>

                    <a
                        class="nav-link text-danger"
                        href="?page=logout">

                        Logout

                    </a>


                <!-- =========================================================
                     DEMO CUSTOMER
                     ========================================================= -->

                <?php elseif (isset($_SESSION['demo_customer'])): ?>

                    <a
                        class="nav-link"
                        href="?page=customer-dashboard">

                        Dashboard

                    </a>

                    <span
                        class="nav-link text-warning fw-semibold">

                        Demo Customer

                    </span>

                    <a
                        class="nav-link text-danger"
                        href="?page=customer-logout">

                        Logout

                    </a>


                <!-- =========================================================
                     DEMO AGENT
                     ========================================================= -->

                <?php elseif (isset($_SESSION['demo_agent'])): ?>

                    <a
                        class="nav-link"
                        href="?page=agent-dashboard">

                        Dashboard

                    </a>

                    <span
                        class="nav-link text-warning fw-semibold">

                        Demo Agent

                    </span>

                    <a
                        class="nav-link text-danger"
                        href="?page=agent-logout">

                        Logout

                    </a>


                <!-- =========================================================
                     MAIN / DEV ADMIN
                     ========================================================= -->

                <?php elseif (isset($_SESSION['user'])): ?>

                    <a
                        class="nav-link"
                        href="?page=dashboard">

                        Dashboard

                    </a>

                    <a
                        class="nav-link"
                        href="?page=services-admin">

                        Services

                    </a>

                    <a
                        class="nav-link"
                        href="?page=customers">

                        Customers

                    </a>


                    <div class="nav-item dropdown">

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

                    </div>


                    <div class="nav-item dropdown">

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

                    </div>


                    <a
                        class="nav-link"
                        href="?page=guest-chats">

                        Live Chat

                    </a>


                    <a
                        class="nav-link text-danger"
                        href="?page=logout">

                        Logout

                    </a>


                <!-- =========================================================
                     NORMAL CUSTOMER
                     ========================================================= -->

                <?php elseif (isset($_SESSION['customer'])): ?>

                    <a
                        class="nav-link"
                        href="?page=customer-dashboard">

                        Dashboard

                    </a>

                    <a
                        class="nav-link"
                        href="?page=customer-requests">

                        My Requests

                    </a>

                    <a
                        class="nav-link"
                        href="?page=customer-payments">

                        My Payments

                    </a>

                    <a
                        class="nav-link"
                        href="?page=customer-refunds">

                        My Refunds

                    </a>


                    <!-- CUSTOMER NOTIFICATIONS -->

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link position-relative"
                            href="#"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">

                            🔔

                            <?php if ($customerNotificationCount > 0): ?>

                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

                                    <?= $customerNotificationCount ?>

                                </span>

                            <?php endif; ?>

                        </a>


                        <ul
                            class="dropdown-menu dropdown-menu-end"
                            style="min-width:350px;">

                            <?php if (empty($customerNotifications)): ?>

                                <li>

                                    <span
                                        class="dropdown-item-text text-muted">

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

                                                <?= htmlspecialchars(
                                                    $notification['title']
                                                ) ?>

                                            </strong>

                                            <br>

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
                                    href="?page=customer-notifications">

                                    View All Notifications

                                </a>

                            </li>

                        </ul>

                    </div>


                    <a
                        class="nav-link text-danger"
                        href="?page=customer-logout">

                        Logout

                    </a>


                <!-- =========================================================
                     PUBLIC VISITOR
                     ========================================================= -->

                <?php else: ?>

                    <a
                        class="nav-link"
                        href="?page=home">

                        Home

                    </a>


                    <a
                        class="nav-link"
                        href="?page=services">

                        Services

                    </a>


                    <?php if ($guestChatAvailability['available']): ?>

                        <a
                            class="nav-link"
                            href="?page=guest-chat">

                            Live Chat

                        </a>

                    <?php endif; ?>


                    <a
                        class="nav-link"
                        href="?page=customer-register">

                        Register

                    </a>


                    <a
                        class="nav-link"
                        href="?page=public-login">

                        Login

                    </a>

                <?php endif; ?>


            </div>

        </div>

    </div>

</nav>
