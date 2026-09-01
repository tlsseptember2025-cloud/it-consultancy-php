<?php

require CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/email.php';

require dirname(__DIR__) . '/layouts/header-public.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if ($password !== $confirmPassword) {

        $error = 'Passwords do not match.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Customer
        |--------------------------------------------------------------------------
        */

        $check = $pdo->prepare("
            SELECT id
            FROM customers
            WHERE email = ?
        ");

        $check->execute([$email]);

        if ($check->fetch()) {

            $error = 'Email already registered.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Create Verification Token
            |--------------------------------------------------------------------------
            */

            $verificationToken = bin2hex(
                random_bytes(32)
            );

            $verificationExpires = date(
                'Y-m-d H:i:s',
                time() + (24 * 60 * 60)
            );

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
            |--------------------------------------------------------------------------
            | Create Customer
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO customers
                (
                    name,
                    email,
                    phone,
                    password,
                    email_verified,
                    email_verification_token,
                    email_verification_expires,
                    registration_status
                )
                VALUES (?, ?, ?, ?, 0, ?, ?, 'Pending Email Verification')
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $hashedPassword,
                $verificationToken,
                $verificationExpires
            ]);

            /*
            |--------------------------------------------------------------------------
            | Verification Link
            |--------------------------------------------------------------------------
            */

            $verificationLink =
                APP_URL
                . '/index.php?page=verify-customer-email&token='
                . urlencode($verificationToken);

            /*
            |--------------------------------------------------------------------------
            | Verification Email
            |--------------------------------------------------------------------------
            */

            $subject = 'Verify Your Customer Account';

            $body = "
                <h2>Welcome to " . COMPANY_NAME . "</h2>

                <p>Hello " . htmlspecialchars($name) . ",</p>

                <p>
                    Thank you for registering with us.
                </p>

                <p>
                    Before your registration can be reviewed by our
                    administrator, please verify your email address.
                </p>

                <p>
                    <a
                        href='{$verificationLink}'
                        style='
                            display:inline-block;
                            padding:12px 22px;
                            background:#0d6efd;
                            color:#ffffff;
                            text-decoration:none;
                            border-radius:6px;
                            font-weight:600;
                        '>
                        Verify My Email
                    </a>
                </p>

                <p>
                    This verification link will expire in
                    <strong>24 hours</strong>.
                </p>

                <p>
                    After verification, your registration will be
                    submitted for administrator approval.
                </p>

                <p>
                    You will receive another email once your registration
                    has been approved or rejected.
                </p>

                <br>

                <p>
                    Kind regards,<br>
                    <strong>" . COMPANY_NAME . "</strong>
                </p>
            ";

            /*
            |--------------------------------------------------------------------------
            | Send Verification Email
            |--------------------------------------------------------------------------
            */

            $emailSent = sendEmail(
                $email,
                $subject,
                $body
            );

            if (!$emailSent) {

                /*
                |--------------------------------------------------------------------------
                | Remove Account If Verification Email Failed
                |--------------------------------------------------------------------------
                |
                | We do not want an account that the customer cannot verify.
                |
                */

                $delete = $pdo->prepare("
                    DELETE FROM customers
                    WHERE email = ?
                ");

                $delete->execute([
                    $email
                ]);

                $error = 'Unable to send the verification email. Please try again later.';

            } else {

                $success = 'Registration submitted successfully. Please check your email and click the verification link to continue.';
            }
        }
    }
}

?>

<div class="row justify-content-center">

    <div class="col-md-6">

        <div class="card shadow-sm">

            <div class="card-body">

                <h2 class="mb-4">
                    Customer Registration
                </h2>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <?php if ($success): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                <?php endif; ?>


                <?php if (!$success): ?>

                    <form method="POST" autocomplete="off">

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
                                class="form-control"
                                autocomplete="off"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                autocomplete="new-password"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                class="form-control"
                                autocomplete="new-password"
                                required>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            Register

                        </button>

                    </form>

                <?php else: ?>

                    <a
                        href="?page=public-login"
                        class="btn btn-primary">

                        Go to Login

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>