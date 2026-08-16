<?php

if (!isset($_SESSION['customer'])) {

    header('Location: ?page=public-login');
    exit;
}

require_once CONFIG_PATH . '/database.php';

$customerId = (int) $_SESSION['customer']['id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$customerId]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {

    session_destroy();

    header('Location: ?page=public-login');
    exit;
}


$error = null;
$success = null;


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_profile'])
) {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {

        $error = 'Name is required.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        /*
         * Check whether another customer already uses
         * this email address.
         */
        $stmt = $pdo->prepare("
            SELECT id
            FROM customers
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $email,
            $customerId
        ]);

        if ($stmt->fetch()) {

            $error = 'This email address is already in use.';

        } else {

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    name = ?,
                    email = ?,
                    phone = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $customerId
            ]);

            /*
             * Keep the session information synchronized
             * with the database.
             */
            $_SESSION['customer']['name'] = $name;
            $_SESSION['customer']['email'] = $email;

            $customer['name'] = $name;
            $customer['email'] = $email;
            $customer['phone'] = $phone;

            $success = 'Your profile has been updated successfully.';
        }
    }
}

require VIEW_PATH . '/layouts/header-customer.php';

?>

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header bg-dark text-white">

            <h4 class="mb-0">
                My Profile
            </h4>

        </div>


        <div class="card-body">

            <?php if ($success): ?>

                <div class="alert alert-success">

                    <?= htmlspecialchars($success) ?>

                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="mb-3">

                    <label class="form-label fw-bold">
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

                    <label class="form-label fw-bold">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($customer['email']) ?>"
                        required>

                    <div class="form-text">
                        Your email is also used to log in to your customer account.
                    </div>

                </div>


                <div class="mb-4">

                    <label class="form-label fw-bold">
                        Phone
                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                        >

                </div>


                <button
                    type="submit"
                    name="save_profile"
                    class="btn btn-success">

                    💾 Save Changes

                </button>

            </form>


            <hr class="my-4">


            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-1">
                        Password
                    </h5>

                    <p class="text-muted mb-0">
                        Change your password using a secure link sent to your email.
                    </p>

                </div>


                <a
                    href="?page=customer-forgot-password"
                    class="btn btn-primary">

                    🔐 Change Password

                </a>

            </div>


            <div class="mt-4">

                <a
                    href="?page=customer-dashboard"
                    class="btn btn-secondary">

                    ← Back

                </a>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>