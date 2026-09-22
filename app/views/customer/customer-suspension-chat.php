<?php

/**
 * Customer Suspension Communication
 *
 * Separate authenticated Customer <-> Admin chat
 * for suspended customers.
 *
 * Works in:
 * - Main environment
 * - Demo environment
 *
 * This system is completely separate from the existing
 * public visitor messaging system.
 */

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Detect Customer Environment
|--------------------------------------------------------------------------
*/

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $customerId = (int) $_SESSION['demo_customer']['id'];

    /*
     * Always read the current customer record from Demo DB.
     * Do not rely only on the session status.
     */
    $customerStmt = $demoPdo->prepare("
        SELECT
            id,
            username,
            name,
            email,
            phone,
            company,
            status,
            demo_tenant_id,
            is_demo_account
        FROM customers
        WHERE id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    /*
     * Demo customer must have a valid tenant.
     */
    if (
        empty($customer['demo_tenant_id']) ||
        (int) $customer['demo_tenant_id'] !==
        (int) ($_SESSION['demo_customer']['demo_tenant_id'] ?? 0)
    ) {
        http_response_code(403);
        exit('403 - Access denied');
    }

    $customerPdo = $demoPdo;

    $customerDisplayName = $customer['username'];

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $customerId = (int) $_SESSION['customer']['id'];

    $customerStmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            company,
            status
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        unset($_SESSION['customer']);

        header('Location: ?page=public-login');
        exit;
    }

    $customerPdo = $pdo;

    $customerDisplayName = $customer['name'];
}

/*
|--------------------------------------------------------------------------
| Customer Must Actually Be Suspended
|--------------------------------------------------------------------------
*/

