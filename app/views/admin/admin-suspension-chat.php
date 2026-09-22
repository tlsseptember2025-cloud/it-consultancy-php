<?php

require_once HELPER_PATH . '/auth.php';

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
| Supports:
| - Main Admin
| - Demo Admin
| - Demo Super Admin
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);
$isMainAdmin = isset($_SESSION['user']);

if (
    !$isMainAdmin &&
    !$isDemoAdmin &&
    !$isDemoSuperAdmin
) {
    header('Location: ?page=login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determine Environment
|--------------------------------------------------------------------------
*/

$isDemoAdmin = isset($_SESSION['demo_user']);
$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

$chatPdo = $pdo;


/*
|--------------------------------------------------------------------------
| Load Correct Database
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin || $isDemoSuperAdmin) {

    require_once CONFIG_PATH . '/demo-database.php';

    $chatPdo = $demoPdo;
}


/*
|--------------------------------------------------------------------------
| Get Customer ID
|--------------------------------------------------------------------------
*/

$customerId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($customerId <= 0) {
    header('Location: ?page=customers');
    exit;
}


/*
|--------------------------------------------------------------------------
| Demo Tenant
|--------------------------------------------------------------------------
*/

$demoTenantId = null;

if ($isDemoAdmin) {

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );

    if ($demoTenantId <= 0) {
        die('Invalid Demo tenant.');
    }
}


/*
|--------------------------------------------------------------------------
| Load Customer
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $stmt = $chatPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $stmt->execute([
        $customerId,
        $demoTenantId
    ]);

} else {

    /*
     * Main Admin and Demo Super Admin.
     */
    $stmt = $chatPdo->prepare("
        SELECT *
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $customerId
    ]);
}

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Customer not found.');
}


/*
|--------------------------------------------------------------------------
| Customer Must Be Suspended
|--------------------------------------------------------------------------
*/

