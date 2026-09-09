<?php

/*
|--------------------------------------------------------------------------
| DEMO DASHBOARD
|--------------------------------------------------------------------------
| Temporary dashboard for approved demo users.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Require Demo Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['demo_user'])) {

    header('Location: ?page=demo-login');
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
| Load Current Demo User
|--------------------------------------------------------------------------
*/

$demoUserId = (int) ($_SESSION['demo_user']['id'] ?? 0);

if ($demoUserId <= 0) {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;

}


$stmt = $pdo->prepare("
    SELECT *
    FROM demo_users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $demoUserId
]);

$demoUser = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Account Validation
|--------------------------------------------------------------------------
*/

if (!$demoUser) {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login');
    exit;

}


/*
|--------------------------------------------------------------------------
| Check Account Status
|--------------------------------------------------------------------------
*/

if ($demoUser['status'] !== 'Active') {

    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login&expired=1');
    exit;

}


/*
|--------------------------------------------------------------------------
| Check Expiration
|--------------------------------------------------------------------------
*/

if (
    !empty($demoUser['expires_at'])
    && strtotime($demoUser['expires_at']) <= time()
) {

    $stmt = $pdo->prepare("
        UPDATE demo_users
        SET status = 'Expired'
        WHERE id = ?
    ");

    $stmt->execute([
        $demoUserId
    ]);


    unset($_SESSION['demo_user']);

    header('Location: ?page=demo-login&expired=1');
    exit;

}


/*
|--------------------------------------------------------------------------
| Refresh Session
|--------------------------------------------------------------------------
*/

$_SESSION['demo_user'] = $demoUser;


/*
|--------------------------------------------------------------------------
| Calculate Remaining Time
|--------------------------------------------------------------------------
*/

$expiresTimestamp = !empty($demoUser['expires_at'])
    ? strtotime($demoUser['expires_at'])
    : null;

$remainingSeconds = $expiresTimestamp
    ? max(0, $expiresTimestamp - time())
    : null;

$remainingDays = $remainingSeconds !== null
    ? ceil($remainingSeconds / 86400)
    : null;


/*
|--------------------------------------------------------------------------
| Public Header
|--------------------------------------------------------------------------
*/

require dirname(__DIR__) . '/layouts/header-public.php';

?>


<div class="container py-4">


    <!-- ================================================================
         HEADER
         ================================================================ -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">

                🖥 Demo Dashboard

            </h2>

            <p class="text-muted mb-0">

                Welcome,
                <strong>
                    <?= htmlspecialchars(
                        $demoUser['username']
                    ) ?>
                </strong>

            </p>

        </div>


        <div>

            <a
                href="?page=demo-logout"
                class="btn btn-outline-danger">

                Logout

            </a>

        </div>

    </div>


    <!-- ================================================================
         DEMO PERIOD
         ================================================================ -->

    <div class="alert alert-info">

        <strong>
            Demo Account
        </strong>

        <br><br>

        <?php if ($demoUser['first_login_at']): ?>

            Your demo period started on:

            <strong>
                <?= htmlspecialchars(
                    $demoUser['first_login_at']
                ) ?>
            </strong>

            <br>

            Your demo access expires on:

            <strong>
                <?= htmlspecialchars(
                    $demoUser['expires_at']
                ) ?>
            </strong>

            <?php if ($remainingDays !== null): ?>

                <br><br>

                <strong>
                    Approximately <?= $remainingDays ?> day(s) remaining.
                </strong>

            <?php endif; ?>

        <?php else: ?>

            Your demo period will begin after your
            first successful login.

        <?php endif; ?>

    </div>


    <!-- ================================================================
         WELCOME
         ================================================================ -->

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <h4 class="mb-3">

                Welcome to the IT Consultancy Management System

            </h4>

            <p class="mb-0">

                This temporary demo account allows you to explore
                selected features of the system without affecting
                normal customer, agent, or administrator accounts.

            </p>

        </div>

    </div>


    <!-- ================================================================
         DEMO FEATURES
         ================================================================ -->

    <div class="row g-4">


        <!-- CUSTOMER MANAGEMENT -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        👥 Customer Management

                    </h5>

                    <p class="card-text text-muted">

                        Explore how customers and their information
                        can be managed within the system.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- SERVICE REQUESTS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        📋 Service Requests

                    </h5>

                    <p class="card-text text-muted">

                        Explore the service request workflow and
                        request management process.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- CONSULTATIONS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        📅 Consultation Scheduling

                    </h5>

                    <p class="card-text text-muted">

                        Explore consultation scheduling and
                        appointment management.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- SERVICE JOBS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        🛠 Service Jobs

                    </h5>

                    <p class="card-text text-muted">

                        Explore how service jobs can be managed
                        from request to completion.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- PAYMENTS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        💳 Payments & Refunds

                    </h5>

                    <p class="card-text text-muted">

                        Explore payment and refund management
                        functionality.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- NOTIFICATIONS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        🔔 Notifications

                    </h5>

                    <p class="card-text text-muted">

                        Explore how notifications can keep users
                        informed about important events.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- CUSTOMER PORTAL -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        🌐 Customer Portal

                    </h5>

                    <p class="card-text text-muted">

                        Explore the customer-facing portal
                        experience.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- AGENT PORTAL -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        🧑‍💼 Agent Portal

                    </h5>

                    <p class="card-text text-muted">

                        Explore the agent-side workflow and
                        service management experience.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>


        <!-- REPORTS -->

        <div class="col-md-6 col-lg-4">

            <div class="card h-100 shadow-sm">

                <div class="card-body">

                    <h5 class="card-title">

                        📊 Reports & Administration

                    </h5>

                    <p class="card-text text-muted">

                        Explore selected administrative and
                        reporting concepts.

                    </p>

                    <span class="badge bg-secondary">

                        Demo Feature

                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================================
         DEMO NOTICE
         ================================================================ -->

    <div class="alert alert-warning mt-4">

        <strong>Please remember:</strong>

        <ul class="mb-0 mt-2">

            <li>
                This is a temporary demo account.
            </li>

            <li>
                Demo access expires after 5 days.
            </li>

            <li>
                Demo data is separate from normal system data.
            </li>

            <li>
                Some functionality may be restricted.
            </li>

        </ul>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>