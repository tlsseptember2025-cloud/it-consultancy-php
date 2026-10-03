<?php

/**
 * Zoom Server-to-Server OAuth helper.
 *
 * Used to:
 * 1. Load Zoom configuration from .env
 * 2. Generate a Zoom access token
 * 3. Create a scheduled Zoom meeting
 */


/**
 * Load Zoom configuration.
 */
function getZoomConfig(): array
{
    $envPath = dirname(__DIR__, 2) . '/.env';

    $env = parse_ini_file($envPath);

    if ($env === false) {
        throw new RuntimeException(
            'Unable to load application configuration.'
        );
    }

    $accountId = trim(
        (string) ($env['ZOOM_ACCOUNT_ID'] ?? '')
    );

    $clientId = trim(
        (string) ($env['ZOOM_CLIENT_ID'] ?? '')
    );

    $clientSecret = trim(
        (string) ($env['ZOOM_CLIENT_SECRET'] ?? '')
    );

    $userEmail = trim(
        (string) ($env['ZOOM_USER_EMAIL'] ?? '')
    );

    if (
        $accountId === ''
        || $clientId === ''
        || $clientSecret === ''
        || $userEmail === ''
    ) {
        throw new RuntimeException(
            'Zoom configuration is incomplete.'
        );
    }

    return [
        'account_id' => $accountId,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'user_email' => $userEmail,
    ];
}


/**
 * Generate a fresh Zoom access token.
 *
 * Server-to-Server OAuth tokens expire after one hour,
 * so a new token is generated when needed.
 */
function getZoomAccessToken(): string
{
    $config = getZoomConfig();

    $credentials = base64_encode(
        $config['client_id']
        . ':'
        . $config['client_secret']
    );

    $tokenUrl = 'https://zoom.us/oauth/token';

    $postFields = http_build_query([
        'grant_type' => 'account_credentials',
        'account_id' => $config['account_id'],
    ]);

    $ch = curl_init($tokenUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $response === false
        || $curlError !== ''
    ) {
        throw new RuntimeException(
            'Unable to communicate with Zoom OAuth.'
        );
    }

    $data = json_decode(
        $response,
        true
    );

    if (
        $httpCode < 200
        || $httpCode >= 300
        || !is_array($data)
        || empty($data['access_token'])
    ) {
        $zoomMessage = '';

        if (is_array($data)) {
            $zoomMessage = trim(
                (string) (
                    $data['message']
                    ?? ''
                )
            );
        }

        if ($zoomMessage === '') {
            $zoomMessage = 'Unknown Zoom OAuth error.';
        }

        throw new RuntimeException(
            'Unable to obtain a Zoom access token. '
            . 'HTTP ' . $httpCode . ': '
            . $zoomMessage
        );
    }

    return (string) $data['access_token'];
}

/**
 * Get the authenticated Zoom user's ID.
 */
function getZoomUserId(string $accessToken): string
{
    $userUrl = 'https://api.zoom.us/v2/users/me';

    $ch = curl_init($userUrl);

    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $response === false
        || $curlError !== ''
    ) {
        throw new RuntimeException(
            'Unable to communicate with Zoom user API.'
        );
    }

    $data = json_decode(
        $response,
        true
    );

    if (
        $httpCode < 200
        || $httpCode >= 300
        || !is_array($data)
        || empty($data['id'])
    ) {
        $zoomMessage = '';

        if (is_array($data)) {
            $zoomMessage = trim(
                (string) (
                    $data['message']
                    ?? ''
                )
            );
        }

        if ($zoomMessage === '') {
            $zoomMessage = 'Unknown Zoom user API error.';
        }

        throw new RuntimeException(
            'Unable to obtain Zoom user ID. '
            . 'HTTP ' . $httpCode . ': '
            . $zoomMessage
        );
    }

    return (string) $data['id'];
}

/**
 * Create a scheduled Zoom meeting.
 *
 * Uses the authenticated Zoom user's actual User ID.
 */
function createZoomMeeting(
    string $accessToken,
    string $topic,
    string $startDateTime,
    int $durationMinutes = 60
): array {

    $config = getZoomConfig();

    /*
     * Get the actual Zoom User ID.
     *
     * We do not use the email address as the user ID.
     */
    $userId = getZoomUserId(
        $accessToken
    );

    /*
     * Account-level Server-to-Server OAuth
     * meeting creation endpoint.
     */
    $meetingUrl =
    'https://api.zoom.us/v2/users/'
    . rawurlencode($userId)
    . '/meetings';

    $payload = [
        'topic' => $topic,
        'type' => 2,
        'start_time' => $startDateTime,
        'duration' => $durationMinutes,
        'timezone' => 'Asia/Dubai',
        'settings' => [
            'waiting_room' => true,
        ],
    ];

    $ch = curl_init($meetingUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if (
        $response === false
        || $curlError !== ''
    ) {
        throw new RuntimeException(
            'Unable to communicate with Zoom.'
        );
    }

    $data = json_decode(
        $response,
        true
    );

    if (
        $httpCode < 200
        || $httpCode >= 300
        || !is_array($data)
    ) {

        $zoomMessage = '';

        if (is_array($data)) {
            $zoomMessage = trim(
                (string) (
                    $data['message']
                    ?? ''
                )
            );
        }

        if ($zoomMessage === '') {
            $zoomMessage = 'Unknown Zoom API error.';
        }

        throw new RuntimeException(
            'Zoom meeting creation failed. '
            . 'HTTP ' . $httpCode . ': '
            . $zoomMessage
        );
    }

    if (empty($data['join_url'])) {
        throw new RuntimeException(
            'Zoom created the meeting but did not return a join URL.'
        );
    }

    return $data;
}