<?php

/*
|--------------------------------------------------------------------------
| Prevent Logged-In Users From Requesting Demo Access
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user'])) {

    header('Location: ?page=dashboard');
    exit;

}

if (isset($_SESSION['customer'])) {

    header('Location: ?page=customer-dashboard');
    exit;

}

if (isset($_SESSION['agent'])) {

    header('Location: ?page=agent-dashboard');
    exit;

}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Demo Environment
|--------------------------------------------------------------------------
|
| DEV and DEMO use the same database.
| Determine which site submitted the request.
|
*/

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

if (strpos($host, 'demo.wahbibconsultancy.com') !== false) {

    $demoEnvironment = 'demo';

} elseif (strpos($host, 'dev.wahbibconsultancy.com') !== false) {

    $demoEnvironment = 'dev';

} else {

    $demoEnvironment = 'dev';

}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

$selectedOptions = [];

$allowedOptions = [

    'Customer Management',
    'Service Requests',
    'Consultation Scheduling',
    'Service Jobs',
    'Payments and Refunds',
    'Notifications',
    'Customer Portal',
    'Agent Portal'

];


/*
|--------------------------------------------------------------------------
| Demo Request Submission
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['request_demo'])
) {

    $fullName = trim($_POST['demo_full_name'] ?? '');

    $email = trim($_POST['demo_email'] ?? '');

    $phone = trim($_POST['demo_phone'] ?? '');

    $companyName = trim($_POST['demo_company'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Selected Explore Options
    |--------------------------------------------------------------------------
    */

    $submittedOptions = $_POST['explore_options'] ?? [];

    if (!is_array($submittedOptions)) {

        $submittedOptions = [];

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Options
    |--------------------------------------------------------------------------
    */

    foreach ($submittedOptions as $option) {

        if (
            is_string($option)
            && in_array($option, $allowedOptions, true)
        ) {

            $selectedOptions[] = $option;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Remove Duplicates
    |--------------------------------------------------------------------------
    */

    $selectedOptions = array_values(
        array_unique($selectedOptions)
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {

        $error = 'Please enter your full name.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif (count($selectedOptions) === 0) {

        $error = 'Please select at least one area you would like to explore.';

    }


    /*
    |--------------------------------------------------------------------------
    | Save Demo Request
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $exploreOptionsJson = json_encode(
            $selectedOptions,
            JSON_UNESCAPED_UNICODE
        );


        if ($exploreOptionsJson === false) {

            $error = 'Unable to process your selected options.';

        } else {

            try {

                $stmt = $pdo->prepare("
    INSERT INTO demo_requests (
        full_name,
        email,
        phone,
        company_name,
        explore_options,
        environment,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
");

                $stmt->execute([

                    $fullName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $companyName !== '' ? $companyName : null,
                    $exploreOptionsJson

                ]);


                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                $success =
                    'Thank you! Your demo access request has been submitted. '
                    . 'Your request will be reviewed before access is provided.';


                /*
                |--------------------------------------------------------------------------
                | Clear Form
                |--------------------------------------------------------------------------
                */

                $fullName = '';

                $email = '';

                $phone = '';

                $companyName = '';

                $selectedOptions = [];

            } catch (PDOException $e) {

                error_log(
                    'Demo request submission failed: '
                    . $e->getMessage()
                );

                $error =
                    'We could not submit your demo request right now. '
                    . 'Please try again later.';

            }

        }

    }

}


require dirname(__DIR__) . '/layouts/header-public.php';

?>


