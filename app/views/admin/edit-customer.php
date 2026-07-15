<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require_once HELPER_PATH . '/auth.php';
require CONFIG_PATH . '/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM customers
    WHERE id = ?
");

$stmt->execute([$id]);

$customer = $stmt->fetch();

if (!$customer) {
    die('Customer not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE customers
        SET
            name = ?,
            email = ?,
            phone = ?,
            company = ?,
            notes = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['company'],
        $_POST['notes'],
        $id
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
                    Edit Customer
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
                            value="<?= htmlspecialchars($customer['name']) ?>"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($customer['email']) ?>">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars($customer['phone']) ?>">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Company
                        </label>

                        <input
                            type="text"
                            name="company"
                            class="form-control"
                            value="<?= htmlspecialchars($customer['company']) ?>">

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            rows="4"><?= htmlspecialchars($customer['notes']) ?></textarea>

                    </div>

                    <button class="btn btn-primary">

                        Update Customer

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