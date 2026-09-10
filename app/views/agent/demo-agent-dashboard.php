<?php

/*
|--------------------------------------------------------------------------
| DEMO AGENT DASHBOARD
|--------------------------------------------------------------------------
| This page is ONLY for the fixed demo agent account.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_agent'])) {
    header('Location: ?page=demo-login');
    exit;
}

$demoAgent = $_SESSION['demo_agent'];

require VIEW_PATH . '/layouts/header-public.php';

?>

<div class="container py-4">

    <!-- ================================================================
         HEADER
         ================================================================ -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">
                🧑‍💼 Demo Agent Portal
            </h2>

            <p class="text-muted mb-0">
                Welcome,
                <strong>
                    <?= htmlspecialchars($demoAgent['username']) ?>
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

        <strong>Demo Agent Account</strong>

        <p class="mb-0 mt-2">
            This is a temporary agent portal for demonstrating
            the agent-side features of the IT Consultancy Management
            System.
        </p>

    </div>


    <!-- ================================================================
         FEATURES
         ================================================================ -->

    <div class="row g-4">

        <!-- Assigned Jobs -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        🛠 Service Jobs
                    </h5>

                    <p class="card-text text-muted">
                        Explore how agents can view and manage assigned
                        service jobs.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Consultations -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        📅 Consultations
                    </h5>

                    <p class="card-text text-muted">
                        Explore consultation appointments assigned to
                        an agent.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Customers -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        👥 Customers
                    </h5>

                    <p class="card-text text-muted">
                        Explore the agent-side customer information
                        experience.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Job Updates -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        📝 Job Updates
                    </h5>

                    <p class="card-text text-muted">
                        Explore how agents can update the progress and
                        outcome of service jobs.
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
                        Explore agent notifications for important
                        assignments and events.
                    </p>

                    <span class="badge bg-secondary">
                        Demo Feature
                    </span>

                </div>

            </div>

        </div>


        <!-- Agent Profile -->

        <div class="col-lg-4 col-md-6">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">
                        👤 Agent Profile
                    </h5>

                    <p class="card-text text-muted">
                        Explore the agent-facing account experience.
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

            <li>This is a temporary demo agent account.</li>

            <li>
                Demo activity is separate from normal agent accounts.
            </li>

            <li>
                Some actions may be restricted during the demonstration.
            </li>

        </ul>

    </div>

</div>

<?php require VIEW_PATH . '/layouts/footer.php'; ?>