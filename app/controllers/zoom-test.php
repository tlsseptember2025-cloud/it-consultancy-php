<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/zoom.php';

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
        <title>Confirm Zoom API Test</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 650px;">
            <div class="card-body">
                <h4 class="mb-3">Confirm Zoom API Test</h4>
                <p>This test creates an external meeting using the configured integration.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-primary">Create Test Zoom Meeting</button>
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


try {

    $accessToken = getZoomAccessToken();

    /*
     * Create a test meeting one hour from now.
     */
    $startTimestamp = time() + (60 * 60);

    $startDateTime = date(
        'Y-m-d\TH:i:s',
        $startTimestamp
    );

    $meeting = createZoomMeeting(
        $accessToken,
        'IT Consultancy - Zoom API Test',
        $startDateTime,
        60
    );

    $joinUrl = (string) (
        $meeting['join_url'] ?? ''
    );

    $meetingId = (string) (
        $meeting['id'] ?? ''
    );

    if ($joinUrl === '') {
        throw new RuntimeException(
            'Zoom did not return a meeting join URL.'
        );
    }

    echo '
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Zoom Meeting Test</title>
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >
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

            .success {
                color: #198754;
                font-weight: 600;
            }

            .meeting {
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 6px;
                word-break: break-word;
            }

            a {
                color: #0d6efd;
            }
        </style>
    </head>

    <body>

    <div class="box">

        <h1>Zoom Meeting Test</h1>

        <p class="success">
            Zoom meeting created successfully.
        </p>

        <p>
            <strong>Meeting ID:</strong>
            ' . htmlspecialchars(
                $meetingId,
                ENT_QUOTES,
                'UTF-8'
            ) . '
        </p>

        <p>
            <strong>Start:</strong>
            ' . htmlspecialchars(
                $startDateTime,
                ENT_QUOTES,
                'UTF-8'
            ) . '
        </p>

        <div class="meeting">

            <strong>Join URL:</strong><br>

            <a
                href="' . htmlspecialchars(
                    $joinUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '"
                target="_blank"
                rel="noopener noreferrer"
            >
                ' . htmlspecialchars(
                    $joinUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '
            </a>

        </div>

        <p>
            This was a real Zoom meeting created for API testing.
        </p>

    </div>

    </body>

    </html>
    ';

} catch (Throwable $e) {

    http_response_code(500);

    echo '
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Zoom Meeting Test Error</title>
    </head>

    <body>

    <h1>Zoom Meeting Test Failed</h1>

    <pre>';

    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '</pre>

    </body>

    </html>';
}