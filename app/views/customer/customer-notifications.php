<?php

require_once APP_PATH . '/helpers/DateHelper.php';

if (!isset($_SESSION['customer'])) {
    header('Location: ?page=public-login');
    exit;
}

require_once HELPER_PATH . '/auth.php';
requireCustomerLogin();

$customerId = (int) $_SESSION['customer']['id'];

/*
|--------------------------------------------------------------------------
| Mark all customer notifications as read
|--------------------------------------------------------------------------
|
| Only perform this action when the customer explicitly submits
| the "Mark All as Read" form.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_all_read'])
) {
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'customer'
          AND recipient_id = ?
          AND is_read = 0
    ");

    $stmt->execute([$customerId]);

    header('Location: ?page=customer-notifications');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load notifications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE recipient_type = 'customer'
      AND recipient_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$customerId]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Count unread notifications
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

            <?php if ($unreadCount > 0): ?>

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

            <div class="alert alert-info">
                You have no notifications.
            </div>

        <?php else: ?>

            <table class="table table-striped">

                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($notifications as $notification): ?>

                        <tr>

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
                                <?= htmlspecialchars($notification['title']) ?>

                                <?php if ((int) $notification['is_read'] === 0): ?>

                                    <span class="badge bg-primary ms-2">
                                        New
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($notification['message']) ?>
                            </td>

                            <td>
                                <?= formatDateTime($notification['created_at']) ?>
                            </td>

                            <td>

                                <?php if (!empty($notification['link'])): ?>

                                    <a
                                        href="<?= htmlspecialchars($notification['link']) ?>"
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

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>