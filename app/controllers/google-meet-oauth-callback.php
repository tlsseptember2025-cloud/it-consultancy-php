<?php

/**
 * Google Meet OAuth Callback
 *
 * Handles the OAuth callback from Google and stores
 * the Google Meet refresh token for the application.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once CONFIG_PATH . '/database.php';

/*
|--------------------------------------------------------------------------
| Check OAuth State
|--------------------------------------------------------------------------
*/

$state = $_GET['state'] ?? '';

if (
    empty($state) ||
    empty($_SESSION['google_meet_oauth_state']) ||
    !hash_equals(
        $_SESSION['google_meet_oauth_state'],
        $state
    )
) {
    unset($_SESSION['google_meet_oauth_state']);

    http_response_code(400);
    exit('Invalid OAuth state.');
}

/*
|--------------------------------------------------------------------------
| State Is One-Time Use
|--------------------------------------------------------------------------
*/

unset($_SESSION['google_meet_oauth_state']);

/*
|--------------------------------------------------------------------------
| Check Google OAuth Error
|--------------------------------------------------------------------------
*/

if (!empty($_GET['error'])) {

    $error = htmlspecialchars(
        (string) $_GET['error'],
        ENT_QUOTES,
        'UTF-8'
    );

    exit('Google OAuth authorization failed: ' . $error);
}

/*
|--------------------------------------------------------------------------
| Authorization Code
|--------------------------------------------------------------------------
*/

$code = $_GET['code'] ?? '';

if (empty($code)) {
    http_response_code(400);
    exit('Google authorization code was not provided.');
}

/*
|--------------------------------------------------------------------------
| Google OAuth Configuration
|--------------------------------------------------------------------------
*/

$env = parse_ini_file(dirname(__DIR__, 2) . '/.env');

if ($env === false) {
    http_response_code(500);
    exit('Unable to load application configuration.');
}

$clientId = $env['GOOGLE_MEET_CLIENT_ID'] ?? '';
$clientSecret = $env['GOOGLE_MEET_CLIENT_SECRET'] ?? '';
$redirectUri = $env['GOOGLE_MEET_REDIRECT_URI'] ?? '';

if (
    empty($clientId) ||
    empty($clientSecret) ||
    empty($redirectUri)
) {
    http_response_code(500);
    exit('Google Meet OAuth configuration is incomplete.');
}

/*
|--------------------------------------------------------------------------
| Exchange Authorization Code For Tokens
|--------------------------------------------------------------------------
*/

$tokenUrl = 'https://oauth2.googleapis.com/token';

$postFields = http_build_query([
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
]);

$ch = curl_init($tokenUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($response === false || !empty($curlError)) {
    http_response_code(500);
    exit('Unable to communicate with Google OAuth.');
}

$tokenData = json_decode($response, true);

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($tokenData)
) {
    http_response_code(500);
    exit('Google OAuth token exchange failed.');
}

/*
|--------------------------------------------------------------------------
| Refresh Token
|--------------------------------------------------------------------------
*/

$refreshToken = $tokenData['refresh_token'] ?? '';

if (empty($refreshToken)) {
    http_response_code(500);
    exit(
        'Google did not return a refresh token. ' .
        'The Google account may already have an existing authorization.'
    );
}

/*
|--------------------------------------------------------------------------
| Get Google Account Email
|--------------------------------------------------------------------------
|
| We use the OAuth userinfo endpoint only to record
| which Google account is connected.
|
*/

$accessToken = $tokenData['access_token'] ?? '';
$accountEmail = null;

if (!empty($accessToken)) {

    $userinfoCh = curl_init(
        'https://www.googleapis.com/oauth2/v3/userinfo'
    );

    curl_setopt_array($userinfoCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $userinfoResponse = curl_exec($userinfoCh);

    curl_close($userinfoCh);

    if ($userinfoResponse !== false) {

        $userinfo = json_decode(
            $userinfoResponse,
            true
        );

        if (is_array($userinfo)) {
            $accountEmail = $userinfo['email'] ?? null;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Save OAuth Connection
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare("
    INSERT INTO google_meet_oauth
        (
            provider,
            account_email,
            refresh_token
        )
    VALUES
        (
            'google_meet',
            :account_email,
            :refresh_token
        )
    ON DUPLICATE KEY UPDATE
        account_email = VALUES(account_email),
        refresh_token = VALUES(refresh_token),
        updated_at = CURRENT_TIMESTAMP
");

$statement->execute([
    ':account_email' => $accountEmail,
    ':refresh_token' => $refreshToken,
]);

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Meet Connected</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            padding: 40px;
        }

        .box {
            max-width: 600px;
            margin: 60px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
        }

        .success {
            color: #198754;
            font-weight: 600;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="box">

    <h1>Google Meet Connected</h1>

    <p class="success">
        Google Meet has been successfully connected.
    </p>

    <p>
        The system can now use this Google account
        to create Google Meet spaces automatically.
    </p>

    <a href="?page=dashboard">
        Return to Dashboard
    </a>

</div>

</body>
</html>
';