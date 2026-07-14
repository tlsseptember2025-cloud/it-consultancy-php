<?php 
require dirname(__DIR__) . '/layouts/header-public.php';
require_once HELPER_PATH . '/email.php';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_contract_lead'])
) {

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

        trim($_POST['company_name']),
        trim($_POST['contact_person']),
        trim($_POST['email']),
        trim($_POST['phone']),
        $_POST['employees'] ?: null,
        trim($_POST['comments'])

    ]);

    sendContractLeadNotification(
    trim($_POST['company_name']),
    trim($_POST['contact_person']),
    trim($_POST['email']),
    trim($_POST['phone']),
    !empty($_POST['employees'])
        ? (int) $_POST['employees']
        : null,
    trim($_POST['comments'])
);

    $success =
        'Thank you for your interest! We will contact you shortly.';
}

?>

<div class="p-5 mb-4 bg-light rounded-3">

    <div class="container-fluid py-5">

        <h1 class="display-5 fw-bold">
            <?= PRODUCT_NAME ?>
        </h1>

        <p class="col-md-8 fs-4">
            Manage customers, services, requests, invoices,
            payments, consultations and more from one platform.
        </p>

        <a class="btn btn-primary btn-lg"
           href="?page=services">

            Explore Demo

        </a>

    </div>

</div>

<?php if (isDemo()): ?>

<div id="demo" class="card border-primary shadow-sm mb-5">

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
                        href="?page=customer-login"
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

<?php endif; ?>

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

<?php

$leadCount = $pdo->query("
    SELECT COUNT(*)
    FROM contract_leads
    WHERE status = 'New'
")->fetchColumn();

?>

<?php if (!empty($success)): ?>

    <div class="alert alert-success">

        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>

<div class="card shadow-sm mt-5">

    <div class="card-body">

        <h3 class="mb-3">
            🏢 Business IT Support Plans
        </h3>

        <p>
            Looking for reliable monthly or annual IT support for your company?
            Complete the form below and we'll contact you with a tailored support solution.
        </p>

        <form method="POST">

            <div class="row">

                <div class="col-md-6 mb-3">
                    <input
                        type="text"
                        name="company_name"
                        class="form-control"
                        placeholder="Company Name"
                        required>
                </div>

                <div class="col-md-6 mb-3">
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
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="Email Address"
                        required>
                </div>

                <div class="col-md-6 mb-3">
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        placeholder="Phone Number">
                </div>

            </div>

            <div class="mb-3">

                <input
                    type="number"
                    name="employees"
                    class="form-control"
                    placeholder="Approximate Number of Employees">

            </div>

            <div class="mb-3">

                <textarea
                    name="comments"
                    class="form-control"
                    rows="4"
                    placeholder="Tell us about your IT support needs"></textarea>

            </div>

            <button
                type="submit"
                name="submit_contract_lead"
                class="btn btn-success">

                I'm Interested

            </button>

        </form>

    </div>

</div>

<?php require dirname(__DIR__) . '/layouts/footer.php';?>