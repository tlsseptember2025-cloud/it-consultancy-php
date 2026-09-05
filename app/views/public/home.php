<?php 

require_once HELPER_PATH . '/email.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_contract_lead'])
) {

    /*
    |--------------------------------------------------------------------------
    | Basic Form Values
    |--------------------------------------------------------------------------
    */

    $companyName = trim($_POST['company_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $companySize = isset($_POST['company_size'])
    ? (int) $_POST['company_size']
    : 0;
    $contractPreference = trim($_POST['contract_preference'] ?? '');

    $serviceInterest = $_POST['service_interest'] ?? [];

    if (!is_array($serviceInterest)) {
        $serviceInterest = [];
    }

    $itServices = $_POST['it_services'] ?? [];

    if (!is_array($itServices)) {
        $itServices = [];
    }

    $websiteServices = $_POST['website_services'] ?? [];

    if (!is_array($websiteServices)) {
        $websiteServices = [];
    }

    $additionalComments = trim($_POST['comments'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Build Structured Lead Information
    |--------------------------------------------------------------------------
    |
    | We keep using the existing "comments" database field.
    |
    */

    $leadInformation = [];

    if (!empty($serviceInterest)) {

        $leadInformation[] =
            "Service Interest:\n- "
            . implode("\n- ", array_map('trim', $serviceInterest));

    }

    if (!empty($itServices)) {

        $leadInformation[] =
            "IT Support / Software Services:\n- "
            . implode("\n- ", array_map('trim', $itServices));

    }

    if (!empty($websiteServices)) {

        $leadInformation[] =
            "Website Services:\n- "
            . implode("\n- ", array_map('trim', $websiteServices));

    }

    if ($companySize > 0) {

    $companySizeLabel = match ($companySize) {

        5   => '1–5 employees',
        10  => '6–10 employees',
        25  => '11–25 employees',
        50  => '26–50 employees',
        100 => '51–100 employees',
        101 => '100+ employees',

        default => 'Not specified'

    };

    $leadInformation[] =
        "Company Size: " . $companySizeLabel;

}

    if ($contractPreference !== '') {

        $leadInformation[] =
            "Contract Preference: " . $contractPreference;

    }

    if ($additionalComments !== '') {

        $leadInformation[] =
            "Additional Information:\n" . $additionalComments;

    }

    $comments = implode("\n\n", $leadInformation);


    /*
    |--------------------------------------------------------------------------
    | Save Lead
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO contract_leads
        (
            company_name,
            contact_person,
            email,
            phone,
            employees,
            comments
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([

        $companyName,
        $contactPerson,
        $email,
        $phone,
        $companySize > 0 ? $companySize : null,
        $comments

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
        $companySize > 0
    ? $companySize
    : null,
        $comments
    );


    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $success =
        'Thank you for your interest! We will contact you shortly.';
}


require dirname(__DIR__) . '/layouts/header-public.php';

?>


<?php if (!empty($success)): ?>

    <div class="alert alert-success alert-dismissible fade show" role="alert">

        <?= htmlspecialchars($success) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

<?php endif; ?>


<!-- ====================================================================== -->
<!-- HERO -->
<!-- ====================================================================== -->

<div class="p-5 mb-4 bg-light rounded-3">

    <div class="container-fluid py-5">

        <h1 class="display-5 fw-bold">
            <?= PRODUCT_NAME ?>
        </h1>

        <p class="col-md-8 fs-4">
            Manage customers, services, requests, invoices,
            payments, consultations and more from one platform.
        </p>

        <a
            class="btn btn-primary btn-lg"
            href="?page=demo">

            Explore Demo

        </a>

    </div>

</div>


<!-- ====================================================================== -->
<!-- FEATURES -->
<!-- ====================================================================== -->

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


<!-- ====================================================================== -->
<!-- OTHER FEATURES -->
<!-- ====================================================================== -->

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


<!-- ====================================================================== -->
<!-- CONTRACT LEAD FORM -->
<!-- ====================================================================== -->

<div class="card shadow-sm mt-5 mb-5">

    <div class="card-body p-4 p-md-5">

        <div class="text-center mb-5">

            <h2 class="fw-bold">
                🏢 Business IT & Website Services
            </h2>

            <p class="text-muted mb-0">

                Looking for reliable ongoing IT support,
                software services, website services,
                or a combination of both?

            </p>

        </div>


        <form method="POST">


            <!-- ============================================================ -->
            <!-- SERVICE CATEGORY -->
            <!-- ============================================================ -->

            <div class="mb-4">

                <label class="form-label fw-semibold">

                    What type of service are you interested in?

                </label>

                <div class="row g-3">


                    <div class="col-md-6">

                        <div class="form-check border rounded p-3 h-100">

                            <input
                                class="form-check-input service-category"
                                type="checkbox"
                                name="service_interest[]"
                                value="IT Support / Software Services"
                                id="interestIT">

                            <label
                                class="form-check-label fw-semibold"
                                for="interestIT">

                                💻 IT Support / Software Services

                            </label>

                            <div class="small text-muted mt-1">

                                Ongoing technical support, software and IT management.

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="form-check border rounded p-3 h-100">

                            <input
                                class="form-check-input service-category"
                                type="checkbox"
                                name="service_interest[]"
                                value="Website Services"
                                id="interestWebsite">

                            <label
                                class="form-check-label fw-semibold"
                                for="interestWebsite">

                                🌐 Website Services

                            </label>

                            <div class="small text-muted mt-1">

                                Website development, maintenance and related services.

                            </div>

                        </div>

                    </div>


                </div>

            </div>


            <!-- ============================================================ -->
            <!-- IT SERVICES -->
            <!-- ============================================================ -->

            <div
                id="itServicesSection"
                class="card border-primary mb-4 d-none">

                <div class="card-header bg-primary text-white">

                    <strong>
                        💻 IT Support / Software Services
                    </strong>

                </div>

                <div class="card-body">

                    <p class="text-muted">

                        Select all services you are interested in:

                    </p>


                    <div class="row g-3">


                        <?php

                        $itOptions = [

                            'Remote IT Support',
                            'On-site IT Support',
                            'Microsoft 365 / Email Support',
                            'Computer & Laptop Support',
                            'Network & Wi-Fi Support',
                            'Software Installation & Setup',
                            'Backup & Data Protection',
                            'Cybersecurity',
                            'Server / Infrastructure Support',
                            'Other IT Services'

                        ];

                        ?>


                        <?php foreach ($itOptions as $index => $option): ?>

                            <div class="col-md-6">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="it_services[]"
                                        value="<?= htmlspecialchars($option) ?>"
                                        id="itService<?= $index ?>">

                                    <label
                                        class="form-check-label"
                                        for="itService<?= $index ?>">

                                        <?= htmlspecialchars($option) ?>

                                    </label>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    </div>

                </div>

            </div>


            <!-- ============================================================ -->
            <!-- WEBSITE SERVICES -->
            <!-- ============================================================ -->

            <div
                id="websiteServicesSection"
                class="card border-success mb-4 d-none">

                <div class="card-header bg-success text-white">

                    <strong>
                        🌐 Website Services
                    </strong>

                </div>

                <div class="card-body">

                    <p class="text-muted">

                        Select all website services you are interested in:

                    </p>


                    <div class="row g-3">


                        <?php

                        $websiteOptions = [

                            'New Website',
                            'Website Redesign',
                            'E-commerce Website',
                            'Website Maintenance',
                            'Website Hosting / Management',
                            'SEO',
                            'Other Website Services'

                        ];

                        ?>


                        <?php foreach ($websiteOptions as $index => $option): ?>

                            <div class="col-md-6">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="website_services[]"
                                        value="<?= htmlspecialchars($option) ?>"
                                        id="websiteService<?= $index ?>">

                                    <label
                                        class="form-check-label"
                                        for="websiteService<?= $index ?>">

                                        <?= htmlspecialchars($option) ?>

                                    </label>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    </div>

                </div>

            </div>


            <!-- ============================================================ -->
            <!-- COMPANY SIZE -->
            <!-- ============================================================ -->

            <div class="mb-4">

    <label
        for="company_size"
        class="form-label fw-semibold">

        Approximate Company Size

    </label>

    <select
        name="company_size"
        id="company_size"
        class="form-select"
        required>

        <option value="">
            Select number of employees
        </option>

        <option value="5">
            1–5 employees
        </option>

        <option value="10">
            6–10 employees
        </option>

        <option value="25">
            11–25 employees
        </option>

        <option value="50">
            26–50 employees
        </option>

        <option value="100">
            51–100 employees
        </option>

        <option value="101">
            100+ employees
        </option>

    </select>

</div>

            <!-- ============================================================ -->
            <!-- CONTRACT -->
            <!-- ============================================================ -->

            <div class="mb-4">

                <label
                    for="contract_preference"
                    class="form-label fw-semibold">

                    Preferred Contract

                </label>

                <select
                    name="contract_preference"
                    id="contract_preference"
                    class="form-select"
                    required>

                    <option value="">
                        Select an option
                    </option>

                    <option value="Monthly">
                        Monthly
                    </option>

                    <option value="Annual">
                        Annual
                    </option>

                    <option value="Not Sure">
                        Not sure — I'd like to discuss the options
                    </option>

                </select>

            </div>


            <!-- ============================================================ -->
            <!-- CONTACT DETAILS -->
            <!-- ============================================================ -->

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label fw-semibold">

                        Company Name

                    </label>

                    <input
                        type="text"
                        name="company_name"
                        class="form-control"
                        placeholder="Company Name"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label fw-semibold">

                        Contact Person

                    </label>

                    <input
                        type="text"
                        name="contact_person"
                        class="form-control"
                        placeholder="Contact Person"
                        required>

                </div>


            </div>


            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label fw-semibold">

                        Email Address

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="Email Address"
                        required>

                </div>


                <div class="col-md-6 mb-3">

                    <label class="form-label fw-semibold">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        placeholder="Phone Number">

                </div>

            </div>

            <!-- ============================================================ -->
            <!-- SUBMIT -->
            <!-- ============================================================ -->

            <div class="text-center">

                <button
                    type="submit"
                    name="submit_contract_lead"
                    class="btn btn-success btn-lg px-5">

                    I'm Interested

                </button>

            </div>


        </form>

    </div>

</div>


<!-- ====================================================================== -->
<!-- DYNAMIC FORM JAVASCRIPT -->
<!-- ====================================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const interestIT =
        document.getElementById('interestIT');

    const interestWebsite =
        document.getElementById('interestWebsite');

    const itSection =
        document.getElementById('itServicesSection');

    const websiteSection =
        document.getElementById('websiteServicesSection');


    function updateServiceSections() {

        /*
        |--------------------------------------------------------------------------
        | IT Services
        |--------------------------------------------------------------------------
        */

        if (interestIT.checked) {

            itSection.classList.remove('d-none');

        } else {

            itSection.classList.add('d-none');

        }


        /*
        |--------------------------------------------------------------------------
        | Website Services
        |--------------------------------------------------------------------------
        */

        if (interestWebsite.checked) {

            websiteSection.classList.remove('d-none');

        } else {

            websiteSection.classList.add('d-none');

        }

    }


    interestIT.addEventListener(
        'change',
        updateServiceSections
    );


    interestWebsite.addEventListener(
        'change',
        updateServiceSections
    );


    updateServiceSections();

});

</script>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>