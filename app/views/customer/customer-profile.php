<?php

/* --------------------------------------------------------------------------
 | CSRF protection for state-changing POST requests
 |-------------------------------------------------------------------------- */
$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


require_once HELPER_PATH . '/auth.php';

$isDemoCustomer = isset($_SESSION['demo_customer']);

if ($isDemoCustomer) {

    requireDemoCustomer();

    require_once CONFIG_PATH . '/demo-database.php';

    $profilePdo = $demoPdo;

    $customerId = (int) ($_SESSION['demo_customer']['id'] ?? 0);

    $demoTenantId = (int) (
        $_SESSION['demo_customer']['demo_tenant_id'] ?? 0
    );

    if ($customerId <= 0 || $demoTenantId <= 0) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    $demoCustomerCheck = $profilePdo->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $demoCustomerCheck->execute([
        $customerId,
        $demoTenantId
    ]);

    if (!$demoCustomerCheck->fetchColumn()) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

} else {

    requireCustomerLogin();

    require_once CONFIG_PATH . '/database.php';

    $profilePdo = $pdo;

    $customerId = (int) $_SESSION['customer']['id'];
}

/*
|--------------------------------------------------------------------------
| Load Customer Profile
|--------------------------------------------------------------------------
*/

$stmt = $profilePdo->prepare("
    SELECT
        id,
        name,
        email,
        phone
    FROM customers
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $customerId
]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {

    if ($isDemoCustomer) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');

    } else {

        unset($_SESSION['customer']);

        header('Location: ?page=public-login');
    }

    exit;
}

$error = null;
$success = null;

/*
|--------------------------------------------------------------------------
| Save Profile
|--------------------------------------------------------------------------
*/

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
        $stmt = $profilePdo->prepare("
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

            /*
             * Update the authenticated customer only.
             */
            $stmt = $profilePdo->prepare("
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
            if ($isDemoCustomer) {

                $_SESSION['demo_customer']['name'] = $name;
                $_SESSION['demo_customer']['email'] = $email;

            } else {

                $_SESSION['customer']['name'] = $name;
                $_SESSION['customer']['email'] = $email;
            }

            $customer['name'] = $name;
            $customer['email'] = $email;
            $customer['phone'] = $phone;

            $success =
                'Your profile has been updated successfully.';
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
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

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
                        value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">

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