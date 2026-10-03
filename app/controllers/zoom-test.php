<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once CONFIG_PATH . '/database.php';
require_once HELPER_PATH . '/auth.php';
require_once HELPER_PATH . '/zoom.php';

requireAdminLogin();

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