<?php

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once CONFIG_PATH . '/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

$stmt = $pdo->query("
    SELECT
        id,
        guest_name,
        guest_email,
        subject,
        status,
        started_at,
        ended_at
    FROM guest_chat_conversations
    WHERE status = 'Closed'
    ORDER BY ended_at DESC, id DESC
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Guest Chats
            </h2>

            <p class="text-muted mb-0">
                Closed guest chat conversations and their complete history.
            </p>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="card-body">

            <?php if (empty($conversations)): ?>

                <div class="alert alert-secondary mb-0">
                    No closed guest chats are available.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Customer Name
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Start Date/Time
                                </th>

                                <th>
                                    End Date/Time
                                </th>

                                <th>
                                    View
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($conversations as $conversation): ?>

                                <tr>

                                    <td>

                                        <?= htmlspecialchars(
                                            $conversation['guest_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $conversation['subject'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= formatDateTime(
                                            $conversation['started_at']
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= !empty($conversation['ended_at'])
                                            ? formatDateTime(
                                                $conversation['ended_at']
                                            )
                                            : '—'
                                        ?>

                                    </td>

                                    <td>

                                        <a
                                            href="?page=guest-chat-conversation-admin&id=<?= (int) $conversation['id'] ?>"
                                            class="btn btn-sm btn-primary">

                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