if (($customer['status'] ?? 'Active') !== 'Suspended') {

    header('Location: ?page=customer-dashboard');
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Active Suspension Reasons
|--------------------------------------------------------------------------
*/

$suspensionStmt = $customerPdo->prepare("
    SELECT
        id,
        reason,
        created_at
    FROM customer_suspensions
    WHERE customer_id = ?
      AND active = 1
    ORDER BY id ASC
");

$suspensionStmt->execute([$customerId]);

$activeSuspensions = $suspensionStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Find or Create Suspension Conversation
|--------------------------------------------------------------------------
*/

$conversationStmt = $customerPdo->prepare("
    SELECT
        id,
        customer_id,
        status,
        created_at,
        updated_at
    FROM customer_suspension_conversations
    WHERE customer_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$conversationStmt->execute([$customerId]);

$conversation = $conversationStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Create Conversation If It Does Not Exist
|--------------------------------------------------------------------------
*/

if (!$conversation) {

    $createConversation = $customerPdo->prepare("
        INSERT INTO customer_suspension_conversations
            (customer_id, status)
        VALUES
            (?, 'Open')
    ");

    $createConversation->execute([$customerId]);

    $conversationId = (int) $customerPdo->lastInsertId();

    $conversation = [
        'id' => $conversationId,
        'customer_id' => $customerId,
        'status' => 'Open'
    ];

} else {

    $conversationId = (int) $conversation['id'];
}

/*
|--------------------------------------------------------------------------
| Handle Customer Message
|--------------------------------------------------------------------------
*/

$messageError = null;
$messageSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $messageText = trim($_POST['message'] ?? '');

    /*
     * Attachment information.
     */
    $attachment = $_FILES['attachment'] ?? null;

    $hasAttachment = (
        $attachment &&
        isset($attachment['error']) &&
        $attachment['error'] !== UPLOAD_ERR_NO_FILE
    );

    /*
     * Require either a message or attachment.
     */
    if ($messageText === '' && !$hasAttachment) {

        $messageError = 'Please enter a message or attach a file.';

    } elseif (
        $hasAttachment &&
        $attachment['error'] !== UPLOAD_ERR_OK
    ) {

        $messageError = 'The attachment could not be uploaded. Please try again.';

    } else {

        /*
         * Attachment validation.
         */
        $attachmentData = null;

        if ($hasAttachment) {

            $maxFileSize = 5 * 1024 * 1024; // 5 MB

            if ((int) $attachment['size'] > $maxFileSize) {

                $messageError = 'The attachment is too large. Maximum size is 5 MB.';

            } else {

                $allowedMimeTypes = [
                    'image/jpeg'      => 'jpg',
                    'image/png'       => 'png',
                    'application/pdf' => 'pdf',
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $detectedMimeType = $finfo->file(
                    $attachment['tmp_name']
                );

                if (!isset($allowedMimeTypes[$detectedMimeType])) {

                    $messageError =
                        'Invalid attachment type. Please upload a JPG, PNG, or PDF file.';

                } else {

                    $safeExtension =
                        $allowedMimeTypes[$detectedMimeType];

                    $originalName =
                        basename($attachment['name']);

                    /*
                     * Prevent excessively long original filenames.
                     */
                    $originalName = mb_substr(
                        $originalName,
                        0,
                        255
                    );

                    $storedName =
                        'suspension_' .
                        $customerId .
                        '_' .
                        bin2hex(random_bytes(16)) .
                        '.' .
                        $safeExtension;

                    /*
                     * Store outside the directly browsable public
                     * customer area.
                     *
                     * This path will be protected by the server
                     * configuration when downloads are implemented.
                     */
                    $uploadDirectory =
                        dirname(__DIR__, 3) .
                        '/storage/suspension-attachments';

                    if (!is_dir($uploadDirectory)) {

                        if (!mkdir($uploadDirectory, 0750, true)) {

                            $messageError =
                                'The attachment folder could not be created.';
                        }
                    }

                    if ($messageError === null) {

                        $storedPath =
                            $uploadDirectory .
                            DIRECTORY_SEPARATOR .
                            $storedName;

                        if (
                            !move_uploaded_file(
                                $attachment['tmp_name'],
                                $storedPath
                            )
                        ) {

                            $messageError =
                                'The attachment could not be saved. Please try again.';

                        } else {

                            $attachmentData = [
                                'original_name' => $originalName,
                                'stored_name'   => $storedName,
                                'file_path'     =>
                                    'storage/suspension-attachments/' .
                                    $storedName,
                                'mime_type'     => $detectedMimeType,
                                'file_size'     =>
                                    (int) $attachment['size'],
                            ];
                        }
                    }
                }
            }
        }

        /*
         * Save message and attachment together.
         */
        if ($messageError === null) {

            try {

                $customerPdo->beginTransaction();

                /*
                 * Re-check that the conversation belongs to
                 * the currently authenticated customer.
                 */
                $conversationCheck = $customerPdo->prepare("
                    SELECT id
                    FROM customer_suspension_conversations
                    WHERE id = ?
                      AND customer_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $conversationCheck->execute([
                    $conversationId,
                    $customerId
                ]);

                if (!$conversationCheck->fetch(PDO::FETCH_ASSOC)) {

                    throw new RuntimeException(
                        'Invalid suspension conversation.'
                    );
                }

                /*
                 * Add the customer message.
                 *
                 * message may be empty when the customer sends
                 * an attachment without text.
                 */
                $insertMessage = $customerPdo->prepare("
                    INSERT INTO customer_suspension_messages
                        (
                            conversation_id,
                            sender_type,
                            sender_id,
                            message
                        )
                    VALUES
                        (?, 'customer', ?, ?)
                ");

                $insertMessage->execute([
                    $conversationId,
                    $customerId,
                    $messageText !== '' ? $messageText : null
                ]);

                $messageId = (int) $customerPdo->lastInsertId();

                /*
                 * Save attachment metadata.
                 */
                if ($attachmentData !== null) {

                    $insertAttachment = $customerPdo->prepare("
                        INSERT INTO customer_suspension_attachments
                            (
                                message_id,
                                original_name,
                                stored_name,
                                file_path,
                                mime_type,
                                file_size
                            )
                        VALUES
                            (?, ?, ?, ?, ?, ?)
                    ");

                    $insertAttachment->execute([
                        $messageId,
                        $attachmentData['original_name'],
                        $attachmentData['stored_name'],
                        $attachmentData['file_path'],
                        $attachmentData['mime_type'],
                        $attachmentData['file_size']
                    ]);
                }

                /*
                 * Make sure the conversation remains open when
                 * the customer sends a new message.
                 */
                $updateConversation = $customerPdo->prepare("
                    UPDATE customer_suspension_conversations
                    SET
                        status = 'Open',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND customer_id = ?
                ");

                $updateConversation->execute([
                    $conversationId,
                    $customerId
                ]);

                /* 
 * ------------------------------------------------------------
 * Notify Admin
 * ------------------------------------------------------------
 *
 * The suspension conversation is a separate communication
 * system. Notify the appropriate Admin when the customer
 * sends a new message or attachment.
 *
 */

$notificationTitle =
    'Suspended Customer Message';

$notificationMessage =
    $customerDisplayName .
    ' has sent a new message in the suspension communication area.';

$notificationLink =
    '?page=admin-suspension-chat&id=' .
    $customerId;


/*
 * Main Admin / Demo Admin notifications
 *
 * The existing notification system displays Admin
 * notifications without requiring a new table.
 *
 */

$notificationStmt = $customerPdo->prepare("
    INSERT INTO notifications
    (
        recipient_type,
        recipient_id,
        title,
        message,
        link
    )
    VALUES
    (
        'admin',
        NULL,
        ?,
        ?,
        ?
    )
");

$notificationStmt->execute([
    $notificationTitle,
    $notificationMessage,
    $notificationLink
]);

                $customerPdo->commit();

                header(
                    'Location: ?page=customer-suspension-chat'
                );
                exit;

            } catch (Throwable $e) {

                if ($customerPdo->inTransaction()) {
                    $customerPdo->rollBack();
                }

                /*
                 * If the database operation failed after the file
                 * was physically stored, remove the orphan file.
                 */
                if (
                    $attachmentData !== null &&
                    isset($storedPath) &&
                    is_file($storedPath)
                ) {
                    @unlink($storedPath);
                }

                $messageError =
                    'Your message could not be sent. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Conversation Messages
|--------------------------------------------------------------------------
*/

$messagesStmt = $customerPdo->prepare("
    SELECT
        m.id,
        m.sender_type,
        m.sender_id,
        m.message,
        m.created_at,
        m.read_at
    FROM customer_suspension_messages m
    WHERE m.conversation_id = ?
    ORDER BY m.id ASC
");

$messagesStmt->execute([$conversationId]);

$messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Attachments
|--------------------------------------------------------------------------
*/

$attachmentsByMessage = [];

if (!empty($messages)) {

    $messageIds = array_map(
        static fn($message) => (int) $message['id'],
        $messages
    );

    $placeholders = implode(
        ',',
        array_fill(0, count($messageIds), '?')
    );

    $attachmentStmt = $customerPdo->prepare("
        SELECT
            id,
            message_id,
            original_name,
            stored_name,
            file_path,
            mime_type,
            file_size,
            uploaded_at
        FROM customer_suspension_attachments
        WHERE message_id IN ($placeholders)
        ORDER BY id ASC
    ");

    $attachmentStmt->execute($messageIds);

    $attachments = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attachments as $attachment) {

        $messageId = (int) $attachment['message_id'];

        if (!isset($attachmentsByMessage[$messageId])) {
            $attachmentsByMessage[$messageId] = [];
        }

        $attachmentsByMessage[$messageId][] = $attachment;
    }
}

/*
|--------------------------------------------------------------------------
| Mark Admin Messages As Read
|--------------------------------------------------------------------------
*/

$markReadStmt = $customerPdo->prepare("
    UPDATE customer_suspension_messages
    SET read_at = CURRENT_TIMESTAMP
    WHERE conversation_id = ?
      AND sender_type = 'admin'
      AND read_at IS NULL
");

$markReadStmt->execute([$conversationId]);

?>

<?php require dirname(__DIR__) . '/layouts/header-customer.php'; ?>

<div class="container mt-4 mb-5">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <!-- Suspension Header -->

            <div class="card border-danger shadow-sm mb-4">

                <div class="card-body">

                    <div class="d-flex align-items-center mb-3">

                        <div>
                            <h3 class="mb-1 text-danger">
                                Account Suspended
                            </h3>

                            <p class="mb-0 text-muted">
                                Hello
                                <?= htmlspecialchars($customerDisplayName) ?>.
                                Your account is currently suspended.
                            </p>
                        </div>

                    </div>

                    <hr>

                    <h5 class="mb-3">
                        Suspension Reason
                    </h5>

                    <?php if (empty($activeSuspensions)): ?>

                        <div class="alert alert-warning mb-0">
                            Your account is suspended, but there are
                            currently no active suspension reasons displayed.
                            Please contact the administrator through this
                            communication area.
                        </div>

                    <?php else: ?>

                        <div class="list-group">

                            <?php foreach ($activeSuspensions as $suspension): ?>

                                <div class="list-group-item">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $suspension['reason']
                                        ) ?>
                                    </strong>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- Communication -->

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <h5 class="mb-0">
                        Suspension Communication
                    </h5>

                </div>

                <div
                    class="card-body"
                    style="
                        height: 500px;
                        overflow-y: auto;
                        background: #f8f9fa;
                    "
                >

                    <?php if ($messageError !== null): ?>

                        <div class="alert alert-danger">
                            <?= htmlspecialchars($messageError) ?>
                        </div>

                    <?php endif; ?>

                    <?php if (empty($messages)): ?>

                        <div class="text-center text-muted mt-5">

                            <h5>
                                No messages yet
                            </h5>

                            <p class="mb-0">
                                Use the message box below to communicate
                                with the administrator about your suspension.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($messages as $message): ?>

                            <?php
                            $isCustomerMessage =
                                $message['sender_type'] === 'customer';
                            ?>

                            <div
                                class="d-flex mb-3
                                <?= $isCustomerMessage
                                    ? 'justify-content-end'
                                    : 'justify-content-start'
                                ?>"
                            >

                                <div
                                    class="p-3 rounded shadow-sm"
                                    style="
                                        max-width: 75%;
                                        <?= $isCustomerMessage
                                            ? 'background:#d1e7dd;'
                                            : 'background:#ffffff;'
                                        ?>
                                    "
                                >

                                    <div class="small text-muted mb-1">

                                        <strong>
                                            <?= $isCustomerMessage
                                                ? 'You'
                                                : 'Administrator'
                                            ?>
                                        </strong>

                                        ·

                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $message['created_at']
                                                )
                                            )
                                        ) ?>

                                    </div>

                                    <?php if (
                                        !empty($message['message'])
                                    ): ?>

                                        <div class="mb-2">

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $message['message']
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                    <?php
                                    $messageAttachments =
                                        $attachmentsByMessage[
                                            (int) $message['id']
                                        ] ?? [];
                                    ?>

                                    
                                    <?php foreach (
    $messageAttachments
    as $attachment
): ?>

    <div
        class="border rounded p-2 mt-2"
    >

        <div class="small">

            📎

            <a
                href="?page=suspension-attachment&id=<?= (int) $attachment['id'] ?>"
                target="_blank"
                rel="noopener"
                class="text-decoration-none"
            >

                <?= htmlspecialchars(
                    $attachment['original_name']
                ) ?>

            </a>

        </div>


        <div
            class="small text-muted"
        >

            <?= number_format(
                $attachment['file_size'] / 1024,
                1
            ) ?>

            KB

        </div>

    </div>

<?php endforeach; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <!-- Message Composer -->

                <div class="card-footer">

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <div class="mb-3">

                            <label
                                for="message"
                                class="form-label"
                            >
                                Message
                            </label>

                            <textarea
                                name="message"
                                id="message"
                                class="form-control"
                                rows="4"
                                placeholder="Write a message to the administrator..."
                            ></textarea>

                        </div>

                        <div class="mb-3">

                            <label
                                for="attachment"
                                class="form-label"
                            >
                                Attach supporting document
                            </label>

                            <input
                                type="file"
                                name="attachment"
                                id="attachment"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.pdf"
                            >

                            <div class="form-text">
                                JPG, PNG or PDF. Maximum 5 MB.
                            </div>

                        </div>

                        <div
                            class="d-flex justify-content-end"
                        >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Send Message
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>