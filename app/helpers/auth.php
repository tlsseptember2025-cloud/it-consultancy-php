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
    if (!isset($_SESSION['user'])) {

        header('Location: ?page=login');
        exit;

    }
}

function clearRoleSessions(): void
{
    unset($_SESSION['user']);
    unset($_SESSION['customer']);
    unset($_SESSION['agent']);
}