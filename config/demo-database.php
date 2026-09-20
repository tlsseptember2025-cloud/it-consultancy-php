<?php

require_once __DIR__ . '/workflow.php';

/**
 * Demo Database Configuration
 *
 * Demo database credentials are loaded from the server .env file.
 */


/**
 * Load Environment Configuration
 */

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die('Unable to load environment configuration.');
}

$env = parse_ini_file($envFile);

if ($env === false) {
    die('Unable to load environment configuration.');
}


/**
 * Demo Database Configuration
 */

$host     = $env['DEMO_DB_HOST'] ?? '';
$port     = $env['DEMO_DB_PORT'] ?? '';
$dbname   = $env['DEMO_DB_NAME'] ?? '';
$username = $env['DEMO_DB_USER'] ?? '';
$password = $env['DEMO_DB_PASS'] ?? '';
$sslCa    = $env['DEMO_DB_SSL_CA'] ?? '';


/**
 * Validate Required Configuration
 */

if (
    empty($host) ||
    empty($port) ||
    empty($dbname) ||
    empty($username) ||
    empty($password) ||
    empty($sslCa)
) {
    die('Demo database configuration is missing.');
}


/**
 * SSL Certificate
 */

$sslCaPath = dirname(__DIR__) . '/' . ltrim($sslCa, '/');


/**
 * Demo Database Connection
 */

try {

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    $demoPdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );


    /**
     * Set MySQL Session Timezone
     */

    $demoPdo->exec("
        SET time_zone = '+04:00'
    ");


} catch (PDOException $e) {

    die('Demo database connection failed: ' . $e->getMessage());

}