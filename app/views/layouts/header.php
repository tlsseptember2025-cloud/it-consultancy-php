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

        <a class="nav-link" href="?page=payments">
            Payments
        </a>

        <a class="nav-link" href="?page=services-admin">
            Manage Services
        </a>

        <a href="?page=backup" class="nav-link">
            Database Backup
        </a>

        <a class="nav-link text-danger" href="?page=logout">
            Logout
        </a>

    <?php else: ?>

        <a class="nav-link" href="?page=home">
            Home
        </a>

        <a class="nav-link" href="?page=services">
            Services
        </a>

        <a class="nav-link" href="?page=contact">
            Contact
        </a>

        <a class="nav-link" href="?page=login">
            Login
        </a>

    <?php endif; ?>

</div>

        </div>

    </div>

</nav>

<div class="container py-4">