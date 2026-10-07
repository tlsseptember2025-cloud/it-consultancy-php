<?php

/**
 * Google Meet OAuth Authorization
 *
 * Starts the Google OAuth authorization flow.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Require Main Admin Login
|--------------------------------------------------------------------------
*/

require_once HELPER_PATH . '/auth.php';

if (isset($_SESSION['demo_user']) || isset($_SESSION['demo_super_admin'])) {
    header('Location: ?page=demo-super-admin-dashboard');
    exit;
}

if (!isset($_SESSION['user'])) {
    header('Location: ?page=login');
    exit;
}

requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Load Environment Configuration
|--------------------------------------------------------------------------
*/

$env = parse_ini_file(dirname(__DIR__, 2) . '/.env');

if ($env === false) {
    http_response_code(500);
    exit('Unable to load application configuration.');
}

$clientId = $env['GOOGLE_MEET_CLIENT_ID'] ?? '';
$redirectUri = $env['GOOGLE_MEET_REDIRECT_URI'] ?? '';

if (empty($clientId) || empty($redirectUri)) {
    http_response_code(500);
    exit('Google Meet OAuth configuration is incomplete.');
}

/*
|--------------------------------------------------------------------------
| Generate OAuth State
|--------------------------------------------------------------------------
|
| This protects the OAuth callback against CSRF.
|
*/

$state = bin2hex(random_bytes(32));

$_SESSION['google_meet_oauth_state'] = $state;

/*
|--------------------------------------------------------------------------
| Google OAuth Authorization URL
|--------------------------------------------------------------------------
*/

$authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' .
    http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/meetings.space.created',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ]);

/*
|--------------------------------------------------------------------------
| Redirect To Google
|--------------------------------------------------------------------------
*/

header('Location: ' . $authorizationUrl);
exit;