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

                    <a class="nav-link" href="?page=services-admin">
                        Services
                    </a>

                    <a class="nav-link" href="?page=customers">
                        Customers
                    </a>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

                            Requests

                        </a>

                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=requests">

                                    Current Requests

                                </a>

                            </li>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=archived-requests">

                                    Archived Requests

                                </a>

                            </li>

                        </ul>

                    </div>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

                            Scheduling

                        </a>

                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=consultation-slots">

                                    Consultation Slots

                                </a>

                            </li>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=service-slots">

                                    Service Slots

                                </a>

                            </li>

                        </ul>

                    </div>

                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

                            Finance

                        </a>

                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=payments">

                                    Payments

                                </a>

                            </li>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=refunds">

                                    Refunds

                                </a>

                            </li>

                        </ul>

                    </div>


                    <div class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown">

                            System

                        </a>

                        <ul class="dropdown-menu">

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=messages">

                                    Messages

                                </a>

                            </li>

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="?page=backup">

                                    Database Backup

                                </a>

                            </li>

                        </ul>

                    </div>
                   

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

                    <a class="nav-link" href="?page=customer-payments">
                        My Payments
                    </a>

                    <a class="nav-link" href="?page=customer-refunds">
                        My Refunds
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