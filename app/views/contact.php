<?php

require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $service = trim($_POST['service']);
    $message = trim($_POST['message']);

    $stmt = $pdo->prepare("
        INSERT INTO messages (
            name,
            email,
            service,
            message,
            status
        )
        VALUES (?, ?, ?, ?, 'unread')
    ");

    $stmt->execute([
        $name,
        $email,
        $service,
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

<?php require __DIR__ . '/layouts/header.php'; ?>

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

<?php require __DIR__ . '/layouts/footer.php'; ?>