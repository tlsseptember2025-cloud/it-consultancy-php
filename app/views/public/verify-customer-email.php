<?php

require CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/notifications.php';
require_once HELPER_PATH . '/email.php';

$token = trim($_GET['token'] ?? '');

$error = '';
$success = '';

if ($token === '') {

    $error = 'Invalid verification link.';

} else {

    /*
    |--------------------------------------------------------------------------
    | Find Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            email_verified,
            email_verification_expires,
            registration_status
        FROM customers
        WHERE email_verification_token = ?
        LIMIT 1
    ");

    $stmt->execute([
        $token
    ]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$customer) {

        $error = 'This verification link is invalid or has already been used.';

    } elseif ((int) $customer['email_verified'] === 1) {

        $error = 'This email address has already been verified.';

    } elseif (
        empty($customer['email_verification_expires'])
        || strtotime($customer['email_verification_expires']) < time()
    ) {

        $error = 'This verification link has expired. Please register again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify Email
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare("
            UPDATE customers

            SET
                email_verified = 1,
                email_verification_token = NULL,
                email_verification_expires = NULL,
                registration_status = 'Pending Admin Approval'

            WHERE
                id = ?
                AND email_verified = 0
                AND email_verification_token = ?
        ");

        $update->execute([
            $customer['id'],
            $token
        ]);


        if ($update->rowCount() === 0) {

            $error = 'Unable to verify your email address. Please try again.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Notify Administrators
            |--------------------------------------------------------------------------
            */

            createNotification(
                $pdo,
                'admin',
                null,
                'New Customer Registration',
                'Customer ' . $customer['name'] .
                ' has verified their email and is awaiting administrator approval.',
                '?page=customers'
            );


            /*
            |--------------------------------------------------------------------------
            | Administrator Email
            |--------------------------------------------------------------------------
            */

            $mailConfig = require CONFIG_PATH . '/mail_config.php';

            $adminEmail = $mailConfig['admin_email'] ?? null;

            if (!empty($adminEmail)) {

                $subject = 'New Customer Registration Awaiting Approval';

                $body = "
                    <h2>New Customer Registration</h2>

                    <p>
                        A new customer has verified their email address
                        and is now awaiting administrator approval.
                    </p>

                    <hr>

                    <p>
                        <strong>Name:</strong>
                        " . htmlspecialchars($customer['name']) . "
                    </p>

                    <p>
                        <strong>Email:</strong>
                        " . htmlspecialchars($customer['email']) . "
                    </p>

                    <hr>

                    <p>
                        Please log in to the Admin Dashboard to review
                        and approve or reject this registration.
                    </p>

                    <p>
                        Kind regards,<br>
                        <strong>" . COMPANY_NAME . "</strong>
                    </p>
                ";

                sendEmail(
                    $adminEmail,
                    $subject,
                    $body
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $success = true;
        }
    }
}

require VIEW_PATH . '/layouts/header-public.php';

?>

<div class="row justify-content-center">

    <div class="col-md-7">

        <div class="card shadow-sm">

            <div class="card-body text-center">

                <?php if ($success): ?>

                    <div class="fs-1 mb-3">
                        ✅
                    </div>

                    <h2 class="mb-3">
                        Email Verified
                    </h2>

                    <p>
                        Thank you,
                        <strong>
                            <?= htmlspecialchars($customer['name']) ?>
                        </strong>.
                    </p>

                    <p>
                        Your email address has been successfully verified.
                    </p>

                    <div class="alert alert-info">

                        Your registration is now awaiting
                        administrator approval.

                    </div>

                    <p class="text-muted mb-4">

                        You will receive an email when your registration
                        has been approved.

                    </p>

                    <a
                        href="?page=public-login"
                        class="btn btn-primary">

                        Go to Login

                    </a>

                <?php else: ?>

                    <div class="fs-1 mb-3">
                        ❌
                    </div>

                    <h2 class="mb-3">
                        Email Verification Failed
                    </h2>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                    <a
                        href="?page=customer-register"
                        class="btn btn-primary">

                        Register Again

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>