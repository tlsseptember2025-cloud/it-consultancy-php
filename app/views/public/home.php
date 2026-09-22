<?php

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/GuestChatHelper.php';

$guestChatAvailability = getGuestChatAvailability($pdo);

require dirname(__DIR__) . '/layouts/header-public.php';

?>

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
    WAHBIB CONSULTATION LLC

</p>

<a
    href="?page=demo-request"
    class="btn btn-primary btn-lg">

    Request a Demo

</a>


 <a
        class="btn btn-outline-primary btn-lg"
        href="?page=demo-login">

        Demo Login

    </a>


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
| Live Chat
|--------------------------------------------------------------------------
-->

<div class="card shadow-sm mt-5 mb-5">

    <div class="card-body p-4">

        <div class="text-center">

            <h3 class="mb-3">
                💬 Live Support Chat
            </h3>

            <?php if ($guestChatAvailability['available']): ?>

                <p class="text-muted mb-4">
                    Need help or have a question?
                    Chat directly with our support team.
                </p>

                <a
                    href="?page=guest-chat"
                    class="btn btn-primary btn-lg px-4">

                    Start Live Chat

                </a>

                <div class="mt-3 text-muted small">
                    Available during office hours when at least one Admin is online.
                </div>

            <?php else: ?>

                <div
                    class="border rounded p-4 text-start"
                    style="background:#fff8e1;">

                    <div class="d-flex align-items-center mb-3">

                        <span
                            class="badge bg-danger fs-6 me-2 px-3 py-2">

                            Unavailable

                        </span>

                        <h4 class="mb-0">

                            Live Chat is currently unavailable

                        </h4>

                    </div>


                    <div class="alert alert-warning mb-4">

                        <strong>Why can't I start a chat?</strong>

                        <div class="mt-1">

                            <?php if (!$guestChatAvailability['office_open']): ?>

                                Our office is currently outside its configured working hours.

                            <?php elseif (!$guestChatAvailability['admin_online']): ?>

                                Our office is currently open, but no Admin is online.

                            <?php endif; ?>

                        </div>

                    </div>


                    <h5 class="mb-3">
                        Live Chat is available when BOTH conditions are met:
                    </h5>


                    <div class="row g-3 mb-4">

                        <div class="col-md-6">

                            <div class="border rounded p-3 h-100 bg-white">

                                <h6 class="fw-bold mb-2">

                                    🕐 Office Hours

                                </h6>

                                <div class="small">

                                    <div>
                                        <strong>Monday–Thursday:</strong>
                                        10:00 AM – 4:00 PM
                                    </div>

                                    <div>
                                        <strong>Friday:</strong>
                                        10:00 AM – 12:00 PM
                                    </div>

                                    <div>
                                        <strong>Saturday–Sunday:</strong>
                                        Closed
                                    </div>

                                </div>

                                <div class="mt-2">

                                    <?php if ($guestChatAvailability['office_open']): ?>

                                        <span class="badge bg-success">
                                            Office currently open
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Office currently closed
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="border rounded p-3 h-100 bg-white">

                                <h6 class="fw-bold mb-2">

                                    👤 Admin Availability

                                </h6>

                                <div class="small">

                                    At least one Admin must be logged in
                                    and currently active.

                                </div>

                                <div class="mt-2">

                                    <?php if ($guestChatAvailability['admin_online']): ?>

                                        <span class="badge bg-success">
                                            Admin currently online
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            No Admin currently online
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="text-center">

                        <button
                            type="button"
                            class="btn btn-secondary btn-lg px-4"
                            disabled>

                            Live Chat Unavailable

                        </button>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php

require dirname(__DIR__) . '/layouts/footer.php';

?>