if (($customer['status'] ?? 'Active') !== 'Suspended') {

    header(
        'Location: ?page=customer-status&id=' . $customerId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Active Suspension Reasons
|--------------------------------------------------------------------------
*/

$suspensionStmt = $chatPdo->prepare("
    SELECT
        id,
        reason,
        created_at
    FROM customer_suspensions
    WHERE customer_id = ?
      AND active = 1
    ORDER BY created_at ASC
");

$suspensionStmt->execute([
    $customerId
]);

$activeSuspensions = $suspensionStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Find Existing Suspension Conversation
|--------------------------------------------------------------------------
*/

$conversationStmt = $chatPdo->prepare("
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

$conversationStmt->execute([
    $customerId
]);

$conversation = $conversationStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Create Conversation If It Does Not Exist
|--------------------------------------------------------------------------
*/

if (!$conversation) {

    $createConversationStmt = $chatPdo->prepare("
        INSERT INTO customer_suspension_conversations
        (
            customer_id,
            status
        )
        VALUES (?, 'Open')
    ");

    $createConversationStmt->execute([
        $customerId
    ]);

    $conversationId = (int) $chatPdo->lastInsertId();

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
| Determine Admin ID
|--------------------------------------------------------------------------
|
| Demo Admin:
|   The Demo session stores the complete user record.
|
| Demo Super Admin:
|   The Demo Super Admin session stores the complete user record.
|
| Main Admin:
|   The current Main Admin login stores the admin email in
|   $_SESSION['user'], so we must look up the actual user ID.
|--------------------------------------------------------------------------
*/

if ($isDemoAdmin) {

    $adminId = (int) ($_SESSION['demo_user']['id'] ?? 0);

} elseif ($isDemoSuperAdmin) {

    $adminId = (int) ($_SESSION['demo_super_admin']['id'] ?? 0);

} else {

    $adminEmail = trim((string) ($_SESSION['user'] ?? ''));

    if ($adminEmail === '') {

        header('Location: ?page=login');
        exit;
    }

    $adminStmt = $chatPdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $adminStmt->execute([
        $adminEmail
    ]);

    $adminId = (int) $adminStmt->fetchColumn();

    if ($adminId <= 0) {

        die('Main Admin account could not be identified.');
    }
}



/*
|--------------------------------------------------------------------------
| Process Admin Message
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = trim($_POST['message'] ?? '');

    $hasAttachment =
        isset($_FILES['attachment']) &&
        $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;


    /*
     * Do not allow completely empty messages.
     */
    if ($message === '' && !$hasAttachment) {

        $error = 'Please enter a message or attach a file.';

    } else {

        try {

            /*
             * ----------------------------------------------------------
             * Validate attachment before opening transaction
             * ----------------------------------------------------------
             */

            $attachmentData = null;

            if ($hasAttachment) {

                if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(
                        'The attachment could not be uploaded.'
                    );
                }


                $maxFileSize = 5 * 1024 * 1024;

                if ((int) $_FILES['attachment']['size'] > $maxFileSize) {
                    throw new RuntimeException(
                        'Attachment size cannot exceed 5 MB.'
                    );
                }


                /*
                 * Detect actual MIME type from file contents.
                 */
                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                if (!$finfo) {
                    throw new RuntimeException(
                        'Unable to verify the attachment type.'
                    );
                }

                $detectedMime = finfo_file(
                    $finfo,
                    $_FILES['attachment']['tmp_name']
                );

                finfo_close($finfo);


                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/png',
                    'application/pdf'
                ];


                if (!in_array(
                    $detectedMime,
                    $allowedMimeTypes,
                    true
                )) {
                    throw new RuntimeException(
                        'Only JPG, PNG, and PDF attachments are allowed.'
                    );
                }


                $originalName = basename(
                    $_FILES['attachment']['name']
                );

                $originalName = str_replace(
                    ["\r", "\n"],
                    '',
                    $originalName
                );


                if ($originalName === '') {
                    throw new RuntimeException(
                        'Invalid attachment name.'
                    );
                }


                $storedName =
                    bin2hex(random_bytes(32));


                $storageDirectory =
                    dirname(__DIR__, 3)
                    . '/storage/suspension-attachments';


                if (!is_dir($storageDirectory)) {

                    if (!mkdir(
                        $storageDirectory,
                        0755,
                        true
                    )) {
                        throw new RuntimeException(
                            'Unable to create attachment storage directory.'
                        );
                    }
                }


                $storedPath =
                    $storageDirectory
                    . '/'
                    . $storedName;


                if (!move_uploaded_file(
                    $_FILES['attachment']['tmp_name'],
                    $storedPath
                )) {
                    throw new RuntimeException(
                        'Unable to save the attachment.'
                    );
                }


                $attachmentData = [
                    'original_name' => $originalName,
                    'stored_name'   => $storedName,
                    'file_path'     => $storedPath,
                    'mime_type'     => $detectedMime,
                    'file_size'     => (int) $_FILES['attachment']['size']
                ];
            }


            /*
             * ----------------------------------------------------------
             * Save message + attachment
             * ----------------------------------------------------------
             */

            $chatPdo->beginTransaction();


            /*
             * Verify conversation still belongs to this customer.
             */
            $conversationCheck = $chatPdo->prepare("
                SELECT id
                FROM customer_suspension_conversations
                WHERE id = ?
                  AND customer_id = ?
                LIMIT 1
            ");

            $conversationCheck->execute([
                $conversationId,
                $customerId
            ]);

            if (!$conversationCheck->fetchColumn()) {

                throw new RuntimeException(
                    'Invalid suspension conversation.'
                );
            }


            /*
             * Insert Admin message.
             */
            $messageStmt = $chatPdo->prepare("
                INSERT INTO customer_suspension_messages
                (
                    conversation_id,
                    sender_type,
                    sender_id,
                    message
                )
                VALUES (?, 'admin', ?, ?)
            ");

            $messageStmt->execute([
                $conversationId,
                $adminId,
                $message
            ]);


            $messageId =
                (int) $chatPdo->lastInsertId();


            /*
             * Insert attachment metadata.
             */
            if ($attachmentData !== null) {

                $attachmentStmt = $chatPdo->prepare("
                    INSERT INTO customer_suspension_attachments
                    (
                        message_id,
                        original_name,
                        stored_name,
                        file_path,
                        mime_type,
                        file_size
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $attachmentStmt->execute([
                    $messageId,
                    $attachmentData['original_name'],
                    $attachmentData['stored_name'],
                    $attachmentData['file_path'],
                    $attachmentData['mime_type'],
                    $attachmentData['file_size']
                ]);
            }


            /*
             * Re-open the conversation when Admin responds.
             */
            $updateConversationStmt = $chatPdo->prepare("
                UPDATE customer_suspension_conversations
                SET status = 'Open'
                WHERE id = ?
                  AND customer_id = ?
            ");

            $updateConversationStmt->execute([
                $conversationId,
                $customerId
            ]);


            $chatPdo->commit();


            /*
             * Prevent duplicate POST submission.
             */
            header(
                'Location: ?page=admin-suspension-chat&id='
                . $customerId
            );

            exit;


        } catch (Throwable $e) {

            if ($chatPdo->inTransaction()) {
                $chatPdo->rollBack();
            }

            /*
             * If the file was already moved but the database operation
             * failed, remove the physical file.
             */
            if (
                isset($attachmentData['file_path']) &&
                is_file($attachmentData['file_path'])
            ) {
                @unlink($attachmentData['file_path']);
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Conversation Messages
|--------------------------------------------------------------------------
*/

$messageStmt = $chatPdo->prepare("
    SELECT
        m.id,
        m.sender_type,
        m.sender_id,
        m.message,
        m.created_at,
        m.read_at
    FROM customer_suspension_messages m
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC, m.id ASC
");

$messageStmt->execute([
    $conversationId
]);

$messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Load Attachments
|--------------------------------------------------------------------------
*/

$attachmentsByMessage = [];

$attachmentStmt = $chatPdo->prepare("
    SELECT
        id,
        message_id,
        original_name,
        stored_name,
        mime_type,
        file_size,
        uploaded_at
    FROM customer_suspension_attachments
    WHERE message_id IN (
        SELECT id
        FROM customer_suspension_messages
        WHERE conversation_id = ?
    )
    ORDER BY id ASC
");

$attachmentStmt->execute([
    $conversationId
]);

$attachments = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($attachments as $attachment) {

    $messageId =
        (int) $attachment['message_id'];

    if (!isset($attachmentsByMessage[$messageId])) {
        $attachmentsByMessage[$messageId] = [];
    }

    $attachmentsByMessage[$messageId][] = $attachment;
}


/*
|--------------------------------------------------------------------------
| Mark Customer Messages As Read
|--------------------------------------------------------------------------
|
| Admin opening the conversation means the customer's messages have
| been viewed.
|--------------------------------------------------------------------------
*/

$markReadStmt = $chatPdo->prepare("
    UPDATE customer_suspension_messages
    SET read_at = NOW()
    WHERE conversation_id = ?
      AND sender_type = 'customer'
      AND read_at IS NULL
");

$markReadStmt->execute([
    $conversationId
]);


require dirname(__DIR__) . '/layouts/header-admin.php';

?>


<div class="container mt-4">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <div class="card shadow-sm">

                <div class="card-header bg-danger text-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <strong>
                            Customer Suspension Chat
                        </strong>

                        <span class="badge bg-light text-dark">
                            Suspended
                        </span>

                    </div>

                </div>


                <div class="card-body">


                    <!--
                    -------------------------------------------------------
                    Customer Information
                    -------------------------------------------------------
                    -->

                    <div class="border rounded p-3 mb-4">

                        <div class="row">

                            <div class="col-md-6 mb-2">

                                <strong>
                                    Customer:
                                </strong>

                                <?= htmlspecialchars(
                                    $customer['name']
                                ) ?>

                            </div>


                            <div class="col-md-6 mb-2">

                                <strong>
                                    Email:
                                </strong>

                                <?= htmlspecialchars(
                                    $customer['email']
                                ) ?>

                            </div>


                            <div class="col-md-6">

                                <strong>
                                    Company:
                                </strong>

                                <?= htmlspecialchars(
                                    $customer['company'] ?? '-'
                                ) ?>

                            </div>


                            <div class="col-md-6">

                                <strong>
                                    Conversation:
                                </strong>

                                <?= htmlspecialchars(
                                    $conversation['status'] ?? 'Open'
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!--
                    -------------------------------------------------------
                    Active Suspension Reasons
                    -------------------------------------------------------
                    -->

                    <div class="alert alert-warning">

                        <strong>
                            Active Suspension Reasons
                        </strong>

                        <?php if (empty($activeSuspensions)): ?>

                            <div class="mt-2">
                                No active suspension reasons remain.
                            </div>

                        <?php else: ?>

                            <ul class="mb-0 mt-2">

                                <?php foreach ($activeSuspensions as $suspension): ?>

                                    <li>
                                        <?= htmlspecialchars(
                                            $suspension['reason']
                                        ) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php endif; ?>

                    </div>


                    <!--
                    -------------------------------------------------------
                    Error
                    -------------------------------------------------------
                    -->

                    <?php if ($error): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error) ?>

                        </div>

                    <?php endif; ?>


                    <!--
                    -------------------------------------------------------
                    Chat Messages
                    -------------------------------------------------------
                    -->

                    <div
                        class="border rounded p-3 mb-4"
                        style="max-height: 500px; overflow-y: auto;"
                    >

                        <?php if (empty($messages)): ?>

                            <div class="text-center text-muted py-5">

                                No messages yet.

                                <br>

                                Send a message to begin the suspension
                                resolution conversation.

                            </div>

                        <?php else: ?>

                            <?php foreach ($messages as $chatMessage): ?>

                                <?php
                                $isAdminMessage =
                                    $chatMessage['sender_type'] === 'admin';
                                ?>

                                <div
                                    class="d-flex mb-3
                                    <?= $isAdminMessage
                                        ? 'justify-content-end'
                                        : 'justify-content-start'
                                    ?>"
                                >

                                    <div
                                        class="border rounded p-3"
                                        style="max-width: 75%;"
                                    >

                                        <div class="small text-muted mb-1">

                                            <?php if ($isAdminMessage): ?>

                                                <strong>
                                                    Admin
                                                </strong>

                                            <?php else: ?>

                                                <strong>
                                                    Customer
                                                </strong>

                                            <?php endif; ?>

                                            &middot;

                                            <?= htmlspecialchars(
                                                $chatMessage['created_at']
                                            ) ?>

                                        </div>


                                        <?php if (
                                            trim(
                                                $chatMessage['message'] ?? ''
                                            ) !== ''
                                        ): ?>

                                            <div class="mb-2">

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $chatMessage['message']
                                                    )
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                        <?php
                                        $messageId =
                                            (int) $chatMessage['id'];

                                        $messageAttachments =
                                            $attachmentsByMessage[$messageId]
                                            ?? [];
                                        ?>


                                        <?php if (!empty($messageAttachments)): ?>

                                            <div class="mt-2">

                                                <?php foreach (
                                                    $messageAttachments
                                                    as $attachment
                                                ): ?>

                                                    <div class="mb-1">

                                                        <a
                                                            href="?page=suspension-attachment&id=<?= (int) $attachment['id'] ?>"
                                                            target="_blank"
                                                            rel="noopener"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $attachment['original_name']
                                                            ) ?>

                                                        </a>

                                                        <span class="small text-muted">

                                                            (
                                                            <?= number_format(
                                                                $attachment['file_size']
                                                                / 1024,
                                                                1
                                                            ) ?>
                                                            KB
                                                            )

                                                        </span>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>


                    <!--
                    -------------------------------------------------------
                    Admin Reply Form
                    -------------------------------------------------------
                    -->

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <div class="mb-3">

                            <label
                                for="message"
                                class="form-label"
                            >
                                <strong>
                                    Reply to Customer
                                </strong>
                            </label>

                            <textarea
                                id="message"
                                name="message"
                                class="form-control"
                                rows="5"
                                placeholder="Enter your message to the customer..."
                            ></textarea>

                        </div>


                        <div class="mb-3">

                            <label
                                for="attachment"
                                class="form-label"
                            >
                                Attachment
                            </label>

                            <input
                                type="file"
                                id="attachment"
                                name="attachment"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.pdf"
                            >

                            <div class="form-text">
                                JPG, PNG, or PDF. Maximum 5 MB.
                            </div>

                        </div>


                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Send Reply
                            </button>


                            <a
                                href="?page=customer-status&id=<?= $customerId ?>"
                                class="btn btn-outline-secondary"
                            >
                                Customer Status
                            </a>


                            <a
                                href="?page=customers"
                                class="btn btn-outline-secondary"
                            >
                                Back to Customers
                            </a>

                        </div>

                    </form>


                </div>

            </div>

        </div>

    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>