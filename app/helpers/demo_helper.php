<?php

/**
 * Returns the current application mode.
 */
function appMode()
{
    return defined('APP_MODE') ? APP_MODE : 'production';
}

/**
 * Check if running in Development mode.
 */
function isDevelopment()
{
    return appMode() === 'development';
}

/**
 * Check if running in Demo mode.
 */
function isDemo()
{
    return appMode() === 'demo';
}

/**
 * Check if running in Production mode.
 */
function isProduction()
{
    return appMode() === 'production';
}


/**
 * Stop actions that are not allowed in Demo Mode.
 */
function blockDemoAction($message = null, $redirect = null)
{
    if (!isDemo()) {
        return;
    }

    $_SESSION['error'] = $message
        ?? 'This action is disabled in the online demo.';

    if ($redirect) {
        header("Location: $redirect");
        exit;
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    header('Location: ?page=dashboard');
    exit;
}