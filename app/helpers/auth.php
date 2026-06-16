<?php

function requireCustomerLogin(): void
{
    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=customer-login');
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