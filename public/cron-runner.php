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

$providedKey = $_GET['key'] ?? '';

if (
    empty($providedKey) ||
    !hash_equals($env['CRON_SECRET'], $providedKey)
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