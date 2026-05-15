<!DOCTYPE html>
<html>
<head>

    <title>IT Consultancy</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">

    <div class="container-fluid">

        <a class="navbar-brand" href="?page=home">
            IT Consultancy
        </a>

        <div class="navbar-nav">

            <a class="nav-link" href="?page=home">
                Home
            </a>

            <a class="nav-link" href="?page=services">
                Services
            </a>

            <a class="nav-link" href="?page=contact">
                Contact
            </a>

            <?php if (isset($_SESSION['user'])): ?>

                <a class="nav-link" href="?page=admin">
                    Admin
                </a>

                <a class="nav-link text-danger" href="?page=logout">
                    Logout
                </a>

            <?php else: ?>

                <a class="nav-link" href="?page=login">
                    Login
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<hr>