<?php

require_once CONFIG_PATH . '/demo-database.php';

if (!isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-login');
    exit;
}

$superAdmin = $_SESSION['demo_super_admin'];

require dirname(__DIR__) . '/layouts/header-admin.php';

?>

<div class="container py-4">

    <div class="mb-4">

        <h1 class="mb-1">
            Demo Super Admin
        </h1>

        <p class="text-muted mb-0">
            Demo Environment Management
        </p>

    </div>

    <div class="alert alert-info">

        <strong>Welcome, Super Admin.</strong>

        <p class="mb-0 mt-2">
            This area is used to manage Demo companies,
            review Demo activity, troubleshoot Demo issues,
            and generate reports.
        </p>

    </div>

</div>