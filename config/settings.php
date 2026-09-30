<?php

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

/**
 * Application mode
 *
 * development
 * production
 */
/*
|--------------------------------------------------------------------------
| Environment Configuration
|--------------------------------------------------------------------------
|
| APP_URL and APP_MODE are environment-specific.
|
| Local:
| APP_MODE=development
| APP_URL=http://localhost/it-consultancy-php/public
|
| DEV / Production:
| APP_MODE=production
| APP_URL=https://dev.wahbibconsultancy.com
|
*/

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die('Unable to load environment configuration.');
}

$env = parse_ini_file($envFile);

if ($env === false) {
    die('Unable to load environment configuration.');
}

$appMode = strtolower(trim((string) ($env['APP_MODE'] ?? 'production')));

if (!in_array($appMode, ['development', 'production'], true)) {
    $appMode = 'production';
}

define('APP_MODE', $appMode);

date_default_timezone_set('Asia/Dubai');

if (empty($env['APP_URL'])) {
    die('APP_URL is not configured.');
}

define(
    'APP_URL',
    rtrim($env['APP_URL'], '/')
);


/*
|--------------------------------------------------------------------------
| Company Configuration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/company.php';


/*
|--------------------------------------------------------------------------
| Uploads
|--------------------------------------------------------------------------
*/

define('UPLOAD_URL', '/uploads/');


/*
|--------------------------------------------------------------------------
| Workflow
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/workflow.php';


/*
|--------------------------------------------------------------------------
| Authentication Helpers
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../app/helpers/auth.php';