<?php

$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);

session_destroy();

if ($isDemoSuperAdmin) {

    header('Location: ?page=demo-super-admin-login');
    exit;
}

header('Location: ?page=home');
exit;