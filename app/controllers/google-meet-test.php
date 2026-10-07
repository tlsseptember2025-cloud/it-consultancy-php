<?php

/**
 * Google Meet API Test
 *
 * Creates a new Google Meet space using the stored OAuth connection.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once CONFIG_PATH . '/database.php';
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

$csrfToken = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Confirm Google Meet API Test</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 650px;">
            <div class="card-body">
                <h4 class="mb-3">Confirm Google Meet API Test</h4>
                <p>This test creates an external meeting using the configured integration.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-primary">Create Test Google Meet</button>
                    <a href="?page=dashboard" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if (
    !isset($_POST['csrf_token'])
    || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid security token.');
}


/*
|--------------------------------------------------------------------------
| Load Google Configuration
|--------------------------------------------------------------------------
*/

$env = parse_ini_file(dirname(__DIR__, 2) . '/.env');

if ($env === false) {
    http_response_code(500);
    exit('Unable to load application configuration.');
}

$clientId = $env['GOOGLE_MEET_CLIENT_ID'] ?? '';
$clientSecret = $env['GOOGLE_MEET_CLIENT_SECRET'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    http_response_code(500);
    exit('Google Meet OAuth configuration is incomplete.');
}

/*
|--------------------------------------------------------------------------
| Load Stored Refresh Token
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare("
    SELECT refresh_token
    FROM google_meet_oauth
    WHERE provider = 'google_meet'
    LIMIT 1
");

$statement->execute();

$oauth = $statement->fetch(PDO::FETCH_ASSOC);

if (!$oauth || empty($oauth['refresh_token'])) {
    http_response_code(400);
    exit('Google Meet is not connected.');
}

$refreshToken = $oauth['refresh_token'];

/*
|--------------------------------------------------------------------------
| Get Fresh Google Access Token
|--------------------------------------------------------------------------
*/

$tokenUrl = 'https://oauth2.googleapis.com/token';

$postFields = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'refresh_token' => $refreshToken,
    'grant_type' => 'refresh_token',
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

$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tokenCurlError = curl_error($ch);

curl_close($ch);

if ($tokenResponse === false || !empty($tokenCurlError)) {
    http_response_code(500);
    exit('Unable to communicate with Google OAuth.');
}

$tokenData = json_decode($tokenResponse, true);

if (
    $tokenHttpCode < 200 ||
    $tokenHttpCode >= 300 ||
    !is_array($tokenData) ||
    empty($tokenData['access_token'])
) {
    http_response_code(500);
    exit('Unable to obtain a Google access token.');
}

$accessToken = $tokenData['access_token'];

/*
|--------------------------------------------------------------------------
| Create Google Meet Space
|--------------------------------------------------------------------------
*/

$meetUrl = 'https://meet.googleapis.com/v2/spaces';

$ch = curl_init($meetUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$meetResponse = curl_exec($ch);
$meetHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$meetCurlError = curl_error($ch);

curl_close($ch);

if ($meetResponse === false || !empty($meetCurlError)) {
    http_response_code(500);
    exit('Unable to communicate with Google Meet.');
}

$meetData = json_decode($meetResponse, true);

if (
    $meetHttpCode < 200 ||
    $meetHttpCode >= 300 ||
    !is_array($meetData)
) {
    http_response_code(500);

    echo '<pre>';
    echo htmlspecialchars(
        $meetResponse,
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';

    exit;
}

/*
|--------------------------------------------------------------------------
| Extract Meeting Information
|--------------------------------------------------------------------------
*/

$meetingUri = $meetData['meetingUri'] ?? '';
$spaceName = $meetData['name'] ?? '';

if (empty($meetingUri)) {
    http_response_code(500);
    exit('Google created the space but did not return a meeting URL.');
}

/*
|--------------------------------------------------------------------------
| Display Result
|--------------------------------------------------------------------------
*/

echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Google Meet Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            padding: 40px;
        }

        .box {
            max-width: 700px;
            margin: 50px auto;
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

        .meeting {
            margin-top: 20px;
            padding: 15px;
            background: #f1f3f5;
            border-radius: 8px;
            word-break: break-all;
        }

        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background: #0d6efd;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
        }
    </style>
</head>

<body>

<div class="box">

    <h1>Google Meet Test</h1>

    <p class="success">
        Google Meet space created successfully.
    </p>

    <p>
        <strong>Meeting Space:</strong><br>
        ' . htmlspecialchars(
            $spaceName,
            ENT_QUOTES,
            'UTF-8'
        ) . '
    </p>

    <div class="meeting">
        <strong>Meeting URL:</strong><br>
        <a
            href="' . htmlspecialchars(
                $meetingUri,
                ENT_QUOTES,
                'UTF-8'
            ) . '"
            target="_blank"
            rel="noopener noreferrer">
            ' . htmlspecialchars(
                $meetingUri,
                ENT_QUOTES,
                'UTF-8'
            ) . '
        </a>
    </div>

</div>

</body>
</html>
';