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
            Welcome to IT Consultancy
        </h1>

        <p class="col-md-8 fs-4">
            We provide professional IT consulting,
            web development, system administration,
            and technical support solutions.
        </p>

        <a class="btn btn-primary btn-lg"
           href="?page=services">

            View Our Services

        </a>

    </div>

</div>

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