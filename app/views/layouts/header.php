<!DOCTYPE html>
<html>
<head>
    <title>IT Consultancy</title>
</head>
<body>

<nav>
    <a href="?page=home">Home</a> |
    <a href="?page=services">Services</a> |
    <a href="?page=contact">Contact</a> |

    <?php if (isset($_SESSION['user'])): ?>
        <a href="?page=admin">Admin</a> |
        <a href="?page=logout">Logout</a>
    <?php else: ?>
        <a href="?page=login">Login</a>
    <?php endif; ?>
</nav>

<hr>