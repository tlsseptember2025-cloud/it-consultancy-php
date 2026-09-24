<?php

require_once APP_PATH . '/helpers/DateHelper.php';

/*
|--------------------------------------------------------------------------
| Select Admin Database
|--------------------------------------------------------------------------
|
| Main Admin:
|     $pdo
|
| Demo Admin / Demo Super Admin:
|     $demoPdo
|
*/

$adminPdo = $pdo;

if (
    isset($_SESSION['demo_super_admin']) ||
    isset($_SESSION['demo_user'])
) {

    if (!isset($demoPdo)) {
        require_once CONFIG_PATH . '/demo-database.php';
    }

    $adminPdo = $demoPdo;
}


/*
|--------------------------------------------------------------------------
| Notification Count
|--------------------------------------------------------------------------
*/

$notificationCount = 0;

try {

    $stmt = $adminPdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE recipient_type = 'admin'
          AND is_read = 0
    ");

    $notificationCount = (int) $stmt->fetchColumn();

} catch (Exception $e) {

    $notificationCount = 0;

}


/*
|--------------------------------------------------------------------------
| Needs Admin Review Count
|--------------------------------------------------------------------------
*/

$needsAdminReviewCount = 0;

try {

    $stmt = $adminPdo->prepare("
        SELECT COUNT(*) AS total
        FROM requests
        WHERE workflow_stage = ?
    ");

    $stmt->execute([
        'Needs Admin Review'
    ]);

    $needsAdminReviewCount =
        (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (Exception $e) {

    $needsAdminReviewCount = 0;

}

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


                <!-- Services -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Services

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=services-admin">

                                Services

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=pricing">

                                Price List

                            </a>

                        </li>

                    </ul>

                </li>


                <!-- Customers -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Customers

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=customers">

                                Customers

                            </a>

                        </li>

                    </ul>

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

                    </ul>

                </li>


                <!-- Requests -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle <?= $needsAdminReviewCount > 0 ? 'text-warning fw-semibold' : '' ?>"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Requests

                        <?php if ($needsAdminReviewCount > 0): ?>

                            <span class="badge bg-warning text-dark ms-1">
                                <?= $needsAdminReviewCount ?>
                            </span>

                        <?php endif; ?>

                    </a>


                    <ul class="dropdown-menu">

                        <!-- Current Requests -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=requests">

                                Current Requests

                            </a>

                        </li>


                        <!-- Needs Admin Review -->

                        <li>

                            <a
                                class="dropdown-item <?= $needsAdminReviewCount > 0 ? 'text-warning fw-semibold' : '' ?>"
                                href="?page=needs-admin-review">

                                Needs Admin Review

                                <?php if ($needsAdminReviewCount > 0): ?>

                                    <span class="badge bg-warning text-dark float-end">
                                        <?= $needsAdminReviewCount ?>
                                    </span>

                                <?php endif; ?>

                            </a>

                        </li>


                        <!-- Awaiting Customer Response -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=awaiting-customer-response">

                                Awaiting Customer Response

                            </a>

                        </li>


                        <!-- Closed Requests -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=closed-requests">

                                Closed Requests

                            </a>

                        </li>


                        <!-- Archived Requests -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=archived-requests">

                                Archived Requests

                            </a>

                        </li>


                        <!-- Retention Review -->

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=retention-review">

                                Retention Review

                            </a>

                        </li>

                    </ul>

                </li>


                <!-- Consultations -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        Consultations

                    </a>

                    <ul class="dropdown-menu">

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=closure-agreements">

                                Pending Closure Agreements

                            </a>

                        </li>

                        <li>

                            <a
                                class="dropdown-item"
                                href="?page=approved-closures">

                                Approved Closures

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


                <?php if (
                    !isset($_SESSION['demo_user']) &&
                    !isset($_SESSION['demo_super_admin'])
                ): ?>

                    <!-- Communications -->

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">

                            Communications

                        </a>

                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=guest-chats">

                                    Guest Chats

                                </a>

                            </li>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=demo-password-recovery-requests">

                                    Demo Pass Recovery Requests

                                </a>

                            </li>

                        </ul>

                    </li>

                <?php endif; ?>


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

                        $stmt = $adminPdo->query("
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
                                        href="?page=open-notification&id=<?= (int) $notification['id'] ?>">

                                        <strong>

                                            <?= htmlspecialchars($notification['title']) ?>

                                        </strong>

                                        <br>

                                        <small>

                                            <?= htmlspecialchars($notification['message']) ?>

                                        </small>

                                        <br>

                                        <small class="text-muted">

                                            <?= formatDateTime($notification['created_at']) ?>

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


                <?php if (
                    !isset($_SESSION['demo_user'])
                ): ?>

                    <!-- Account -->

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">

                            Account

                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=admin-change-password">

                                    Change Password

                                </a>

                            </li>

                        </ul>

                    </li>

                <?php endif; ?>


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