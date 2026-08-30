<?php

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

/**
 * Application mode
 *
 * demo
 * development
 * production
 */
define('APP_MODE', 'demo');

date_default_timezone_set('Asia/Dubai');


/*
|--------------------------------------------------------------------------
| Environment Configuration
|--------------------------------------------------------------------------
|
| APP_URL is environment-specific.
|
| Local:
| http://localhost/it-consultancy-php/public
|
| DEV:
| https://dev.wahbibconsultancy.com
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