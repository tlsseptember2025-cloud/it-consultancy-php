<?php

/**
 * Google Meet Helper
 *
 * Handles:
 * - Google OAuth configuration
 * - Access-token refresh
 * - Google Meet space creation
 *
 * The Google refresh token is stored in:
 * google_meet_oauth
 */


/**
 * Load Google Meet configuration from .env
 */
function getGoogleMeetConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $envPath = dirname(__DIR__, 2) . '/.env';

    $env = parse_ini_file($envPath);

    if ($env === false) {
        throw new RuntimeException(
            'Unable to load .env configuration file.'
        );
    }

    $required = [
        'GOOGLE_MEET_CLIENT_ID',
        'GOOGLE_MEET_CLIENT_SECRET',
        'GOOGLE_MEET_REDIRECT_URI',
    ];

    foreach ($required as $key) {
        if (
            !isset($env[$key]) ||
            trim((string) $env[$key]) === ''
        ) {
            throw new RuntimeException(
                'Missing Google Meet configuration: ' . $key
            );
        }
    }

    $config = [
        'client_id' => trim((string) $env['GOOGLE_MEET_CLIENT_ID']),
        'client_secret' => trim((string) $env['GOOGLE_MEET_CLIENT_SECRET']),
        'redirect_uri' => trim((string) $env['GOOGLE_MEET_REDIRECT_URI']),
    ];

    return $config;
}


/**
 * Get the Google Meet OAuth authorization URL.
 */
function getGoogleMeetAuthorizationUrl(string $state): string
{
    $config = getGoogleMeetConfig();

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/meetings.space.created',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' .
        http_build_query($params);
}


/**
 * Exchange a Google authorization code for OAuth tokens.
 *
 * Returns:
 * [
 *     'access_token' => '...',
 *     'expires_in' => 3599,
 *     'refresh_token' => '...'
 * ]
 */
function exchangeGoogleMeetAuthorizationCode(
    string $authorizationCode
): array {

    $config = getGoogleMeetConfig();

    $postFields = http_build_query([
        'code' => $authorizationCode,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);

    $ch = curl_init(
        'https://oauth2.googleapis.com/token'
    );

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

    if ($response === false) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            'Google OAuth request failed: ' . $error
        );
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array($data)
    ) {
        throw new RuntimeException(
            'Google OAuth token exchange failed.'
        );
    }

    if (empty($data['access_token'])) {
        throw new RuntimeException(
            'Google did not return an access token.'
        );
    }

    return $data;
}


/**
 * Refresh the Google access token using the stored refresh token.
 */
function refreshGoogleMeetAccessToken(
    string $refreshToken
): string {

    $config = getGoogleMeetConfig();

    $postFields = http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);

    $ch = curl_init(
        'https://oauth2.googleapis.com/token'
    );

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

    if ($response === false) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            'Google access-token refresh failed: ' . $error
        );
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array($data) ||
        empty($data['access_token'])
    ) {
        throw new RuntimeException(
            'Google access-token refresh failed.'
        );
    }

    return $data['access_token'];
}


/**
 * Create a Google Meet space.
 *
 * Returns the Google Meet URL.
 */
function createGoogleMeetSpace(
    string $accessToken
): string {

    $ch = curl_init(
        'https://meet.googleapis.com/v2/spaces'
    );

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

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            'Google Meet creation request failed: ' . $error
        );
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array($data)
    ) {
        throw new RuntimeException(
            'Google Meet creation failed.'
        );
    }

    if (
        empty($data['meetingUri']) ||
        !is_string($data['meetingUri'])
    ) {
        throw new RuntimeException(
            'Google Meet did not return a meeting URL.'
        );
    }

    return $data['meetingUri'];
}