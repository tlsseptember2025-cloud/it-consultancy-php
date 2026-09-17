<?php

$isDemoSuperAdmin = isset($_SESSION['demo_super_admin']);
$isDemoAdmin = isset($_SESSION['demo_user']);

session_destroy();

if ($isDemoSuperAdmin) {
    header('Location: ?page=demo-super-admin-login');
    exit;
}

if ($isDemoAdmin) {
    header('Location: ?page=demo-login');
    exit;
}

header('Location: ?page=home');
exit;