<?php

/*
|--------------------------------------------------------------------------
| Secure Cron Runner
|--------------------------------------------------------------------------
|
| Public entry point for IONOS Cron Jobs.
|
| The actual cron scripts remain outside the public web root.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Base Path
|--------------------------------------------------------------------------
*/

$basePath = dirname(__DIR__);

/*
|--------------------------------------------------------------------------
| Prevent intermediary caching
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


/*
|--------------------------------------------------------------------------
| Load Environment Configuration
|--------------------------------------------------------------------------
*/

$envFile = $basePath . '/.env';

if (!file_exists($envFile)) {

    http_response_code(500);
    exit('Cron configuration unavailable.');

}

$env = parse_ini_file($envFile);

if ($env === false || empty($env['CRON_SECRET'])) {

    http_response_code(500);
    exit('Cron secret is not configured.');

}


/*
|--------------------------------------------------------------------------
| Verify Cron Secret
|--------------------------------------------------------------------------
*/

$providedKey = $_SERVER['HTTP_X_CRON_KEY'] ?? '';

if ($providedKey === '') {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        $providedKey = trim($matches[1]);
    }
}

if ($providedKey === '') {
    // Backward compatibility for existing IONOS cron URLs.
    $providedKey = (string) ($_GET['key'] ?? '');
}

if (
    $providedKey === '' ||
    !hash_equals((string) $env['CRON_SECRET'], $providedKey)
) {

    http_response_code(403);
    exit('Forbidden.');

}


/*
|--------------------------------------------------------------------------
| Allowed Cron Jobs
|--------------------------------------------------------------------------
*/

$jobs = [

    'check-missed-consultations'
        => 'check-missed-consultations.php',

    'detect-missed-consultations'
        => 'detect-missed-consultations.php',

    'detect-missed-service-jobs'
        => 'detect-missed-service-jobs.php',

    'detect-overdue-consultations'
        => 'detect-overdue-consultations.php',

    'detect-overdue-service-jobs'
        => 'detect-overdue-service-jobs.php',

    'send-second-verification-emails'
        => 'send-second-verification-emails.php',

];


/*
|--------------------------------------------------------------------------
| Validate Requested Job
|--------------------------------------------------------------------------
*/

$environment = $_GET['env'] ?? 'main';

if (!in_array($environment, ['main', 'demo'], true)) {

    http_response_code(400);
    exit('Invalid cron environment.');

}

if (!defined('CRON_ENVIRONMENT')) {
    define('CRON_ENVIRONMENT', $environment);
}

$cronAppUrl = $environment === 'demo'
    ? (string) ($env['DEMO_APP_URL'] ?? '')
    : (string) ($env['APP_URL'] ?? '');

if (!defined('CRON_APP_URL')) {
    define('CRON_APP_URL', rtrim($cronAppUrl, '/'));
}


$job = $_GET['job'] ?? '';

if (!isset($jobs[$job])) {

    http_response_code(404);
    exit('Cron job not found.');

}


/*
|--------------------------------------------------------------------------
| Build Private Script Path
|--------------------------------------------------------------------------
*/

$script = $basePath . '/cron/' . $jobs[$job];

if (!is_file($script)) {

    http_response_code(500);
    exit('Cron script unavailable.');

}


/*
|--------------------------------------------------------------------------
| Execute Cron Job
|--------------------------------------------------------------------------
*/

echo "Starting cron job: {$job}\n";

require $script;

echo "\nCron job completed: {$job}\n";