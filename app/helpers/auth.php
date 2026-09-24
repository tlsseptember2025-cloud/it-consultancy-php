<?php

/**
 * Require Main Customer Login
 */
function requireCustomerLogin(): void
{
    if (!isset($_SESSION['customer'])) {

        header('Location: ?page=public-login');
        exit;

    }
}

/**
 * Require Customer Normal Access
 *
 * Suspended customers are not allowed to continue into
 * normal customer pages.
 *
 * They must use the dedicated suspension communication page.
 *
 * IMPORTANT:
 * - This does NOT replace requireCustomerLogin().
 * - The suspension chat page must NOT call this function.
 * - Existing Admin/Agent authentication is unchanged.
 */
function requireCustomerNormalAccess(): void
{
    requireCustomerLogin();

    $customerStatus = $_SESSION['customer']['status'] ?? 'Active';

    if ($customerStatus === 'Suspended') {

        header('Location: ?page=customer-suspension-chat');
        exit;

    }
}

/**
 * Require Demo Customer Normal Access
 *
 * Suspended Demo customers are redirected to the
 * dedicated suspension communication page.
 *
 * The suspension chat page itself should use
 * requireDemoCustomer() instead of this function.
 */
function requireDemoCustomerNormalAccess(): void
{
    requireDemoCustomer();

    $customerStatus = $_SESSION['demo_customer']['status'] ?? 'Active';

    if ($customerStatus === 'Suspended') {

        header('Location: ?page=customer-suspension-chat');
        exit;

    }
}

/**
 * Require Main Admin Login
 *
 * Main/Dev Admin:
 * - Requires $_SESSION['user']
 *
 * Demo Admin:
 * - Requires $_SESSION['demo_user']
 *
 * The Demo Admin session is checked first so the shared
 * Admin pages work correctly even when the Demo application
 * is being tested locally.
 */
function requireAdminLogin(): void
{
    /**
     * Demo Admin session takes priority.
     *
     * Demo Admin uses the same Admin pages as Main/Dev,
     * but its authentication session is stored separately.
     */
    if (isset($_SESSION['demo_user'])) {
        return;
    }

    /**
     * Demo environment without a Demo Admin session.
     */
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');

    $isDemoEnvironment = (
        $host === 'demo.wahbibconsultancy.com'
    );

    if ($isDemoEnvironment) {

        header('Location: ?page=demo-login');
        exit;

    }

    /**
     * Main/Dev Admin authentication.
     */
    if (!isset($_SESSION['user'])) {

        header('Location: ?page=login');
        exit;

    }
}

/**
 * Require Demo Login
 *
 * Allows any authenticated Demo role:
 * - Super Admin
 * - Admin
 * - Customer
 * - Agent
 */
function requireDemoLogin(): void
{
    if (
        !isset($_SESSION['demo_super_admin']) &&
        !isset($_SESSION['demo_user']) &&
        !isset($_SESSION['demo_customer']) &&
        !isset($_SESSION['demo_agent'])
    ) {

        header('Location: ?page=demo-login');
        exit;

    }
}

/**
 * Require Demo Super Admin
 */
function requireDemoSuperAdmin(): void
{
    if (!isset($_SESSION['demo_super_admin'])) {

        header('Location: ?page=demo-login');
        exit;

    }
}

/**
 * Require Demo Admin
 */
function requireDemoAdmin(): void
{
    if (!isset($_SESSION['demo_user'])) {

        header('Location: ?page=demo-login');
        exit;

    }
}

/**
 * Require Demo Customer
 */
function requireDemoCustomer(): void
{
    if (!isset($_SESSION['demo_customer'])) {

        header('Location: ?page=demo-login');
        exit;

    }
}

/**
 * Require Demo Agent
 */
function requireDemoAgent(): void
{
    if (!isset($_SESSION['demo_agent'])) {

        header('Location: ?page=demo-login');
        exit;

    }
}

/**
 * Clear All Role Sessions
 */
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