<div class="card border-primary shadow-sm mb-5">

    <div class="card-body">


        <!-- ============================================================
             DEMO PORTAL
             ============================================================ -->

        <h3 class="text-primary mb-3">

            🖥 Welcome to the Demo Portal

        </h3>


        <p>

            Explore the IT Consultancy Management System using your own
            temporary demo account.

        </p>


        <!-- ============================================================
             SUCCESS MESSAGE
             ============================================================ -->

        <?php if ($success !== ''): ?>

            <div class="alert alert-success">

                <strong>Demo Request Submitted</strong>

                <br><br>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <!-- ============================================================
             ERROR MESSAGE
             ============================================================ -->

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- ============================================================
             WHAT YOU CAN EXPLORE
             ============================================================ -->

        <h5 class="mt-4">

            What You Can Explore

        </h5>


        <div class="row">

            <div class="col-md-6">

                <ul class="list-unstyled">

                    <li>✅ Customer Registration</li>

                    <li>✅ Customer Dashboard</li>

                    <li>✅ Service Management</li>

                    <li>✅ Consultation Booking</li>

                </ul>

            </div>


            <div class="col-md-6">

                <ul class="list-unstyled">

                    <li>✅ Request Management</li>

                    <li>✅ Payments &amp; Refunds</li>

                    <li>✅ Notifications</li>

                    <li>✅ Reports &amp; Administration</li>

                </ul>

            </div>

        </div>


        <hr>


        <!-- ============================================================
             DEMO ACCESS INFORMATION
             ============================================================ -->

        <div class="alert alert-info">

            <strong>Demo Access Information</strong>

            <br><br>

            Request your own temporary demo account to explore the system.

            <br><br>

            Your request will be reviewed before demo access is provided.

            <br><br>

            If your request is approved, you will receive a unique
            demo username and password.

            <br><br>

            <strong>
                Demo access is valid for 5 days from your first successful
                login.
            </strong>

            <br><br>

            Your demo account and all data created under that demo account
            will be automatically removed when the demo period expires.

            <br><br>

            Demo accounts are completely separate from normal customer,
            agent, and system user accounts.

        </div>


        <!-- ============================================================
             REQUEST DEMO ACCESS
             ============================================================ -->

        <?php if ($success === ''): ?>

            <div class="border rounded p-4">

                <h5 class="text-primary mb-3">

                    🔐 Request Your Own Demo Account

                </h5>


                <p>

                    Please provide your information below to request
                    temporary demo access.

                </p>


                <form
                    method="POST"
                    action="?page=demo"
                    autocomplete="off">


                    <!-- ====================================================
                         FULL NAME
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="demo_full_name"
                            class="form-label">

                            Full Name

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="demo_full_name"
                            name="demo_full_name"
                            maxlength="255"
                            value="<?= htmlspecialchars($fullName ?? '') ?>"
                            required>

                    </div>


                    <!-- ====================================================
                         EMAIL
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="demo_email"
                            class="form-label">

                            Email Address

                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="demo_email"
                            name="demo_email"
                            maxlength="255"
                            value="<?= htmlspecialchars($email ?? '') ?>"
                            required>

                    </div>


                    <!-- ====================================================
                         PHONE
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="demo_phone"
                            class="form-label">

                            Phone Number
                            <span class="text-muted">(Optional)</span>

                        </label>

                        <input
                            type="tel"
                            class="form-control"
                            id="demo_phone"
                            name="demo_phone"
                            maxlength="50"
                            value="<?= htmlspecialchars($phone ?? '') ?>">

                    </div>


                    <!-- ====================================================
                         COMPANY
                         ==================================================== -->

                    <div class="mb-3">

                        <label
                            for="demo_company"
                            class="form-label">

                            Company Name
                            <span class="text-muted">(Optional)</span>

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="demo_company"
                            name="demo_company"
                            maxlength="255"
                            value="<?= htmlspecialchars($companyName ?? '') ?>">

                    </div>


                    <!-- ====================================================
                         AREAS TO EXPLORE
                         ==================================================== -->

                    <div class="mb-3">

                        <label class="form-label">

                            What would you like to explore?

                        </label>


                        <div class="row">


                            <!-- LEFT COLUMN -->

                            <div class="col-md-6">


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Customer Management"
                                        id="explore_customer"
                                        <?= in_array(
                                            'Customer Management',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_customer">

                                        Customer Management

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Service Requests"
                                        id="explore_requests"
                                        <?= in_array(
                                            'Service Requests',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_requests">

                                        Service Requests

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Consultation Scheduling"
                                        id="explore_consultations"
                                        <?= in_array(
                                            'Consultation Scheduling',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_consultations">

                                        Consultation Scheduling

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Service Jobs"
                                        id="explore_jobs"
                                        <?= in_array(
                                            'Service Jobs',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_jobs">

                                        Service Jobs

                                    </label>

                                </div>


                            </div>


                            <!-- RIGHT COLUMN -->

                            <div class="col-md-6">


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Payments and Refunds"
                                        id="explore_payments"
                                        <?= in_array(
                                            'Payments and Refunds',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_payments">

                                        Payments &amp; Refunds

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Notifications"
                                        id="explore_notifications"
                                        <?= in_array(
                                            'Notifications',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_notifications">

                                        Notifications

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Customer Portal"
                                        id="explore_customer_portal"
                                        <?= in_array(
                                            'Customer Portal',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_customer_portal">

                                        Customer Portal

                                    </label>

                                </div>


                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="explore_options[]"
                                        value="Agent Portal"
                                        id="explore_agent_portal"
                                        <?= in_array(
                                            'Agent Portal',
                                            $selectedOptions,
                                            true
                                        ) ? 'checked' : '' ?>>

                                    <label
                                        class="form-check-label"
                                        for="explore_agent_portal">

                                        Agent Portal

                                    </label>

                                </div>


                            </div>

                        </div>


                        <div class="form-text">

                            Select all areas you would like to explore.

                        </div>

                    </div>


                    <!-- ====================================================
                         DEMO NOTICE
                         ==================================================== -->

                    <div class="alert alert-warning">

                        <strong>Please note:</strong>

                        <ul class="mb-0 mt-2">

                            <li>
                                Demo access is temporary.
                            </li>

                            <li>
                                The 5-day period starts after your first
                                successful login.
                            </li>

                            <li>
                                Your demo account is separate from normal
                                customer, agent, and system accounts.
                            </li>

                            <li>
                                You will only have access to data belonging
                                to your own demo account.
                            </li>

                            <li>
                                Demo data will be automatically deleted when
                                the demo period expires.
                            </li>

                            <li>
                                Some functions may be restricted for security
                                reasons.
                            </li>

                        </ul>

                    </div>


                    <!-- ====================================================
                         SUBMIT
                         ==================================================== -->

                    <div class="d-flex gap-2">

    <button
        type="submit"
        name="request_demo"
        value="1"
        class="btn btn-primary">

        Request Demo Access

    </button>


    <a
        href="?page=home"
        class="btn btn-secondary">

        Back

    </a>

</div>


                </form>

            </div>

        <?php endif; ?>


    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>