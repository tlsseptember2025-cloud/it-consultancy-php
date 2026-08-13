<?php 

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
           href="?page=demo">

            Explore Demo

        </a>

    </div>

</div>



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