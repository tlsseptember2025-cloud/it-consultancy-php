<?php

require_once __DIR__ . '/../helpers/auth.php';
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

require __DIR__ . '/layouts/header.php';
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
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($notifications as $notification): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($notification['title']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($notification['message']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($notification['created_at']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>