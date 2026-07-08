<?php require __DIR__ . '/layouts/header.php'; 
require_once __DIR__ . '/../helpers/email.php'; ?>

<?php
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
            IT Consultancy Management System
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

<div class="row">

    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Web Development</h4>

                <p>
                    Modern responsive websites and web applications.
                </p>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>IT Support</h4>

                <p>
                    Reliable support and infrastructure management.
                </p>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <h4>Softare Installation</h4>

                <p>
                    Professional software installation service provided for quick and hassle-free setup.
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

<?php require __DIR__ . '/layouts/footer.php'; ?>