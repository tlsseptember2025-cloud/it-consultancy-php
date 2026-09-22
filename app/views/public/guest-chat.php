<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/GuestChatHelper.php';
require_once HELPER_PATH . '/notifications.php';

$guestChatAvailability = getGuestChatAvailability($pdo);

/*
|--------------------------------------------------------------------------
| Build the configured office-hours summary
|--------------------------------------------------------------------------
*/

$officeHoursStmt = $pdo->query("
    SELECT
        day_of_week,
        is_open,
        open_time,
        close_time
    FROM guest_chat_office_hours
    ORDER BY day_of_week ASC
");

$officeHours = $officeHoursStmt->fetchAll(PDO::FETCH_ASSOC);

$dayNames = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

$officeSchedule = [];

foreach ($officeHours as $hours) {

    $dayNumber = (int) $hours['day_of_week'];

    if ((int) $hours['is_open'] !== 1) {
        $officeSchedule[] = $dayNames[$dayNumber] . ': Closed';
        continue;
    }

    $open = date(
        'g:i A',
        strtotime($hours['open_time'])
    );

    $close = date(
        'g:i A',
        strtotime($hours['close_time'])
    );

    $officeSchedule[] =
        $dayNames[$dayNumber]
        . ': '
        . $open
        . ' - '
        . $close;
}

$availabilityMessage = '';

if (!$guestChatAvailability['office_open']) {

    $availabilityMessage =
        'Live Chat is currently unavailable because the office is closed.';

} elseif (!$guestChatAvailability['admin_online']) {

    $availabilityMessage =
        'Live Chat is currently unavailable because no Admin is online.';

}

/*
|--------------------------------------------------------------------------
| Start a new conversation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Always re-check availability immediately before
     * creating the conversation.
     */
    $guestChatAvailability = getGuestChatAvailability($pdo);

    if (!$guestChatAvailability['available']) {

        if (!$guestChatAvailability['office_open']) {

            $availabilityMessage =
                'Live Chat is currently unavailable because the office is closed.';

        } else {

            $availabilityMessage =
                'Live Chat is currently unavailable because no Admin is online.';

        }

    } else {

        $guestName = trim($_POST['guest_name'] ?? '');
        $guestEmail = trim($_POST['guest_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');

        $errors = [];

        if ($guestName === '') {
            $errors[] = 'Please enter your name.';
        }

        if (
            $guestEmail === ''
            || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($subject === '') {
            $errors[] = 'Please enter a subject.';
        }

        if (empty($errors)) {

            $insertStmt = $pdo->prepare("
                INSERT INTO guest_chat_conversations
                (
                    guest_name,
                    guest_email,
                    subject,
                    status,
                    started_at,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'Open',
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

            $insertStmt->execute([
                $guestName,
                $guestEmail,
                $subject
            ]);

            $conversationId = (int) $pdo->lastInsertId();

            $_SESSION['guest_chat_conversation_id'] =
                $conversationId;

            createNotification(
                $pdo,
                'admin',
                null,
                'New Guest Chat',
                $guestName
                . ' has started a new guest chat: '
                . $subject,
                '?page=guest-chat-conversation-admin&id='
                . $conversationId
            );

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7 col-md-9">

            <div class="card shadow-sm">

                <div class="card-header">

                    <h3 class="mb-1">
                        Live Chat
                    </h3>

                    <p class="text-muted mb-0">
                        Chat directly with our support team.
                    </p>

                </div>


                <div class="card-body">

                    <?php if (!$guestChatAvailability['available']): ?>

                        <div class="alert alert-warning">

                            <strong>
                                Live Chat is currently unavailable.
                            </strong>

                            <div class="mt-2">

                                <?= htmlspecialchars(
                                    $availabilityMessage,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                            <hr>

                            <div class="fw-semibold mb-1">
                                Chat availability
                            </div>

                            <div class="small">

                                Live Chat requires:

                                <ul class="mb-2">

                                    <li>
                                        The office to be within its configured working hours.
                                    </li>

                                    <li>
                                        At least one Admin to be currently online.
                                    </li>

                                </ul>

                                <strong>Configured office hours:</strong>

                                <ul class="mb-0">

                                    <?php foreach ($officeSchedule as $schedule): ?>

                                        <li>
                                            <?= htmlspecialchars(
                                                $schedule,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty($errors ?? [])): ?>

                        <div class="alert alert-danger">

                            <ul class="mb-0">

                                <?php foreach ($errors as $error): ?>

                                    <li>
                                        <?= htmlspecialchars(
                                            $error,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>


                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">
                                Your Name
                            </label>

                            <input
                                type="text"
                                name="guest_name"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $_POST['guest_name'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= $guestChatAvailability['available']
                                    ? ''
                                    : 'disabled'
                                ?>
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Your Email
                            </label>

                            <input
                                type="email"
                                name="guest_email"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $_POST['guest_email'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= $guestChatAvailability['available']
                                    ? ''
                                    : 'disabled'
                                ?>
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Subject
                            </label>

                            <input
                                type="text"
                                name="subject"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $_POST['subject'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= $guestChatAvailability['available']
                                    ? ''
                                    : 'disabled'
                                ?>
                                required>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                            <?= $guestChatAvailability['available']
                                ? ''
                                : 'disabled'
                            ?>>

                            <?= $guestChatAvailability['available']
                                ? 'Start Live Chat'
                                : 'Live Chat Unavailable'
                            ?>

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
