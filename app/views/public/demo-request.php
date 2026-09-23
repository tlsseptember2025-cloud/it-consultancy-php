<?php

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/email.php';
require_once APP_PATH . '/helpers/demo_helper.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');

    $exploreOptions = $_POST['explore_options'] ?? [];

    if (!is_array($exploreOptions)) {
        $exploreOptions = [];
    }

    if ($fullName === '') {
        $error = 'Please enter your full name.';
    } elseif ($companyName === '') {
        $error = 'Please enter your company name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid company email address.';
    } elseif ($phone === '') {
        $error = 'Please enter your phone number.';
    }

    $companyDomain = null;

    if ($error === '') {

        $companyDomain = getCompanyDomain($email);

if ($companyDomain === null) {
    $error = 'Please enter a valid business email address.';
} elseif (isPersonalEmailDomain($companyDomain)) {
    $error =
        'Please use your company email address. '
        . 'Personal email addresses are not accepted for Demo requests.';
} elseif (!companyDomainExists($companyDomain)) {
    $error =
        'The company email domain could not be verified. '
        . 'Please use a valid company email address.';
}
    }

    /*
     * Check whether this company domain has already received a Demo.
     */
    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM demo_domain_history
            WHERE company_domain = ?
            LIMIT 1
        ");

        $stmt->execute([$companyDomain]);

        if ($stmt->fetch()) {
            $error =
                'This company has already used its Demo access. '
                . 'A second Demo is not available.';
        }
    }

    /*
     * Check for an existing unconfirmed request.
     */
    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM demo_requests
            WHERE company_domain = ?
            AND status = 'Pending Email Confirmation'
            AND confirmation_expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([$companyDomain]);

        if ($stmt->fetch()) {
            $error =
                'A Demo confirmation request for this company is already pending. '
                . 'Please check the company email inbox.';
        }
    }

    if ($error === '') {

        $token = bin2hex(random_bytes(32));

        $confirmationExpiresAt = date('Y-m-d H:i:s', time() + 15 * 60);

        $optionsJson =
            $exploreOptions
                ? json_encode(
                    array_values($exploreOptions),
                    JSON_UNESCAPED_UNICODE
                )
                : null;

        $stmt = $pdo->prepare("
            INSERT INTO demo_requests (
                full_name,
                email,
                phone,
                company_name,
                company_domain,
                explore_options,
                confirmation_token,
                confirmation_expires_at,
                status
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                'Pending Email Confirmation'
            )
        ");

        $stmt->execute([
            $fullName,
            $email,
            $phone,
            $companyName,
            $companyDomain,
            $optionsJson,
            $token,
            $confirmationExpiresAt
        ]);

        $confirmationLink =
            APP_URL
            . '/index.php?page=confirm-demo-email&token='
            . urlencode($token);

        $subject = 'Confirm Your Demo Request';

        $body = "
            <h2>Hello " . htmlspecialchars($fullName) . ",</h2>

            <p>
                Thank you for requesting an IT Consultancy Demo
                for <strong>" . htmlspecialchars($companyName) . "</strong>.
            </p>

            <p>
                Please confirm that this company email address belongs to you
                by clicking the button below.
            </p>

            <p>
                <a
                    href='" . htmlspecialchars($confirmationLink) . "'
                    style='
                        display:inline-block;
                        padding:12px 22px;
                        background:#0d6efd;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:6px;
                    '>
                    Confirm Demo Request
                </a>
            </p>

            <p>
                This confirmation link is valid for
                <strong>15 minutes</strong>.
            </p>

            <p>
                If you did not request a Demo, you can safely ignore this email.
            </p>

            <p>
                Kind regards,<br>
                <strong>" . COMPANY_NAME . "</strong>
            </p>
        ";

        if (sendEmail($email, $subject, $body)) {

            header('Location: ?page=demo-request&sent=1');
            exit;

        } else {

            /*
             * Do not leave a request that the customer was never
             * able to confirm if the email could not be sent.
             */
            $stmt = $pdo->prepare("
                DELETE FROM demo_requests
                WHERE confirmation_token = ?
            ");

            $stmt->execute([$token]);

            $error =
                'We could not send the confirmation email. '
                . 'Please try again later.';
        }
    }
}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="row justify-content-center mt-5">

    <div class="col-lg-7 col-md-9">

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h2 class="text-center mb-2">
                    Request a Demo
                </h2>

                <p class="text-muted text-center mb-4">
                    Explore our IT consultancy platform using a temporary
                    company Demo environment.
                </p>

                <?php if (isset($_GET['sent'])): ?>

                    <div class="alert alert-success">

                        <strong>Check your company email.</strong>

                        <br>

                        We have sent you a confirmation link.
                        This confirmation link is valid for 15 minutes.

                    </div>

                <?php endif; ?>

                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>

                <form method="POST" autocomplete="off">

                    <div class="mb-3">

                        <label class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            maxlength="255"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Company Name
                        </label>

                        <input
                            type="text"
                            name="company_name"
                            class="form-control"
                            maxlength="255"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Company Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            maxlength="255"
                            required>

                        <div class="form-text">
                            A company email address is required.
                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="tel"
                            name="phone"
                            class="form-control"
                            maxlength="50"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            What would you like to explore?
                        </label>

                        <?php
                        $options = [
                            'Customer Management',
                            'Service Management',
                            'Requests & Workflow',
                            'Payments',
                            'Consultations',
                            'Reports',
                            'Other'
                        ];
                        ?>

                        <?php foreach ($options as $option): ?>

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="explore_options[]"
                                    value="<?= htmlspecialchars($option) ?>"
                                    id="option-<?= md5($option) ?>">

                                <label
                                    class="form-check-label"
                                    for="option-<?= md5($option) ?>">

                                    <?= htmlspecialchars($option) ?>

                                </label>

                            </div>

                        <?php endforeach; ?>

                    </div>

                   <div class="d-flex gap-2">

    <button
        type="submit"
        name="request_demo"
        class="btn btn-primary flex-grow-1">

        Request Demo

    </button>

    <a
        href="?page=home"
        class="btn btn-secondary">

        Cancel

    </a>

</div>



                </form>

            </div>

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>