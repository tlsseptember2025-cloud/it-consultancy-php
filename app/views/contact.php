<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Contact Us
                </h2>

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

<?php
require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO messages (name, email, message, status) VALUES (?, ?, ?, 'unread')");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['message']
    ]);

    echo "<p>Message sent successfully!</p>";
}
?>

<?php require __DIR__ . '/layouts/footer.php'; ?>