<?php

require_once HELPER_PATH . '/auth.php';
require_once APP_PATH . '/helpers/DateHelper.php';
require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/email.php';

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

/*
 * Main Admin session stores the Admin email.
 * Resolve it to the actual users.id for message ownership.
 */
$adminEmail = trim((string) $_SESSION['user']);

if ($adminEmail === '') {
    header('Location: ?page=login');
    exit;
}

$adminStmt = $pdo->prepare("
    SELECT id, email
    FROM users
    WHERE email = ?
    LIMIT 1
");

$adminStmt->execute([$adminEmail]);

$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Admin account could not be identified.');
}

$adminId = (int) $admin['id'];

$conversationId = (int) ($_GET['id'] ?? 0);

if ($conversationId <= 0) {
    header('Location: ?page=guest-chats');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load conversation
|--------------------------------------------------------------------------
*/

$conversationStmt = $pdo->prepare("
    SELECT *
    FROM guest_chat_conversations
    WHERE id = ?
    LIMIT 1
");

$conversationStmt->execute([$conversationId]);

$conversation = $conversationStmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die('Guest chat conversation not found.');
}

/*
|--------------------------------------------------------------------------
| AJAX polling
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['ajax'])
    && $_GET['ajax'] === '1'
) {

    $ajaxMessageStmt = $pdo->prepare("
        SELECT
            m.id,
            m.sender_type,
            m.sender_id,
            m.message,
            m.created_at,
            a.id AS attachment_id,
            a.original_name AS attachment_original_name
        FROM guest_chat_messages m
        LEFT JOIN guest_chat_attachments a
            ON a.message_id = m.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC, m.id ASC
    ");

    $ajaxMessageStmt->execute([$conversationId]);
    $ajaxMessages = $ajaxMessageStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ajaxMessages as &$ajaxMessage) {

        if (!empty($ajaxMessage['attachment_id'])) {
            $ajaxMessage['attachment'] = [
                'id' => (int) $ajaxMessage['attachment_id'],
                'original_name' => $ajaxMessage['attachment_original_name']
            ];
        } else {
            $ajaxMessage['attachment'] = null;
        }

        unset(
            $ajaxMessage['attachment_id'],
            $ajaxMessage['attachment_original_name']
        );
    }
    unset($ajaxMessage);

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo json_encode([
        'success' => true,
        'status' => $conversation['status'],
        'ended_at' => $conversation['ended_at'],
        'messages' => $ajaxMessages
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Admin actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
     * ---------------------------------------------------------------
     * Send Admin reply
     * ---------------------------------------------------------------
     */

    if (
        $action === 'send_message' &&
        $conversation['status'] === 'Open'
    ) {

        $message = trim($_POST['message'] ?? '');
        $hasAttachment =
            isset($_FILES['attachment'])
            && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE;

        if ($message === '') {
            $_SESSION['error'] = 'Please enter a message before sending.';

            header(
                'Location: ?page=guest-chat-conversation-admin&id='
                . $conversationId
            );

            exit;
        }

        $storedFilePath = null;
        $originalName = null;
        $mimeType = null;
        $fileSize = null;
        $storedName = null;

        try {

            /*
             * Validate the optional Admin attachment before opening
             * the database transaction.
             */
            if ($hasAttachment) {

                $file = $_FILES['attachment'];

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The attachment could not be uploaded.');
                }

                $maxFileSize = 5 * 1024 * 1024;

                if ((int) ($file['size'] ?? 0) <= 0) {
                    throw new RuntimeException('The attachment is empty.');
                }

                if ((int) $file['size'] > $maxFileSize) {
                    throw new RuntimeException('The attachment must not exceed 5 MB.');
                }

                if (!is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException('Invalid attachment upload.');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($file['tmp_name']);

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'application/pdf' => 'pdf'
                ];

                if (!isset($allowedTypes[$detectedMime])) {
                    throw new RuntimeException(
                        'Only JPG, PNG, and PDF attachments are allowed.'
                    );
                }

                $storageDirectory =
                    dirname(__DIR__, 3)
                    . '/storage/guest-chat-attachments';

                if (!is_dir($storageDirectory)) {
                    if (!mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
                        throw new RuntimeException(
                            'The attachment storage directory could not be created.'
                        );
                    }
                }

                $storedName =
                    bin2hex(random_bytes(16))
                    . '.'
                    . $allowedTypes[$detectedMime];

                $storedFilePath =
                    $storageDirectory
                    . DIRECTORY_SEPARATOR
                    . $storedName;

                if (!move_uploaded_file($file['tmp_name'], $storedFilePath)) {
                    throw new RuntimeException(
                        'The attachment could not be saved.'
                    );
                }

                $originalName = basename(
                    (string) ($file['name'] ?? 'attachment')
                );

                $mimeType = $detectedMime;
                $fileSize = (int) $file['size'];
            }

            $pdo->beginTransaction();

            $messageStmt = $pdo->prepare("
                INSERT INTO guest_chat_messages
                (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message,
                    created_at
                )
                VALUES
                (
                    ?,
                    'admin',
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )
            ");

            $messageStmt->execute([
                $conversationId,
                $adminId,
                $message
            ]);

            $messageId = (int) $pdo->lastInsertId();

            if ($storedFilePath !== null) {

                $attachmentStmt = $pdo->prepare("
                    INSERT INTO guest_chat_attachments
                    (
                        message_id,
                        original_name,
                        stored_name,
                        file_path,
                        mime_type,
                        file_size,
                        uploaded_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        UTC_TIMESTAMP()
                    )
                ");

                $attachmentStmt->execute([
                    $messageId,
                    $originalName,
                    $storedName,
                    $storedFilePath,
                    $mimeType,
                    $fileSize
                ]);
            }

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($storedFilePath !== null && file_exists($storedFilePath)) {
                @unlink($storedFilePath);
            }

            $_SESSION['error'] = $e->getMessage();
        }

        header(
            'Location: ?page=guest-chat-conversation-admin&id='
            . $conversationId
        );

        exit;
    }

    /*
     * ---------------------------------------------------------------
     * Close Guest Chat
     * ---------------------------------------------------------------
     */

    if (
        $action === 'close_chat' &&
        $conversation['status'] === 'Open'
    ) {

        /*
         * Close the conversation first.
         *
         * The WHERE status = 'Open' condition prevents the same
         * conversation from being closed twice.
         */
        $closeStmt = $pdo->prepare("
            UPDATE guest_chat_conversations
            SET
                status = 'Closed',
                ended_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = ?
              AND status = 'Open'
        ");

        $closeStmt->execute([$conversationId]);

        /*
         * Reload the conversation so the email contains the
         * actual recorded end time.
         */
        $conversationStmt = $pdo->prepare("
            SELECT *
            FROM guest_chat_conversations
            WHERE id = ?
            LIMIT 1
        ");

        $conversationStmt->execute([$conversationId]);

        $conversation = $conversationStmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Load the complete conversation history.
         */
        $transcriptStmt = $pdo->prepare("
            SELECT
                m.sender_type,
                m.message,
                m.created_at,
                a.original_name AS attachment_original_name,
                a.file_path AS attachment_file_path
            FROM guest_chat_messages m
            LEFT JOIN guest_chat_attachments a
                ON a.message_id = m.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC, m.id ASC
        ");

        $transcriptStmt->execute([$conversationId]);

        $transcriptMessages = $transcriptStmt->fetchAll(PDO::FETCH_ASSOC);

        /*
         * Build the email transcript.
         */
        $emailRows = '';
        $adminEmailAttachments = [];

        $storageRoot = realpath(
            dirname(__DIR__, 3)
            . '/storage/guest-chat-attachments'
        );

        foreach ($transcriptMessages as $transcriptMessage) {

            $senderName =
                $transcriptMessage['sender_type'] === 'admin'
                    ? 'Support Admin'
                    : $conversation['guest_name'];

            $senderNameHtml = htmlspecialchars(
                $senderName,
                ENT_QUOTES,
                'UTF-8'
            );

            $messageHtml = nl2br(
                htmlspecialchars(
                    $transcriptMessage['message'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );

            $dateHtml = htmlspecialchars(
                formatDateTime($transcriptMessage['created_at']),
                ENT_QUOTES,
                'UTF-8'
            );

            $attachmentHtml = '';

            if (!empty($transcriptMessage['attachment_original_name'])) {

                $attachmentName = htmlspecialchars(
                    $transcriptMessage['attachment_original_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $attachmentHtml = '
                    <div style="
                        margin-top:10px;
                        padding-top:8px;
                        border-top:1px solid #dee2e6;
                        font-size:13px;
                    ">
                        <strong>Attachment:</strong> ' . $attachmentName . '
                    </div>
                ';

                if ($transcriptMessage['sender_type'] === 'admin') {

                    $realAttachmentPath = realpath(
                        (string) $transcriptMessage['attachment_file_path']
                    );

                    if (
                        $storageRoot !== false
                        && $realAttachmentPath !== false
                        && strpos(
                            $realAttachmentPath,
                            $storageRoot . DIRECTORY_SEPARATOR
                        ) === 0
                        && is_file($realAttachmentPath)
                    ) {
                        $adminEmailAttachments[] = $realAttachmentPath;
                    }
                }
            }

            $emailRows .= '
                <div style="
                    margin-bottom:20px;
                    padding:15px;
                    border:1px solid #dee2e6;
                    border-radius:8px;
                    background:#f8f9fa;
                ">
                    <div style="
                        font-weight:600;
                        margin-bottom:6px;
                    ">
                        ' . $senderNameHtml . '
                    </div>

                    <div style="
                        margin-bottom:8px;
                    ">
                        ' . $messageHtml . '
                    </div>

                    <div style="
                        color:#6c757d;
                        font-size:12px;
                    ">
                        ' . $dateHtml . '
                    </div>

                    ' . $attachmentHtml . '
                </div>
            ';
        }

        $guestNameHtml = htmlspecialchars(
            $conversation['guest_name'],
            ENT_QUOTES,
            'UTF-8'
        );

        $subjectHtml = htmlspecialchars(
            $conversation['subject'],
            ENT_QUOTES,
            'UTF-8'
        );

        $startedAtHtml = htmlspecialchars(
            formatDateTime($conversation['started_at']),
            ENT_QUOTES,
            'UTF-8'
        );

        $endedAtHtml = htmlspecialchars(
            formatDateTime($conversation['ended_at']),
            ENT_QUOTES,
            'UTF-8'
        );

        $emailSubject =
            'Guest Chat Transcript - '
            . $conversation['subject'];

        $emailBody = '
            <div style="
                font-family:Arial,Helvetica,sans-serif;
                line-height:1.6;
                color:#212529;
            ">

                <h2 style="margin-bottom:20px;">
                    Guest Chat Transcript
                </h2>

                <p>
                    Hello ' . $guestNameHtml . ',
                </p>

                <p>
                    Thank you for chatting with our support team.
                    This email contains a copy of your completed
                    guest chat for your reference.
                </p>

                <table
                    cellpadding="6"
                    cellspacing="0"
                    border="0"
                    style="margin-bottom:20px;"
                >
                    <tr>
                        <td>
                            <strong>Subject:</strong>
                        </td>
                        <td>
                            ' . $subjectHtml . '
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <strong>Started:</strong>
                        </td>
                        <td>
                            ' . $startedAtHtml . '
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <strong>Ended:</strong>
                        </td>
                        <td>
                            ' . $endedAtHtml . '
                        </td>
                    </tr>
                </table>

                <hr style="
                    border:0;
                    border-top:1px solid #dee2e6;
                    margin:20px 0;
                ">

                <h3 style="margin-bottom:15px;">
                    Conversation
                </h3>

                ' . $emailRows . '

                <hr style="
                    border:0;
                    border-top:1px solid #dee2e6;
                    margin:25px 0;
                ">

                <p style="
                    color:#6c757d;
                    font-size:13px;
                ">
                    This email is provided as a reference copy
                    of your completed guest chat.
                </p>

            </div>
        ';

        /*
         * Send the transcript to the guest.
         *
         * Failure to send the email does NOT reopen the chat.
         * The conversation remains permanently closed.
         */
        try {

            sendEmail(
                $conversation['guest_email'],
                $emailSubject,
                $emailBody,
                $adminEmailAttachments
            );

        } catch (Throwable $e) {

            /*
             * The chat is already safely closed.
             * We intentionally do not reopen it if email delivery
             * encounters an error.
             */
        }

        header('Location: ?page=guest-chats');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Load messages
|--------------------------------------------------------------------------
*/

$messageStmt = $pdo->prepare("
    SELECT
        m.id,
        m.sender_type,
        m.sender_id,
        m.message,
        m.created_at,
        a.id AS attachment_id,
        a.original_name AS attachment_original_name
    FROM guest_chat_messages m
    LEFT JOIN guest_chat_attachments a
        ON a.message_id = m.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC, m.id ASC
");

$messageStmt->execute([$conversationId]);

$messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as &$chatMessage) {

    if (!empty($chatMessage['attachment_id'])) {
        $chatMessage['attachment'] = [
            'id' => (int) $chatMessage['attachment_id'],
            'original_name' => $chatMessage['attachment_original_name']
        ];
    } else {
        $chatMessage['attachment'] = null;
    }

    unset(
        $chatMessage['attachment_id'],
        $chatMessage['attachment_original_name']
    );
}
unset($chatMessage);

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Guest Chat
            </h2>

            <div class="text-muted">

                <?= htmlspecialchars(
                    $conversation['subject'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        </div>

        <a
            href="?page=guest-chats"
            class="btn btn-secondary">

            Back to Guest Chats

        </a>

    </div>


    <div class="card shadow-sm mb-3">

        <div class="card-body">

            <div class="row">

                <div class="col-md-3">

                    <strong>Customer Name</strong>

                    <div>

                        <?= htmlspecialchars(
                            $conversation['guest_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="col-md-3">

                    <strong>Email</strong>

                    <div>

                        <?= htmlspecialchars(
                            $conversation['guest_email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <div class="col-md-3">

                    <strong>Status</strong>

                    <div>

                        <?php if ($conversation['status'] === 'Open'): ?>

                            <span class="badge bg-success">
                                Open
                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">
                                Closed
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="col-md-3">

                    <strong>Started</strong>

                    <div>

                        <?= formatDateTime(
                            $conversation['started_at']
                        ) ?>

                    </div>

                    <?php if (!empty($conversation['ended_at'])): ?>

                        <div class="mt-1">

                            <strong>Ended:</strong>

                            <?= formatDateTime(
                                $conversation['ended_at']
                            ) ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <div class="card shadow-sm">

        <div class="card-header">

            <strong>
                Conversation
            </strong>

        </div>


        <div
            class="card-body"
            style="max-height:500px;overflow-y:auto;">

            <?php if (empty($messages)): ?>

                <p class="text-muted mb-0">
                    No messages yet.
                </p>

            <?php else: ?>

                <?php foreach ($messages as $chatMessage): ?>

                    <?php

                    $isAdmin =
                        $chatMessage['sender_type'] === 'admin';

                    ?>

                    <div
                        class="mb-3 d-flex
                        <?= $isAdmin
                            ? 'justify-content-end'
                            : 'justify-content-start'
                        ?>"
                        data-message-id="<?= (int) $chatMessage['id'] ?>">

                        <div
                            class="p-3 rounded"
                            style="
                                max-width:75%;
                                <?= $isAdmin
                                    ? 'background:#0d6efd;color:white;'
                                    : 'background:#f1f1f1;color:#212529;'
                                ?>
                            ">

                            <div class="fw-semibold mb-1">

                                <?= $isAdmin
                                    ? 'You'
                                    : htmlspecialchars(
                                        $conversation['guest_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ?>

                            </div>

                            <div>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $chatMessage['message'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>

                            </div>

                            <?php if (!empty($chatMessage['attachment'])): ?>

                                <div class="mt-2">

                                    📎

                                    <a
                                        href="?page=guest-chat-attachment&id=<?= (int) $chatMessage['attachment']['id'] ?>"
                                        target="_blank"
                                        rel="noopener"
                                        class="<?= $isAdmin ? 'text-white' : '' ?>">

                                        <?= htmlspecialchars(
                                            $chatMessage['attachment']['original_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </a>

                                </div>

                            <?php endif; ?>

                            <small
                                class="<?= $isAdmin
                                    ? 'text-white-50'
                                    : 'text-muted'
                                ?>">

                                <?= formatDateTime(
                                    $chatMessage['created_at']
                                ) ?>

                            </small>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <?php if ($conversation['status'] === 'Open'): ?>

            <div class="card-footer">

                <form method="POST" enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="action"
                        value="send_message">

                    <div class="mb-3">

                        <textarea
                            name="message"
                            class="form-control"
                            rows="3"
                            placeholder="Type your reply..."
                            required></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Attachment (optional)
                        </label>

                        <input
                            type="file"
                            name="attachment"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.pdf">

                        <div class="form-text">
                            JPG, PNG, or PDF. Maximum 5 MB.
                        </div>

                    </div>

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary">

                            Send Reply

                        </button>

                    </div>

                </form>


                <form
                    method="POST"
                    class="mt-3"
                    onsubmit="
                        return confirm(
                            'Are you sure you want to close this guest chat? The complete conversation will be emailed to the guest.'
                        );
                    ">

                    <input
                        type="hidden"
                        name="action"
                        value="close_chat">

                    <button
                        type="submit"
                        class="btn btn-danger">

                        Close Chat

                    </button>

                </form>

            </div>

        <?php else: ?>

            <div class="card-footer">

                <div class="alert alert-secondary mb-0">

                    This conversation has been closed.

                    The conversation transcript was sent
                    to the guest as a reference.

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>
(function () {

    const conversationId = <?= (int) $conversationId ?>;
    const messagesContainer = document.querySelector('.card-body[style*="max-height:500px"]');
    const statusBadge = messagesContainer?.previousElementSibling?.querySelector('.badge');
    const replyForm = document.querySelector('form input[name="action"][value="send_message"]')?.closest('form');
    const closeForm = document.querySelector('form input[name="action"][value="close_chat"]')?.closest('form');

    let pollingActive = <?= $conversation['status'] === 'Open' ? 'true' : 'false' ?>;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function renderMessages(messages) {
        if (!messagesContainer) return;

        const wasNearBottom =
            messagesContainer.scrollHeight -
            messagesContainer.scrollTop -
            messagesContainer.clientHeight < 80;

        if (messages.length === 0) {
            messagesContainer.innerHTML = '<p class="text-muted mb-0">No messages yet.</p>';
            return;
        }

        let html = '';
        const guestName = <?= json_encode($conversation['guest_name']) ?>;

        messages.forEach(function (chatMessage) {
            const isAdmin = chatMessage.sender_type === 'admin';
            const alignment = isAdmin ? 'justify-content-end' : 'justify-content-start';
            const bubbleStyle = isAdmin
                ? 'background:#0d6efd;color:white;'
                : 'background:#f1f1f1;color:#212529;';
            const senderName = isAdmin ? 'You' : guestName;
            const timeClass = isAdmin ? 'text-white-50' : 'text-muted';
            const safeMessage = escapeHtml(chatMessage.message).replace(/\n/g, '<br>');
            const safeTime = escapeHtml(chatMessage.created_at);
            const attachmentHtml = chatMessage.attachment
                ? '<div class="mt-2">📎 ' +
                  '<a href="?page=guest-chat-attachment&id=' +
                  encodeURIComponent(chatMessage.attachment.id) +
                  '" target="_blank" rel="noopener"' +
                  (isAdmin ? ' class="text-white"' : '') +
                  '>' +
                  escapeHtml(chatMessage.attachment.original_name) +
                  '</a></div>'
                : '';

            html +=
                '<div class="mb-3 d-flex ' + alignment + '" data-message-id="' +
                parseInt(chatMessage.id, 10) + '">' +
                    '<div class="p-3 rounded" style="max-width:75%;' + bubbleStyle + '">' +
                        '<div class="fw-semibold mb-1">' + escapeHtml(senderName) + '</div>' +
                        '<div>' + safeMessage + '</div>' +
                        attachmentHtml +
                        '<small class="' + timeClass + '">' + safeTime + '</small>' +
                    '</div>' +
                '</div>';
        });

        messagesContainer.innerHTML = html;

        if (wasNearBottom) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    function updateStatus(status) {
        if (statusBadge) {
            statusBadge.textContent = status;
            statusBadge.classList.toggle('bg-success', status === 'Open');
            statusBadge.classList.toggle('bg-secondary', status !== 'Open');
        }

        if (status === 'Closed') {
            pollingActive = false;
            if (replyForm) replyForm.remove();
            if (closeForm) closeForm.remove();
        }
    }

    function pollMessages() {
        if (!pollingActive) return;

        fetch(
            '?page=guest-chat-conversation-admin' +
            '&id=' + encodeURIComponent(conversationId) +
            '&ajax=1',
            {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            }
        )
        .then(function (response) {
            if (!response.ok) throw new Error('Chat polling request failed.');
            return response.json();
        })
        .then(function (data) {
            if (!data.success) return;
            renderMessages(Array.isArray(data.messages) ? data.messages : []);
            updateStatus(data.status);
        })
        .catch(function () {});
    }

    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    if (pollingActive) {
        setInterval(pollMessages, 3000);
    }

})();
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>