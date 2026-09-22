<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/notifications.php';

$conversationId = (int) ($_GET['id'] ?? 0);

$sessionConversationId = (int) (
    $_SESSION['guest_chat_conversation_id'] ?? 0
);

if (
    $conversationId <= 0
    || $sessionConversationId <= 0
    || $conversationId !== $sessionConversationId
) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Load conversation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM guest_chat_conversations
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$conversationId]);

$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {

    unset($_SESSION['guest_chat_conversation_id']);

    http_response_code(404);
    exit('Conversation not found.');
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

    $messageStmt = $pdo->prepare("
        SELECT
            m.id,
            m.sender_type,
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

    foreach ($messages as &$ajaxMessage) {

        if (!empty($ajaxMessage['attachment_original_name'])) {

            $ajaxMessage['attachment'] = [
                'id' =>
                    (int) $ajaxMessage['attachment_id'],
                'original_name' =>
                    $ajaxMessage['attachment_original_name']
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
        'messages' => $messages
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Guest message + optional attachment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = trim($_POST['message'] ?? '');

    $uploadedFile = $_FILES['attachment'] ?? null;

    $hasUpload =
        is_array($uploadedFile)
        && (
            ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
        );

    /*
     * A message or an attachment is required.
     */
    if (
        $message === ''
        && !$hasUpload
    ) {

        $_SESSION['guest_chat_error'] =
            'Please enter a message or select a file to send.';

        header(
            'Location: ?page=guest-chat-conversation&id='
            . $conversationId
        );

        exit;
    }

    if ($conversation['status'] !== 'Open') {

        $_SESSION['guest_chat_error'] =
            'This conversation has already been closed.';

        header(
            'Location: ?page=guest-chat-conversation&id='
            . $conversationId
        );

        exit;
    }

    /*
     * Attachment validation.
     */
    $attachmentInfo = null;

    if ($hasUpload) {

        if (
            !isset($uploadedFile['tmp_name'])
            || !is_uploaded_file($uploadedFile['tmp_name'])
        ) {

            $_SESSION['guest_chat_error'] =
                'The uploaded file could not be processed.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        if (
            ($uploadedFile['error'] ?? UPLOAD_ERR_OK)
            !== UPLOAD_ERR_OK
        ) {

            $_SESSION['guest_chat_error'] =
                'The file upload failed. Please try again.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        $maxFileSize = 5 * 1024 * 1024;

        if (
            (int) ($uploadedFile['size'] ?? 0)
            > $maxFileSize
        ) {

            $_SESSION['guest_chat_error'] =
                'The attachment must be 5 MB or smaller.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file(
            $uploadedFile['tmp_name']
        );

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf'
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {

            $_SESSION['guest_chat_error'] =
                'Only JPG, PNG, and PDF files are allowed.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        $originalName = basename(
            (string) $uploadedFile['name']
        );

        $originalName = mb_substr(
            $originalName,
            0,
            255
        );

        $extension = $allowedMimeTypes[$mimeType];

        $storedName =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $storageDirectory =
            dirname(__DIR__, 3)
            . '/storage/guest-chat-attachments';

        if (
            !is_dir($storageDirectory)
            && !mkdir(
                $storageDirectory,
                0755,
                true
            )
        ) {

            $_SESSION['guest_chat_error'] =
                'The attachment storage could not be prepared.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        $storagePath =
            $storageDirectory
            . DIRECTORY_SEPARATOR
            . $storedName;

        if (
            !move_uploaded_file(
                $uploadedFile['tmp_name'],
                $storagePath
            )
        ) {

            $_SESSION['guest_chat_error'] =
                'The attachment could not be saved.';

            header(
                'Location: ?page=guest-chat-conversation&id='
                . $conversationId
            );

            exit;
        }

        $attachmentInfo = [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size' => (int) $uploadedFile['size']
        ];
    }

    /*
     * Save the message and attachment together.
     */
    try {

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
                'guest',
                NULL,
                ?,
                UTC_TIMESTAMP()
            )
        ");

        $messageStmt->execute([
            $conversationId,
            $message
        ]);

        $messageId = (int) $pdo->lastInsertId();

        if ($attachmentInfo !== null) {

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
                $attachmentInfo['original_name'],
                $attachmentInfo['stored_name'],
                $attachmentInfo['file_path'],
                $attachmentInfo['mime_type'],
                $attachmentInfo['file_size']
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $attachmentInfo !== null
            && is_file($attachmentInfo['file_path'])
        ) {
            unlink($attachmentInfo['file_path']);
        }

        $_SESSION['guest_chat_error'] =
            'The message could not be saved. Please try again.';

        header(
            'Location: ?page=guest-chat-conversation&id='
            . $conversationId
        );

        exit;
    }

    /*
     * Notify Main Admin.
     */
    $notificationTitle = 'New Guest Chat Message';

    $notificationMessage =
        $conversation['guest_name']
        . ' has sent a new message in the guest chat: '
        . $conversation['subject'];

    if ($attachmentInfo !== null) {

        $notificationMessage .=
            ' An attachment was included.';
    }

    createNotification(
        $pdo,
        'admin',
        null,
        $notificationTitle,
        $notificationMessage,
        '?page=guest-chat-conversation-admin&id='
        . $conversationId
    );

    header(
        'Location: ?page=guest-chat-conversation&id='
        . $conversationId
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Initial messages
|--------------------------------------------------------------------------
*/

$messageStmt = $pdo->prepare("
    SELECT
        m.id,
        m.sender_type,
        m.message,
        m.created_at
    FROM guest_chat_messages m
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC, m.id ASC
");

$messageStmt->execute([$conversationId]);

$messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);

$guestChatError = $_SESSION['guest_chat_error'] ?? '';
unset($_SESSION['guest_chat_error']);

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-8 col-md-10">

            <div class="card shadow-sm">

                <div class="card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h4 class="mb-1">
                                Live Chat
                            </h4>

                            <small class="text-muted">

                                <?= htmlspecialchars(
                                    $conversation['subject'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        </div>

                        <span
                            id="guestChatStatus"
                            class="badge
                            <?= $conversation['status'] === 'Open'
                                ? 'bg-success'
                                : 'bg-secondary'
                            ?>">

                            <?= htmlspecialchars(
                                $conversation['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                </div>


                <?php if ($guestChatError !== ''): ?>

                    <div class="card-body pb-0">

                        <div class="alert alert-danger mb-0">

                            <?= htmlspecialchars(
                                $guestChatError,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>


                <div
                    class="card-body"
                    id="guestChatMessages"
                    style="height:450px;overflow-y:auto;">

                    <?php if (empty($messages)): ?>

                        <div
                            class="text-center text-muted py-5">

                            Waiting for the Admin to join the conversation.

                        </div>

                    <?php else: ?>

                        <?php foreach ($messages as $chatMessage): ?>

                            <?php
                            $isGuest =
                                $chatMessage['sender_type'] === 'guest';
                            ?>

                            <div
                                class="mb-3 d-flex
                                <?= $isGuest
                                    ? 'justify-content-end'
                                    : 'justify-content-start'
                                ?>"
                                data-message-id="<?= (int) $chatMessage['id'] ?>">

                                <div
                                    class="p-3 rounded"
                                    style="
                                        max-width:75%;
                                        <?= $isGuest
                                            ? 'background:#0d6efd;color:white;'
                                            : 'background:#f1f1f1;color:#212529;'
                                        ?>
                                    ">

                                    <div class="fw-semibold mb-1">

                                        <?= $isGuest
                                            ? 'You'
                                            : 'IT Consultancy'
                                        ?>

                                    </div>

                                    <?php

                                    $attachmentStmt = $pdo->prepare("
                                        SELECT
                                            id,
                                            original_name,
                                            mime_type,
                                            file_size
                                        FROM guest_chat_attachments
                                        WHERE message_id = ?
                                        LIMIT 1
                                    ");

                                    $attachmentStmt->execute([
                                        (int) $chatMessage['id']
                                    ]);

                                    $messageAttachment =
                                        $attachmentStmt->fetch(
                                            PDO::FETCH_ASSOC
                                        );

                                    ?>

                                    <?php if ($chatMessage['message'] !== ''): ?>

                                        <div>

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $chatMessage['message'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                    <?php if ($messageAttachment): ?>

                                        <div class="mt-2">

                                            📎

                                            <a
                                                href="?page=guest-chat-attachment&id=<?= (int) $messageAttachment['id'] ?>"
                                                target="_blank"
                                                rel="noopener">

                                                <?= htmlspecialchars(
                                                    $messageAttachment['original_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </a>

                                        </div>

                                    <?php endif; ?>


                                    <small
                                        class="<?= $isGuest
                                            ? 'text-white-50'
                                            : 'text-muted'
                                        ?>">

                                        <?= htmlspecialchars(
                                            $chatMessage['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>


                <div id="guestChatFooter">

                    <?php if ($conversation['status'] === 'Open'): ?>

                        <div class="card-footer">

                            <form
                                method="POST"
                                enctype="multipart/form-data">

                                <div class="mb-2">

                                    <textarea
                                        name="message"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Type your message..."></textarea>

                                </div>

                                <div class="d-flex justify-content-between align-items-center gap-2">

                                    <div>

                                        <input
                                            type="file"
                                            name="attachment"
                                            class="form-control"
                                            accept=".jpg,.jpeg,.png,.pdf">

                                        <div class="form-text">

                                            Optional attachment: JPG, PNG or PDF, maximum 5 MB.

                                        </div>

                                    </div>

                                    <button
                                        type="submit"
                                        class="btn btn-primary">

                                        Send

                                    </button>

                                </div>

                            </form>

                        </div>

                    <?php else: ?>

                        <div class="card-footer">

                            <div class="alert alert-secondary mb-3">

                                This conversation has been closed.

                                The complete transcript has been
                                sent to your email for reference.

                            </div>

                            <a
                                href="?page=home"
                                class="btn btn-primary">

                                Return to Home

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
(function () {

    const conversationId =
        <?= (int) $conversationId ?>;

    const messagesContainer =
        document.getElementById('guestChatMessages');

    const statusBadge =
        document.getElementById('guestChatStatus');

    const footer =
        document.getElementById('guestChatFooter');

    let pollingActive =
        <?= $conversation['status'] === 'Open'
            ? 'true'
            : 'false'
        ?>;

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value === null || value === undefined
                ? ''
                : String(value);

        return div.innerHTML;
    }

    function renderMessages(messages) {

        if (!messagesContainer) {
            return;
        }

        const wasNearBottom =
            messagesContainer.scrollHeight
            - messagesContainer.scrollTop
            - messagesContainer.clientHeight
            < 80;

        if (messages.length === 0) {

            messagesContainer.innerHTML =
                '<div class="text-center text-muted py-5">' +
                'Waiting for the Admin to join the conversation.' +
                '</div>';

            return;
        }

        let html = '';

        messages.forEach(function (chatMessage) {

            const isGuest =
                chatMessage.sender_type === 'guest';

            const alignment =
                isGuest
                    ? 'justify-content-end'
                    : 'justify-content-start';

            const bubbleStyle =
                isGuest
                    ? 'background:#0d6efd;color:white;'
                    : 'background:#f1f1f1;color:#212529;';

            const senderName =
                isGuest
                    ? 'You'
                    : 'IT Consultancy';

            const timeClass =
                isGuest
                    ? 'text-white-50'
                    : 'text-muted';

            const safeMessage =
                escapeHtml(chatMessage.message)
                    .replace(/\n/g, '<br>');

            const safeTime =
                escapeHtml(chatMessage.created_at);

            const attachmentHtml =
                chatMessage.attachment
                    ? '<div class="mt-2">📎 ' +
                      '<a href="?page=guest-chat-attachment&id=' +
                      encodeURIComponent(chatMessage.attachment.id) +
                      '" target="_blank" rel="noopener">' +
                      escapeHtml(chatMessage.attachment.original_name) +
                      '</a></div>'
                    : '';

            const messageHtml =
                safeMessage !== ''
                    ? '<div>' + safeMessage + '</div>'
                    : '';

            html +=
                '<div class="mb-3 d-flex ' +
                alignment +
                '" data-message-id="' +
                parseInt(chatMessage.id, 10) +
                '">' +

                    '<div class="p-3 rounded" ' +
                    'style="max-width:75%;' +
                    bubbleStyle +
                    '">' +

                        '<div class="fw-semibold mb-1">' +
                            senderName +
                        '</div>' +

                        messageHtml +

                        attachmentHtml +

                        '<small class="' +
                            timeClass +
                        '">' +
                            safeTime +
                        '</small>' +

                    '</div>' +

                '</div>';
        });

        messagesContainer.innerHTML = html;

        if (wasNearBottom) {

            messagesContainer.scrollTop =
                messagesContainer.scrollHeight;
        }
    }

    function showClosedState() {

        pollingActive = false;

        if (statusBadge) {

            statusBadge.textContent =
                'Closed';

            statusBadge.classList.remove(
                'bg-success'
            );

            statusBadge.classList.add(
                'bg-secondary'
            );
        }

        if (footer) {

            footer.innerHTML =
                '<div class="card-footer">' +
                    '<div class="alert alert-secondary mb-3">' +
                        'This conversation has been closed. ' +
                        'The complete transcript has been sent ' +
                        'to your email for reference.' +
                    '</div>' +
                    '<a href="?page=home" ' +
                    'class="btn btn-primary">' +
                        'Return to Home' +
                    '</a>' +
                '</div>';
        }
    }

    function pollMessages() {

        if (!pollingActive) {
            return;
        }

        fetch(
            '?page=guest-chat-conversation' +
            '&id=' +
            encodeURIComponent(conversationId) +
            '&ajax=1',
            {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            }
        )
        .then(function (response) {

            if (!response.ok) {
                throw new Error(
                    'Chat polling request failed.'
                );
            }

            return response.json();
        })
        .then(function (data) {

            if (!data.success) {
                return;
            }

            renderMessages(
                Array.isArray(data.messages)
                    ? data.messages
                    : []
            );

            if (data.status === 'Closed') {
                showClosedState();
            }

        })
        .catch(function () {
            /*
             * Temporary polling failures do not interrupt
             * the guest conversation.
             */
        });
    }

    if (messagesContainer) {

        messagesContainer.scrollTop =
            messagesContainer.scrollHeight;
    }

    if (pollingActive) {

        setInterval(
            pollMessages,
            3000
        );
    }

})();
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
