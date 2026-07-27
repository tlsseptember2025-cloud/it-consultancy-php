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

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand fw-bold"
            href="?page=customer-dashboard"
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

                <!-- Requests -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=customer-requests">

                        My Requests

                    </a>

                </li>

                <!-- Payments -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=customer-payments">

                        My Payments

                    </a>

                </li>

                <!-- Refunds -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="?page=customer-refunds">

                        My Refunds

                    </a>

                </li>

                                <!-- Notifications -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link position-relative"
                        href="#"
                        id="customerNotificationsDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <i class="bi bi-bell-fill"></i>

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

                                <li>

                                    <hr class="dropdown-divider">

                                </li>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        <li>

                            <a
                                class="dropdown-item text-center fw-bold"
                                href="?page=customer-notifications">

                                View All Notifications

                            </a>

                        </li>

                    </ul>

                </li>

                <!-- Logout -->

                <li class="nav-item">

                    <a
                        class="nav-link text-danger"
                        href="?page=customer-logout">

                        Logout

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>