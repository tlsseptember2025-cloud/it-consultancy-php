<!DOCTYPE html>
<html>

<head>

    <title>IT Consultancy</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a class="navbar-brand" href="?page=home">
            IT Consultancy
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <div class="navbar-nav ms-auto">

                <?php if (isset($_SESSION['user'])): ?>

                    <!-- ADMIN MENU -->

                    <a class="nav-link" href="?page=dashboard">
                        Dashboard
                    </a>

                    <a class="nav-link" href="?page=messages">
                        Messages
                    </a>

                    <a class="nav-link" href="?page=customers">
                        Customers
                    </a>

                    <a class="nav-link" href="?page=requests">
                        Requests
                    </a>

                     <a class="nav-link" href="?page=consultation-slots">
                        Consultation Slots
                    </a>

                    <a class="nav-link" href="?page=payments">
                        Payments
                    </a>

                    <a class="nav-link" href="?page=services-admin">
                        Manage Services
                    </a>

                    <a href="?page=deposit-slips" class="nav-link">
                        Deposit Slips
                    </a>

                    <a class="nav-link" href="?page=refunds">
                        Refunds
                    </a>

                    <a class="nav-link" href="?page=backup">
                        Database Backup
                    </a>

                    <a class="nav-link text-danger" href="?page=logout">
                        Logout
                    </a>

                <?php elseif (isset($_SESSION['customer'])): ?>

                    <!-- CUSTOMER MENU -->

                    <a class="nav-link" href="?page=customer-dashboard">
                        Dashboard
                    </a>

                    <a class="nav-link" href="?page=customer-requests">
                        My Requests
                    </a>

                    <a class="nav-link"
                        href="?page=customer-request-service">
                        Request Service
                    </a>

                    <a class="nav-link" href="?page=customer-payments">
                        My Payments
                    </a>

                    <a class="nav-link" href="?page=customer-refunds">
                        My Refunds
                    </a>

                    <a class="nav-link" href="?page=customer-upload-slip">
                        Upload Slip
                    </a>

                    <a class="nav-link text-danger"
                       href="?page=customer-logout">
                        Logout
                    </a>

                <?php else: ?>

                    <!-- PUBLIC MENU -->

                    <a class="nav-link" href="?page=home">
                        Home
                    </a>

                    <a class="nav-link" href="?page=services">
                        Services
                    </a>

                    <a class="nav-link" href="?page=contact">
                        Contact
                    </a>

                    <a class="nav-link" href="?page=customer-register">
                        Register
                    </a>

                    <a class="nav-link" href="?page=customer-login">
                        Customer Login
                    </a>

                    <a class="nav-link" href="?page=login">
                        Admin Login
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>

<div class="container py-4">