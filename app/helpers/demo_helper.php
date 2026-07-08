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