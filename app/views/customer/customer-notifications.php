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
*/

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE recipient_type = 'customer'
      AND recipient_id = ?
");

$stmt->execute([$customerId]);

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

require dirname(__DIR__) . '/layouts/header-customer.php';
?>

<h1 class="mb-4">My Notifications</h1>

<div class="card">

    <div class="card-body">

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

    <?= $icon ?> <?= htmlspecialchars($notification['title']) ?>

</td>
                                
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
                                
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>