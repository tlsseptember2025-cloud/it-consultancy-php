<?php

/*
|--------------------------------------------------------------------------
| DEMO CUSTOMER DASHBOARD
|--------------------------------------------------------------------------
| This page is ONLY for the fixed demo customer account.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_customer'])) {
    header('Location: ?page=demo-login');
    exit;
}

$demoCustomer = $_SESSION['demo_customer'];

require VIEW_PATH . '/layouts/header-public.php';

?>

<div class="container py-4">

    <!-- ================================================================
         HEADER
         ================================================================ -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">
                👤 Demo Customer Portal
            </h2>

            <p class="text-muted mb-0">
                Welcome,
                <strong>
                    <?= htmlspecialchars($demoCustomer['username']) ?>
                </strong>
            </p>
        </div>

        <a
            href="?page=demo-logout"
            class="btn btn-outline-danger">
            Logout
        </a>

    </div>


    <!-- ================================================================
         DEMO NOTICE
         ================================================================ -->

    <div class="alert alert-info shadow-sm">

        <strong>Demo Customer Account</strong>

        <p class="mb-0 mt-2">
            This is a temporary customer portal for demonstrating
            the customer-side features of the IT Consultancy Management
            System.
        </p>

    </div>


    <!-- ================================================================
         FEATURES
         ================================================================ -->

    <div class="row g-4">

        <!-- Service Requests -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        📋 Service Requests
                    </h5>

                    <p class="card-text text-muted">
                        Explore how customers can submit and track
                        service requests.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Consultation -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        📅 Consultation Scheduling
                    </h5>

                    <p class="card-text text-muted">
                        Explore consultation scheduling and appointment
                        management from the customer side.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Payments -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        💳 Payments
                    </h5>

                    <p class="card-text text-muted">
                        Explore customer payment and payment receipt
                        functionality.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Notifications -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        🔔 Notifications
                    </h5>

                    <p class="card-text text-muted">
                        Explore how customers can receive important
                        system notifications.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Profile -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        👤 Customer Profile
                    </h5>

                    <p class="card-text text-muted">
                        Explore the customer-facing account experience.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Support -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        💬 Support
                    </h5>

                    <p class="card-text text-muted">
                        Explore customer communication and support
                        functionality.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================================
         DEMO REMINDER
         ================================================================ -->

    <div class="alert alert-warning mt-4">

        <strong>Please remember:</strong>

        <ul class="mb-0 mt-2">

            <li>This is a temporary demo customer account.</li>

            <li>
                Demo activity is separate from normal customer accounts.
            </li>

            <li>
                Some actions may be restricted during the demonstration.
            </li>

        </ul>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>