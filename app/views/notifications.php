<?php

require_once __DIR__ . '/../helpers/auth.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';

require __DIR__ . '/layouts/header.php';

$stmt = $pdo->query("
    SELECT *
    FROM notifications
    WHERE recipient_type = 'admin'
    ORDER BY created_at DESC
");

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mb-4 pt-3">
    Notification History
</h1>

<div class="mb-3">

    <a
        href="?page=mark-all-notifications-read"
        class="btn btn-success"
        onclick="return confirm('Mark all notifications as read?');">

        ✓ Mark All as Read

    </a>

</div>

<div class="card">

    <div class="card-body">

        <table class="table table-striped">

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

                    <tr class="<?= !$notification['is_read'] ? 'table-warning' : '' ?>">

                        <td>

                            <?php if ($notification['is_read']): ?>

    <span class="badge bg-success">
        ✓ Read
    </span>

<?php else: ?>

    <span class="badge bg-warning text-dark">
        🔔 New
    </span>

<?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($notification['title']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($notification['message']) ?>
                        </td>

                        <td>
                            <?= $notification['created_at'] ?>
                        </td>

                        <td>

                            <a
                                href="?page=open-notification&id=<?= $notification['id'] ?>"
                                class="btn btn-sm btn-primary">

                                Open

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>