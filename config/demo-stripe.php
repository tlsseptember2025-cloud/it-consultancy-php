<?php

/*
|--------------------------------------------------------------------------
| Demo Stripe Test/Sandbox Configuration
|--------------------------------------------------------------------------
|
| The Demo uses the same Stripe Test/Sandbox API credentials as Dev.
| The Demo webhook has its own endpoint-specific signing secret.
|
*/

$envFile = dirname(__DIR__) . '/.env';
$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($env === false) {
    throw new RuntimeException('Unable to load environment configuration.');
}

if (!defined('DEMO_APP_URL')) {
    define(
        'DEMO_APP_URL',
        rtrim((string) ($env['DEMO_APP_URL'] ?? ''), '/')
    );
}

if (!defined('DEMO_STRIPE_SECRET_KEY')) {
    define(
        'DEMO_STRIPE_SECRET_KEY',
        trim((string) ($env['STRIPE_SECRET_KEY'] ?? ''))
    );
}

if (!defined('DEMO_STRIPE_PUBLISHABLE_KEY')) {
    define(
        'DEMO_STRIPE_PUBLISHABLE_KEY',
        trim((string) ($env['STRIPE_PUBLISHABLE_KEY'] ?? ''))
    );
}

if (!defined('DEMO_STRIPE_WEBHOOK_SECRET')) {
    define(
        'DEMO_STRIPE_WEBHOOK_SECRET',
        trim((string) ($env['DEMO_STRIPE_WEBHOOK_SECRET'] ?? ''))
    );
}

if (!defined('DEMO_STRIPE_API_BASE_URL')) {
    define(
        'DEMO_STRIPE_API_BASE_URL',
        'https://api.stripe.com/v1/'
    );
}

function demoStripeIsConfigured(): bool
{
    return DEMO_APP_URL !== ''
        && DEMO_STRIPE_SECRET_KEY !== ''
        && DEMO_STRIPE_WEBHOOK_SECRET !== '';
}

function demoStripeFlattenParams(array $params, string $prefix = ''): array
{
    $result = [];

    foreach ($params as $key => $value) {
        $name = $prefix === ''
            ? (string) $key
            : $prefix . '[' . $key . ']';

        if (is_array($value)) {
            $result += demoStripeFlattenParams($value, $name);
        } else {
            $result[$name] = (string) $value;
        }
    }

    return $result;
}

function demoStripeApiRequest(
    string $method,
    string $endpoint,
    array $params = []
): array {
    if (DEMO_STRIPE_SECRET_KEY === '') {
        throw new RuntimeException('Demo Stripe secret key is not configured.');
    }

    $method = strtoupper($method);
    $url = rtrim(DEMO_STRIPE_API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Unable to initialize Stripe connection.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => DEMO_STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query(
            demoStripeFlattenParams($params),
            '',
            '&'
        );
        $options[CURLOPT_HTTPHEADER][] =
            'Content-Type: application/x-www-form-urlencoded';
    } elseif ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
        $options[CURLOPT_URL] = $url;
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

function demoStripeVerifyWebhookSignature(
    string $payload,
    string $signatureHeader,
    int $toleranceSeconds = 300
): bool {
    if (DEMO_STRIPE_WEBHOOK_SECRET === '' || $signatureHeader === '') {
        return false;
    }

    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(
            explode('=', trim($part), 2),
            2,
            null
        );

        if ($key === 't') {
            $timestamp = ctype_digit((string) $value)
                ? (int) $value
                : null;
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
        DEMO_STRIPE_WEBHOOK_SECRET
    );

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
