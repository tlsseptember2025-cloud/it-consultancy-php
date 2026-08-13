<?php

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

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="card border-primary shadow-sm mb-5">

    <div class="card-body">

        <h3 class="text-primary mb-3">
            🖥 Welcome to the Demo Portal
        </h3>

        <p>
            Explore the complete IT Consultancy Management System using the demo accounts below.
        </p>

        <div class="row">

            <div class="col-md-6">

                <div class="border rounded p-3 mb-3">

                    <h5>🛠 Administrator Portal</h5>

                    <p class="mb-1">
                        <strong>Username:</strong> administrator
                    </p>

                    <p>
                        <strong>Password:</strong> demo
                    </p>

                    <a
                        href="?page=login"
                        class="btn btn-primary">

                        Admin Login

                    </a>

                </div>

            </div>

            <div class="col-md-6">

                <div class="border rounded p-3 mb-3">

                    <h5>👤 Customer Portal</h5>

                    <p class="mb-1">
                        <strong>Email:</strong>
                        customer@demo.ramiphp.com
                    </p>

                    <p>
                        <strong>Password:</strong> demo
                    </p>

                    <a
                        href="?page=public-login"
                        class="btn btn-success">

                        Customer Login

                    </a>

                </div>

            </div>

        </div>

        <hr>

        <h5>What You Can Explore</h5>

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
                    <li>✅ Payments & Refunds</li>
                    <li>✅ Notifications</li>
                    <li>✅ Reports & Administration</li>

                </ul>

            </div>

        </div>

        <div class="alert alert-info mb-0">

            <strong>Demo Information</strong>

            <br><br>

            This online demonstration includes most features of the
            IT Consultancy Management System.

            <br><br>

            For security reasons, some actions such as deleting records,
            sending live emails, and changing system settings are disabled.

            <br><br>

            Demo data may be refreshed periodically.

        </div>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>