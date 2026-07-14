<?php

require CONFIG_PATH . '/database.php';

requireAdminLogin();

require dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO customers
        (
            name,
            email,
            phone,
            company,
            notes
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['company'],
        $_POST['notes']
    ]);

    header("Location: ?page=customers");
    exit;
}

?>

<?php require dirname(__DIR__) . '/layouts/header-admin.php'; ?>

<div class="row justify-content-center">

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="mb-4">
                    Add Customer
                </h2>

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Company
                        </label>

                        <input
                            type="text"
                            name="company"
                            class="form-control">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="4"></textarea>

                    </div>

                    <button
                        class="btn btn-primary">

                        Save Customer

                    </button>

                    <a
                        href="?page=customers"
                        class="btn btn-secondary ms-2">

                        Cancel

                    </a>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>