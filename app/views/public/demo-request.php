<?php

require_once CONFIG_PATH . '/database.php';
require_once APP_PATH . '/helpers/demo_helper.php';
require_once APP_PATH . '/helpers/captcha.php';


/*
|--------------------------------------------------------------------------
| Public CSRF Token
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['public_csrf_token']) ||
    !is_string($_SESSION['public_csrf_token'])
) {
    $_SESSION['public_csrf_token'] = bin2hex(random_bytes(32));
}

$publicCsrfToken = $_SESSION['public_csrf_token'];


/*
|--------------------------------------------------------------------------
| CAPTCHA
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['demo_captcha_code'])) {
    generateDemoCaptcha();
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$fullName = '';
$companyName = '';
$email = '';
$phone = '';

$exploreOptions = [];


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedCsrf) ||
        !hash_equals($publicCsrfToken, $submittedCsrf)
    ) {
        http_response_code(400);

        exit(
            'Invalid form submission. Please refresh the page and try again.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Form Values
    |--------------------------------------------------------------------------
    */

    $fullName = trim($_POST['full_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');

    $exploreOptions = $_POST['explore_options'] ?? [];

    if (!is_array($exploreOptions)) {
        $exploreOptions = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $error = 'Please enter your full name.';

    } elseif ($companyName === '') {

        $error = 'Please enter your company name.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid company email address.';

    } elseif ($phone === '') {

        $error = 'Please enter your phone number.';
    }


    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $captchaCode = trim(
            (string) ($_POST['captcha_code'] ?? '')
        );

        if (!verifyDemoCaptcha($captchaCode)) {

            $error =
                'The verification code is incorrect or has expired. '
                . 'Please enter the new code shown in the image.';

            clearDemoCaptcha();
            generateDemoCaptcha();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Company Domain Validation
    |--------------------------------------------------------------------------
    */

    $companyDomain = null;

    if ($error === '') {

        $companyDomain = getCompanyDomain($email);

        if ($companyDomain === null) {

            $error =
                'Please enter a valid business email address.';

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
    |--------------------------------------------------------------------------
    | Check Demo Domain History
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM demo_domain_history
            WHERE company_domain = ?
            LIMIT 1
        ");

        $stmt->execute([
            $companyDomain
        ]);

        if ($stmt->fetch()) {

            $error =
                'This company has already used its Demo access. '
                . 'A second Demo is not available.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing Active Demo Request
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM demo_requests
            WHERE company_domain = ?
            AND status IN (
                'Confirmed',
                'Approved',
                'Customer Confirmed',
                'Demo Created'
            )
            LIMIT 1
        ");

        $stmt->execute([
            $companyDomain
        ]);

        if ($stmt->fetch()) {

            $error =
                'A Demo request for this company already exists. '
                . 'Please contact us if you need assistance.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Confirmed Demo Request
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

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
                email_confirmed_at,
                status
            )
            VALUES (
                ?, ?, ?, ?, ?, ?,
                NULL,
                NULL,
                NULL,
                'Confirmed'
            )
        ");


        $stmt->execute([
            $fullName,
            $email,
            $phone,
            $companyName,
            $companyDomain,
            $optionsJson
        ]);


        /*
        |--------------------------------------------------------------------------
        | Clear CAPTCHA
        |--------------------------------------------------------------------------
        */

        clearDemoCaptcha();


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        header(
            'Location: ?page=demo-request&success=1'
        );

        exit;
    }
}


require dirname(__DIR__) . '/layouts/header-public.php';
require dirname(__DIR__) . '/public/demo-banner.php';

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


                <?php if (isset($_GET['success'])): ?>

                    <div class="alert alert-success">

                        <strong>
                            Demo request submitted successfully.
                        </strong>

                        <br>

                        Your request has been received and is now
                        awaiting administrator review.

                    </div>

                <?php endif; ?>


                <?php if ($error): ?>

                    <div class="alert alert-danger">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    autocomplete="off">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $publicCsrfToken,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


                    <!-- Full Name -->

                    <div class="mb-3">

                        <label class="form-label">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $fullName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required>

                    </div>


                    <!-- Company Name -->

                    <div class="mb-3">

                        <label class="form-label">
                            Company Name
                        </label>

                        <input
                            type="text"
                            name="company_name"
                            class="form-control"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $companyName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required>

                    </div>


                    <!-- Company Email -->

                    <div class="mb-3">

                        <label class="form-label">
                            Company Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required>

                        <div class="form-text">
                            A company email address is required.
                        </div>

                    </div>


                    <!-- Phone -->

                    <div class="mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input
                            type="tel"
                            name="phone"
                            class="form-control"
                            maxlength="50"
                            value="<?= htmlspecialchars(
                                $phone,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required>

                    </div>


                    <!-- Explore Options -->

                    <div class="mb-3">

                        <label class="form-label">
                            What would you like to explore?
                        </label>

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="explore_options[]"
                                value="Consultancy Services"
                                id="exploreServices">

                            <label
                                class="form-check-label"
                                for="exploreServices">

                                Consultancy Services

                            </label>

                        </div>

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="explore_options[]"
                                value="Customer Management"
                                id="exploreCustomer">

                            <label
                                class="form-check-label"
                                for="exploreCustomer">

                                Customer Management

                            </label>

                        </div>

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="explore_options[]"
                                value="Service Requests"
                                id="exploreRequests">

                            <label
                                class="form-check-label"
                                for="exploreRequests">

                                Service Requests

                            </label>

                        </div>

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="explore_options[]"
                                value="Admin Dashboard"
                                id="exploreAdmin">

                            <label
                                class="form-check-label"
                                for="exploreAdmin">

                                Admin Dashboard

                            </label>

                        </div>

                    </div>


                    <!-- CAPTCHA -->

                    <div class="mb-4">

                        <label class="form-label">
                            Verification Code
                        </label>

                        <div class="mb-2">

                            <img
                                id="demoCaptchaImage"
                                src="demo-captcha.php"
                                alt="Verification code"
                                width="320"
                                height="100"
                                style="
                                    display:block;
                                    max-width:100%;
                                    height:auto;
                                ">

                        </div>


                        <div class="text-muted small">

                            New code in
                            <span
                                id="demoCaptchaTimer"
                                class="fw-semibold">

                                60 seconds

                            </span>

                        </div>


                        <input
                            type="text"
                            name="captcha_code"
                            class="form-control mt-2"
                            maxlength="6"
                            minlength="6"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            autocomplete="off"
                            required>

                        <div class="form-text">

                            Enter the 6-digit code shown in the image.

                        </div>

                    </div>


                    <!-- Submit -->

                    <div class="d-grid">

                        <button
                            type="submit"
                            class="btn btn-primary">

                            Submit Demo Request

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<script>

let demoCaptchaTimer;
let demoCaptchaSeconds = 60;


function refreshDemoCaptcha() {

    const image =
        document.getElementById('demoCaptchaImage');

    image.src =
        'demo-captcha.php?refresh='
        + Date.now();

    startDemoCaptchaTimer();
}


function startDemoCaptchaTimer() {

    clearInterval(demoCaptchaTimer);

    demoCaptchaSeconds = 60;

    const timer =
        document.getElementById('demoCaptchaTimer');

    timer.textContent =
        demoCaptchaSeconds + ' seconds';

    demoCaptchaTimer = setInterval(function () {

        demoCaptchaSeconds--;

        timer.textContent =
            demoCaptchaSeconds + ' seconds';

        if (demoCaptchaSeconds <= 0) {

            clearInterval(demoCaptchaTimer);

            refreshDemoCaptcha();

        }

    }, 1000);
}


document.addEventListener('DOMContentLoaded', function () {

    startDemoCaptchaTimer();

});

</script>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>