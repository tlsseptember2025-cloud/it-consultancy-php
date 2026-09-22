<?php

/*
|--------------------------------------------------------------------------
| Guest Chat Attachment
|--------------------------------------------------------------------------
|
| Securely serves attachments belonging to Guest Chat messages.
|
| Access is allowed only when:
|
| 1. The visitor owns the active guest-chat conversation in session, OR
| 2. The Main Admin is authenticated.
|
| Demo accounts and other customer sessions are not permitted.
|
*/

require_once CONFIG_PATH . '/database.php';

$attachmentId = (int) ($_GET['id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment.');
}

/*
|--------------------------------------------------------------------------
| Load attachment and its conversation
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.original_name,
        a.stored_name,
        a.file_path,
        a.mime_type,
        a.file_size,
        m.id AS message_id,
        m.sender_type,
        c.id AS conversation_id
    FROM guest_chat_attachments a
    INNER JOIN guest_chat_messages m
        ON m.id = a.message_id
    INNER JOIN guest_chat_conversations c
        ON c.id = m.conversation_id
    WHERE a.id = ?
    LIMIT 1
");

$stmt->execute([$attachmentId]);

$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

/*
|--------------------------------------------------------------------------
| Authorize Main Admin
|--------------------------------------------------------------------------
*/

$isMainAdmin = isset($_SESSION['user']);

/*
|--------------------------------------------------------------------------
| Authorize Guest
|--------------------------------------------------------------------------
*/

$sessionConversationId = (int) (
    $_SESSION['guest_chat_conversation_id'] ?? 0
);

$isAuthorizedGuest =
    $sessionConversationId > 0
    && $sessionConversationId ===
        (int) $attachment['conversation_id'];

/*
|--------------------------------------------------------------------------
| Only Main Admin or the owning Guest may view it.
|--------------------------------------------------------------------------
*/

if (!$isMainAdmin && !$isAuthorizedGuest) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Resolve stored file safely
|--------------------------------------------------------------------------
*/

$storageRoot = realpath(
    dirname(__DIR__, 2)
    . '/storage/guest-chat-attachments'
);

$filePath = realpath(
    (string) $attachment['file_path']
);

if (
    $storageRoot === false
    || $filePath === false
    || !is_file($filePath)
) {
    http_response_code(404);
    exit('Attachment file not found.');
}

/*
|--------------------------------------------------------------------------
| Prevent path traversal.
|--------------------------------------------------------------------------
*/

$storageRootWithSeparator =
    rtrim($storageRoot, DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR;

if (
    strpos(
        $filePath,
        $storageRootWithSeparator
    ) !== 0
) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Serve file
|--------------------------------------------------------------------------
*/

$mimeType =
    trim((string) $attachment['mime_type']);

if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

$downloadName =
    basename((string) $attachment['original_name']);

$downloadName =
    str_replace(
        ["\r", "\n", '"'],
        '',
        $downloadName
    );

if ($downloadName === '') {
    $downloadName = 'guest-chat-attachment';
}

header(
    'Content-Type: ' . $mimeType
);

header(
    'Content-Length: ' . (string) filesize($filePath)
);

header(
    'Content-Disposition: inline; filename="' .
    $downloadName .
    '"'
);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

readfile($filePath);
exit;
