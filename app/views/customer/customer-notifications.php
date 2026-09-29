<?php

require_once APP_PATH . '/helpers/DateHelper.php';
require_once HELPER_PATH . '/auth.php';

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $notificationPdo = $demoPdo;

    $customerId = (int) ($_SESSION['demo_customer']['id'] ?? 0);
    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {
        unset($_SESSION['demo_customer']);
        header('Location: ?page=demo-login');
        exit;
    }

    $demoCustomerCheck = $notificationPdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $demoCustomerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$demoCustomerCheck->fetchColumn()) {
        unset($_SESSION['demo_customer']);
        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $notificationPdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}


/*
|--------------------------------------------------------------------------
| Open Individual Notification
|--------------------------------------------------------------------------
|
| When a customer clicks a notification from the bell or from the
| notification list:
|
| 1. Confirm the notification belongs to this customer.
| 2. Mark ONLY that notification as read.
| 3. Redirect to its existing stored link.
|
*/

if (
    isset($_GET['id'])
    && ctype_digit((string) $_GET['id'])
) {

    $notificationId = (int) $_GET['id'];

    $stmt = $notificationPdo->prepare("
        SELECT id, link
        FROM notifications
        WHERE id = ?
          AND recipient_type = 'customer'
          AND recipient_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $notificationId,
        $customerId
    ]);

    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($notification) {

        /* Mark ONLY this notification as read */
        $stmt = $notificationPdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ?
              AND recipient_type = 'customer'
              AND recipient_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $customerId
        ]);

        /* Follow the notification's existing destination */
        if (!empty($notification['link'])) {

            header(
                'Location: ' . $notification['link']
            );
            exit;
        }
    }

    /*
     * If the notification does not exist, does not belong
     * to this customer, or has no destination, return to
     * the notification list.
     */
    header('Location: ?page=customer-notifications');
    exit;
}


/*
|--------------------------------------------------------------------------
| Mark All Customer Notifications as Read
|--------------------------------------------------------------------------
|
| This action only happens when the customer explicitly
| submits the "Mark All as Read" form.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_all_read'])
) {

    $stmt = $notificationPdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'customer'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $customerId
    ]);

    header('Location: ?page=customer-notifications');
    exit;
}


/*
|--------------------------------------------------------------------------
| Load Notifications
|--------------------------------------------------------------------------
*/

$stmt = $notificationPdo->prepare("
    SELECT *
    FROM notifications
    WHERE recipient_type = 'customer'
      AND recipient_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([
    $customerId
]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Count Unread Notifications
|--------------------------------------------------------------------------
*/

$unreadCount = 0;

foreach ($notifications as $notification) {

    if ((int) $notification['is_read'] === 0) {
        $unreadCount++;
    }
}


require dirname(__DIR__) . '/layouts/header-customer.php';

?>

<h1 class="mb-4">My Notifications</h1>

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
                                $isUnread =
                                    (int) $notification['is_read'] === 0;
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

                                    <?php
                                    $icon = '🔔';

                                    switch ($notification['title']) {

                                        case 'Proposal Ready':
                                            $icon = '📄';
                                            break;

                                        case 'Payment Rejected':
                                            $icon = '❌';
                                            break;

                                        case 'Payment Approved':
                                            $icon = '✅';
                                            break;

                                        case 'Service Scheduled':
                                            $icon = '📅';
                                            break;

                                        case 'Service Completed':
                                            $icon = '🎉';
                                            break;

                                        case 'Refund Approved':
                                            $icon = '💰';
                                            break;
                                    }
                                    ?>

                                    <?= $icon ?>

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

                                    <?= formatDateTime(
                                        $notification['created_at']
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (!empty($notification['link'])): ?>

                                        <a
                                            href="?page=customer-notifications&id=<?= (int) $notification['id'] ?>"
                                            class="btn btn-primary btn-sm">

                                            Open

                                        </a>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
