<?php

require_once HELPER_PATH . '/email.php';

/*
|--------------------------------------------------------------------------
| Contract Lead Form
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Anti-Spam
|--------------------------------------------------------------------------
|
| Honeypot field:
| Real users never see or fill this field.
| Basic bots often do.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_contract_lead'])
) {

    /*
    |--------------------------------------------------------------------------
    | Honeypot spam protection
    |--------------------------------------------------------------------------
    */

    if (!empty($_POST['website'])) {

        $error =
            'Unable to submit the form. Please try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | Basic submission-time protection
    |--------------------------------------------------------------------------
    |
    | A normal user should need at least a few seconds
    | to read and complete the form.
    |
    */

    if (
        empty($error)
        &&
        isset($_POST['form_started_at'])
    ) {

        $formStartedAt = (int) $_POST['form_started_at'];

        if (
            $formStartedAt > 0
            &&
            (time() - $formStartedAt) < 3
        ) {

            $error =
                'Unable to submit the form. Please try again.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Collect Form Data
    |--------------------------------------------------------------------------
    */

    if (empty($error)) {

        $companyName =
            trim($_POST['company_name'] ?? '');

        $contactPerson =
            trim($_POST['contact_person'] ?? '');

        $email =
            trim($_POST['email'] ?? '');

        $phone =
            trim($_POST['phone'] ?? '');

        $contractTerm =
            trim($_POST['contract_term'] ?? '');

        $supportCoverage =
            trim($_POST['support_coverage'] ?? '');

        $startTimeframe =
            trim($_POST['start_timeframe'] ?? '');

        $supportServices =
            $_POST['support_services'] ?? [];

        $marketingConsent =
            isset($_POST['marketing_consent'])
            ? 1
            : 0;


        /*
        |--------------------------------------------------------------------------
        | Validate Required Fields
        |--------------------------------------------------------------------------
        */

        if (
            $companyName === '' ||
            $contactPerson === '' ||
            $email === '' ||
            $phone === ''
        ) {

            $error =
                'Please complete all required fields.';

        }

        elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Please enter a valid email address.';

        }

        elseif (
            !is_array($supportServices)
            ||
            empty($supportServices)
        ) {

            $error =
                'Please select at least one service you are interested in.';

        }

        elseif (
    !in_array(
        $contractTerm,
        [
            'Monthly',
            'Annual',
            'Not Sure'
        ],
        true
    )
) {

    $error =
        'Please select a contract preference.';

}

       elseif (
    !in_array(
        $supportCoverage,
        [
            'Business Hours',
            'Extended Hours',
            '24/7',
            'Not Sure'
        ],
        true
    )
) {

            $error =
                'Please select your preferred support coverage.';

        }

       elseif (
    !in_array(
        $startTimeframe,
        [
            'Immediately',
            'Within 30 Days',
            'Within 3 Months',
            'Just Exploring'
        ],
        true
    )
) {

    $error =
        'Please select when you would like to start.';

}


        /*
        |--------------------------------------------------------------------------
        | Validate Service Selections
        |--------------------------------------------------------------------------
        */

        if (empty($error)) {

            $allowedServices = [

                'Remote IT Support',

                'Software Services',

                'E-commerce Website',

                'Corporate Website',

                'Website Maintenance'

            ];


            $supportServices =
                array_values(
                    array_intersect(
                        $allowedServices,
                        $supportServices
                    )
                );


            if (empty($supportServices)) {

                $error =
                    'Please select at least one valid service.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Save Lead
        |--------------------------------------------------------------------------
        */

        if (empty($error)) {

            /*
            |--------------------------------------------------------------------------
            | Store Service Selections as JSON
            |--------------------------------------------------------------------------
            */

            $supportServicesJson =
                json_encode(
                    $supportServices,
                    JSON_UNESCAPED_UNICODE
                );


            /*
            |--------------------------------------------------------------------------
            | Insert Lead
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO contract_leads
            (
                company_name,
                contact_person,
                email,
                phone,
                contract_term,
                support_services,
                support_coverage,
                start_timeframe,
                marketing_consent,
                status,
                approval_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");


            $stmt->execute([

                $companyName,
                $contactPerson,
                $email,
                $phone,
                $contractTerm,
                $supportServicesJson,
                $supportCoverage,
                $startTimeframe,
                $marketingConsent,
                'New',
                'Pending'

            ]);
            /*
            |--------------------------------------------------------------------------
            | Email Notification
            |--------------------------------------------------------------------------
            */

            sendContractLeadNotification(

                $companyName,

                $contactPerson,

                $email,

                $phone,

                null,

                implode(
                    ', ',
                    $supportServices
                )
                .
                "\n\nContract Preference: "
                .
                $contractTerm
                .
                "\n\nSupport Coverage: "
                .
                $supportCoverage
                .
                "\n\nPreferred Start Timeframe: "
                .
                $startTimeframe

            );


            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */

            $success =
                'Thank you for your interest! We will contact you shortly.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-public.php';

?>


<?php if (!empty($success)): ?>

    <div
        class="alert alert-success alert-dismissible fade show"
        role="alert">

        <?= htmlspecialchars($success) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<?php if (!empty($error)): ?>

    <div
        class="alert alert-danger alert-dismissible fade show"
        role="alert">

        <?= htmlspecialchars($error) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| Hero Section
|--------------------------------------------------------------------------
-->

<div class="p-5 mb-4 bg-light rounded-3">

    <div class="container-fluid py-5">

        <h1 class="display-5 fw-bold">

            <?= PRODUCT_NAME ?>

        </h1>

        <p class="col-md-8 fs-4">

    Manage customers, services, requests, invoices,
    payments, consultations and more from one platform.

</p>


<div class="d-flex flex-wrap gap-2">

    <a
        class="btn btn-primary btn-lg"
        href="?page=demo">

        Request a Demo

    </a>


    <a
        class="btn btn-outline-primary btn-lg"
        href="?page=demo-login">

        Login to Demo Portal

    </a>

</div>
    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Features
|--------------------------------------------------------------------------
-->

<div class="mt-5 mb-5">

    <div class="text-center mb-5">

        <h2 class="fw-bold">

            Powerful Features

        </h2>

        <p class="text-muted">

            Everything you need to manage your IT consultancy business from one place.

        </p>

    </div>


    <div class="row g-4">


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">👥</div>

                    <h5>Customer Management</h5>

                    <p class="text-muted">

                        Manage customers, profiles and communication.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📋</div>

                    <h5>Request Management</h5>

                    <p class="text-muted">

                        Track customer requests from start to completion.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">💳</div>

                    <h5>Payments</h5>

                    <p class="text-muted">

                        Record payments and process refund requests.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📅</div>

                    <h5>Consultations</h5>

                    <p class="text-muted">

                        Schedule and manage customer consultations.

                    </p>

                </div>

            </div>

        </div>

    </div>


    <div class="row g-4 mt-1">


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📊</div>

                    <h5>Dashboard</h5>

                    <p class="text-muted">

                        View business statistics and important activities.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">🔔</div>

                    <h5>Notifications</h5>

                    <p class="text-muted">

                        Stay updated with customer and system notifications.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">📄</div>

                    <h5>Reports</h5>

                    <p class="text-muted">

                        Generate reports for business insights.

                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card h-100 shadow-sm text-center">

                <div class="card-body">

                    <div class="display-4 mb-3">⚙️</div>

                    <h5>Administration</h5>

                    <p class="text-muted">

                        Manage users, settings and system configuration.

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Additional Features
|--------------------------------------------------------------------------
-->

<div class="row">


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Easy Installation</h4>

                <p>

                    Install the software in minutes using the built-in installation wizard.

                </p>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Email Notifications</h4>

                <p>

                    Automatically notify administrators and customers about important activities.

                </p>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Multi-User Access</h4>

                <p>

                    Separate administrator and customer portals with secure authentication.

                </p>

            </div>

        </div>

    </div>


</div>


<!--
|--------------------------------------------------------------------------
| Business IT Support Lead Form
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mt-5 mb-5">

    <div class="card-body p-4">


        <h3 class="mb-3">

            🏢 Business IT Support Plans

        </h3>


        <p class="text-muted">

            Looking for reliable monthly or annual IT support,
            software services or website services for your company?

            Tell us what you are interested in and we'll contact you
            with a suitable solution.

        </p>


        <form method="POST">


            <!--
            |--------------------------------------------------------------------------
            | Anti-Spam Honeypot
            |--------------------------------------------------------------------------
            -->

            <div
                style="
                    position:absolute;
                    left:-9999px;
                    width:1px;
                    height:1px;
                    overflow:hidden;
                "
                aria-hidden="true">

                <label for="website">

                    Website

                </label>

                <input
                    type="text"
                    name="website"
                    id="website"
                    tabindex="-1"
                    autocomplete="off">

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Submission Time
            |--------------------------------------------------------------------------
            -->

            <input
                type="hidden"
                name="form_started_at"
                value="<?= time() ?>">


            <!--
            |--------------------------------------------------------------------------
            | Company Information
            |--------------------------------------------------------------------------
            -->

            <div class="row">


                <div class="col-md-6 mb-3">

                    <label
                        class="form-label"
                        for="company_name">

                        Company Name

                    </label>

                    <input
                        type="text"
                        name="company_name"
                        id="company_name"
                        class="form-control"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label
                        class="form-label"
                        for="contact_person">

                        Contact Person

                    </label>

                    <input
                        type="text"
                        name="contact_person"
                        id="contact_person"
                        class="form-control"
                        required>

                </div>


            </div>


            <div class="row">


                <div class="col-md-6 mb-3">

                    <label
                        class="form-label"
                        for="email">

                        Email Address

                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label
                        class="form-label"
                        for="phone">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        name="phone"
                        id="phone"
                        class="form-control"
                        required>

                </div>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Service Interest
            |--------------------------------------------------------------------------
            -->

            <div class="card border mb-4">

                <div class="card-body">


                    <h5 class="mb-2">

                        What services are you interested in?

                    </h5>


                    <p class="text-muted small mb-4">

                        Select all that apply.

                    </p>


                    <!--
                    |--------------------------------------------------------------------------
                    | IT Support / Software Services
                    |--------------------------------------------------------------------------
                    -->

                    <h6 class="fw-bold mb-3">

                        IT Support / Software Services

                    </h6>


                    <div class="form-check mb-3">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="support_services[]"
                            value="Remote IT Support"
                            id="remoteIT">

                        <label
                            class="form-check-label"
                            for="remoteIT">

                            Remote IT Support

                        </label>

                    </div>


                    <div class="form-check mb-4">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="support_services[]"
                            value="Software Services"
                            id="softwareServices">

                        <label
                            class="form-check-label"
                            for="softwareServices">

                            Software Services

                        </label>

                    </div>


                    <hr>


                    <!--
                    |--------------------------------------------------------------------------
                    | Website Services
                    |--------------------------------------------------------------------------
                    -->

                    <h6 class="fw-bold mt-4 mb-3">

                        Website Services

                    </h6>


                    <div class="form-check mb-3">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="support_services[]"
                            value="E-commerce Website"
                            id="ecommerceWebsite">

                        <label
                            class="form-check-label"
                            for="ecommerceWebsite">

                            E-commerce Website

                        </label>

                    </div>


                    <div class="form-check mb-3">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="support_services[]"
                            value="Corporate Website"
                            id="corporateWebsite">

                        <label
                            class="form-check-label"
                            for="corporateWebsite">

                            Corporate Website

                        </label>

                    </div>


                    <div class="form-check">

                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="support_services[]"
                            value="Website Maintenance"
                            id="websiteMaintenance">

                        <label
                            class="form-check-label"
                            for="websiteMaintenance">

                            Website Maintenance

                        </label>

                    </div>


                </div>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Support Coverage
            |--------------------------------------------------------------------------
            -->

            <div class="mb-3">


                <label
                    class="form-label"
                    for="support_coverage">

                    Preferred Support Coverage

                </label>


                <select
    name="support_coverage"
    id="support_coverage"
    class="form-select"
    required>

    <option value="">
        Select support coverage
    </option>

    <option value="Business Hours">
        Business Hours
    </option>

    <option value="Extended Hours">
        Extended Hours
    </option>

    <option value="24/7">
        24/7
    </option>

    <option value="Not Sure">
        Not Sure
    </option>

</select>

            </div>


            <!--
            |--------------------------------------------------------------------------
            | Contract Preference
            |--------------------------------------------------------------------------
            -->

            <div class="mb-3">


                <label
                    class="form-label"
                    for="contract_term">

                    Contract Preference

                </label>


                <select
    name="contract_term"
    class="form-select"
    required>

    <option value="">
        Select contract preference
    </option>

    <option value="Monthly">
        Monthly
    </option>

    <option value="Annual">
        Annual
    </option>

    <option value="Not Sure">
        Not Sure
    </option>

</select>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Start Timeframe
            |--------------------------------------------------------------------------
            -->

            <div class="mb-4">


                <label
                    class="form-label"
                    for="start_timeframe">

                    When would you like to start?

                </label>


                <select
                    name="start_timeframe"
                    id="start_timeframe"
                    class="form-select"
                    required>


                    <option value="">

                        Select timeframe

                    </option>


                   <option value="Immediately">
    Immediately
</option>

<option value="Within 30 Days">
    Within 30 Days
</option>

<option value="Within 3 Months">
    Within 3 Months
</option>

<option value="Just Exploring">
    Just Exploring
</option>


                </select>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Marketing Consent
            |--------------------------------------------------------------------------
            -->

            <div class="form-check mb-4">


                <input
                    class="form-check-input"
                    type="checkbox"
                    name="marketing_consent"
                    value="1"
                    id="marketingConsent">


                <label
                    class="form-check-label"
                    for="marketingConsent">

                    I would like to receive occasional updates and special offers.

                </label>


            </div>


            <!--
            |--------------------------------------------------------------------------
            | Submit
            |--------------------------------------------------------------------------
            -->

            <button
                type="submit"
                name="submit_contract_lead"
                class="btn btn-success btn-lg">

                I'm Interested

            </button>


        </form>


    </div>

</div>


<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>