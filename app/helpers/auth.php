<?php

function requireCustomerLogin(): void
{
    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;

    }
}

function requireAdminLogin(): void
{
    if (
        !isset($_SESSION['user']) &&
        !isset($_SESSION['demo_user'])
    ) {
        header('Location: ?page=login');
        exit;
    }
}

function clearRoleSessions(): void
{
    unset($_SESSION['user']);
    unset($_SESSION['customer']);
    unset($_SESSION['agent']);

    // Demo sessions
    unset($_SESSION['demo_user']);
    unset($_SESSION['demo_customer']);
    unset($_SESSION['demo_agent']);
    unset($_SESSION['demo_super_admin']);
}