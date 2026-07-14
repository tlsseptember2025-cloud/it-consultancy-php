<?php

require CONFIG_PATH . '/database.php';
require dirname(__DIR__) . '/layouts/header-public.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $preferredContact = trim($_POST['preferred_contact']);
    $service = trim($_POST['service']);
    $message = trim($_POST['message']);

    // Generate secure reply token
    $replyToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        INSERT INTO messages (
            name,
            email,
            phone,
            preferred_contact,
            service,
            message,
            reply_token,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'unread')
    ");

    $stmt->execute([
        $name,
        $email,
        $phone,
        $preferredContact,
        $service,
        $message,
        $replyToken
    ]);

    $messageId = $pdo->lastInsertId();

   require_once HELPER_PATH . '/notifications.php';

createNotification(
    $pdo,
    'admin',
    null,
    'New Contact Message',
    $name . ' submitted a new contact inquiry.',
    '?page=view&id=' . $messageId
);

$stmt = $pdo->prepare("
    INSERT INTO message_replies
    (
        message_id,
        sender,
        reply_text
    )
    VALUES
    (
        ?,
        'visitor',
        ?
    )
");

$stmt->execute([
    $messageId,
    $message
]);

    $success = true;
}

$stmt = $pdo->query("
    SELECT * FROM services
    ORDER BY title ASC
");

$services = $stmt->fetchAll();

?>


<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Contact Us
                </h2>

                <?php if (!empty($success)): ?>

                    <div class="alert alert-success">

                        Message sent successfully!

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Your Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Your Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

    <label class="form-label">
        Phone Number
    </label>

    <input
        type="text"
        name="phone"
        class="form-control"
        placeholder="+971 50 123 4567">

</div>

<div class="mb-3">

    <label class="form-label">
        Preferred Contact Method
    </label>

    <select
        name="preferred_contact"
        class="form-select"
        required>

        <option value="Email" selected>
            Email
        </option>

        <option value="Phone">
            Phone
        </option>

    </select>

</div>

                    <div class="mb-3">

                        <label class="form-label">
                            Select Service
                        </label>

                        <select
                            name="service"
                            class="form-select"
                            required>

                            <option value="">
                                Choose a service
                            </option>

                            <?php foreach ($services as $service): ?>

                                <option value="<?= htmlspecialchars($service['title']) ?>">

                                    <?= htmlspecialchars($service['title']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Message
                        </label>

                        <textarea
                            name="message"
                            class="form-control"
                            rows="5"
                            required></textarea>

                    </div>

                    <button class="btn btn-primary">

                        Send Message

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>