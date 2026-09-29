<?php

/*
|--------------------------------------------------------------------------
| Stripe Configuration
|--------------------------------------------------------------------------
|
| Main application only. Keep secret keys in .env and never commit them.
|
*/

$envFile = dirname(__DIR__) . '/.env';
$env = parse_ini_file($envFile);

if ($env === false) {
    die('Unable to load environment configuration.');
}

$secretKey = trim((string) ($env['STRIPE_SECRET_KEY'] ?? ''));
$publishableKey = trim((string) ($env['STRIPE_PUBLISHABLE_KEY'] ?? ''));
$webhookSecret = trim((string) ($env['STRIPE_WEBHOOK_SECRET'] ?? ''));

if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', $secretKey);
}

if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', $publishableKey);
}

if (!defined('STRIPE_WEBHOOK_SECRET')) {
    define('STRIPE_WEBHOOK_SECRET', $webhookSecret);
}

if (!defined('STRIPE_API_BASE_URL')) {
    define('STRIPE_API_BASE_URL', 'https://api.stripe.com/v1/');
}

function stripeIsConfigured(): bool
{
    return STRIPE_SECRET_KEY !== '' && STRIPE_WEBHOOK_SECRET !== '';
}

function stripeFlattenParams(array $params, string $prefix = ''): array
{
    $result = [];

    foreach ($params as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

        if (is_array($value)) {
            $result += stripeFlattenParams($value, $name);
        } else {
            $result[$name] = (string) $value;
        }
    }

    return $result;
}

function stripeApiRequest(
    string $method,
    string $endpoint,
    array $params = []
): array {
    if (STRIPE_SECRET_KEY === '') {
        throw new RuntimeException('Stripe secret key is not configured.');
    }

    $method = strtoupper($method);
    $url = rtrim(STRIPE_API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Unable to initialize Stripe connection.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query(
            stripeFlattenParams($params),
            '',
            '&'
        );
        $options[CURLOPT_HTTPHEADER][] =
            'Content-Type: application/x-www-form-urlencoded';
    } elseif ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    curl_setopt_array($ch, $options);

    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException(
            'Stripe connection failed: ' . ($curlError ?: 'Unknown error')
        );
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $decoded['error']['message'] ?? 'Stripe API request failed.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function stripeVerifyWebhookSignature(
    string $payload,
    string $signatureHeader,
    int $toleranceSeconds = 300
): bool {
    if (STRIPE_WEBHOOK_SECRET === '' || $signatureHeader === '') {
        return false;
    }

    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

        if ($key === 't') {
            $timestamp = ctype_digit((string) $value) ? (int) $value : null;
        } elseif ($key === 'v1' && is_string($value)) {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > $toleranceSeconds) {
        return false;
    }

    $expected = hash_hmac(
        'sha256',
        $timestamp . '.' . $payload,
        STRIPE_WEBHOOK_SECRET
    );

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
